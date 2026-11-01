<?php
declare(strict_types=1);

// Web Push — obaveštenja direktno iz aplikacije (bez Telegrama, bez Composer-a).
// Standardi: RFC 8291 (šifrovanje aes128gcm) i RFC 8292 (VAPID).
// Ključevi (VAPID) se prave sami pri prvoj upotrebi i čuvaju u tabeli settings.

const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

function b64u_enc(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function b64u_dec(string $s): string
{
    return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
}

function push_supported(): bool
{
    return function_exists('openssl_pkey_derive') && function_exists('hash_hkdf') && function_exists('curl_init');
}

/** Sirov javni ključ P-256 (65 bajtova, 0x04||X||Y) iz OpenSSL ključa. */
function p256_raw_public(OpenSSLAsymmetricKey $key): string
{
    $d = openssl_pkey_get_details($key)['ec'];
    return "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT);
}

/** OpenSSL javni ključ iz sirovih 65 bajtova. */
function p256_public_from_raw(string $raw): OpenSSLAsymmetricKey
{
    if (strlen($raw) !== 65 || $raw[0] !== "\x04") {
        throw new RuntimeException('Neispravan javni ključ pretplate');
    }
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(P256_SPKI_PREFIX) . $raw), 64, "\n") . "-----END PUBLIC KEY-----\n";
    $k = openssl_pkey_get_public($pem);
    if (!$k) {
        throw new RuntimeException('Ne mogu da učitam javni ključ pretplate');
    }
    return $k;
}

function p256_new_key(): OpenSSLAsymmetricKey
{
    $k = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if (!$k) {
        throw new RuntimeException('OpenSSL ne može da napravi EC ključ');
    }
    return $k;
}

/** VAPID ključevi: [javni base64url (za browser), privatni PEM]. Pravi ih ako ne postoje. */
function push_vapid(): array
{
    $pub = (string) setting('vapid_public', '');
    $priv = (string) setting('vapid_private', '');
    if ($pub === '' || $priv === '') {
        $k = p256_new_key();
        openssl_pkey_export($k, $priv);
        $pub = b64u_enc(p256_raw_public($k));
        setting_set('vapid_private', $priv);
        setting_set('vapid_public', $pub);
    }
    return [$pub, $priv];
}

/** DER ECDSA potpis → sirovi R||S (64 bajta) za JWT ES256. */
function ecdsa_der_to_raw(string $der): string
{
    $pos = 2;                                   // 0x30 len
    if (ord($der[1]) & 0x80) {
        $pos += ord($der[1]) & 0x7f;
    }
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $len = ord($der[$pos + 1]);              // 0x02 len
        $int = substr($der, $pos + 2, $len);
        $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
        $pos += 2 + $len;
    }
    return $out;
}

/** VAPID zaglavlje za dati endpoint. */
function push_vapid_header(string $endpoint): string
{
    [$pub, $priv] = push_vapid();
    $u = parse_url($endpoint);
    $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
    $sub = (string) config('base_url', '');
    if (!str_starts_with($sub, 'https://')) {
        $sub = 'mailto:montaza@aggroup.rs';
    }
    $jwt = b64u_enc(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])) . '.'
         . b64u_enc(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => $sub]));
    if (!openssl_sign($jwt, $der, $priv, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('VAPID potpis nije uspeo');
    }
    return 'vapid t=' . $jwt . '.' . b64u_enc(ecdsa_der_to_raw($der)) . ', k=' . $pub;
}

/**
 * Šifruje poruku za jednu pretplatu (RFC 8291, aes128gcm, jedan zapis).
 * $p256dh i $auth su base64url vrednosti koje šalje browser.
 */
function push_encrypt(string $payload, string $p256dh, string $auth): string
{
    $uaPublic = b64u_dec($p256dh);
    $authSecret = b64u_dec($auth);
    if (strlen($authSecret) !== 16) {
        throw new RuntimeException('Neispravan auth ključ pretplate');
    }
    $as = p256_new_key();                              // privremeni ključ servera
    $asPublic = p256_raw_public($as);
    $ecdh = openssl_pkey_derive(p256_public_from_raw($uaPublic), $as, 32);
    if ($ecdh === false) {
        throw new RuntimeException('ECDH nije uspeo');
    }
    $ikm = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0" . $uaPublic . $asPublic, $authSecret);
    $salt = random_bytes(16);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Šifrovanje nije uspelo');
    }
    return $salt . pack('N', 4096) . chr(65) . $asPublic . $cipher . $tag;
}

/** Šalje jednu poruku na jednu pretplatu. Vraća HTTP status (0 = nema veze). */
function push_send_one(array $sub, array $message): int
{
    $body = push_encrypt(json_encode($message, JSON_UNESCAPED_UNICODE), $sub['p256dh'], $sub['auth']);
    $ch = curl_init($sub['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . push_vapid_header($sub['endpoint']),
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400',
            'Urgency: high',
        ],
    ]);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($code === 0 || $code >= 400) {
        error_log('push ' . $code . ' ' . parse_url($sub['endpoint'], PHP_URL_HOST) . ': ' . (curl_error($ch) ?: substr((string) $res, 0, 200)));
    }
    curl_close($ch);
    return $code;
}

/**
 * Šalje obaveštenje na sve telefone korisnika.
 * Pretplate koje više ne važe (404/410) se brišu.
 */
function push_to_user(int $userId, string $title, string $body, ?string $url = null): int
{
    if (!push_supported()) {
        return 0;
    }
    $sent = 0;
    foreach (q_all('SELECT * FROM push_subscriptions WHERE user_id = ?', [$userId]) as $sub) {
        try {
            $code = push_send_one($sub, [
                'title' => $title,
                'body'  => mb_substr($body, 0, 1000),
                'url'   => $url ?: url(),
                'tag'   => 'mz-' . substr(md5($title . $body), 0, 8),
            ]);
            if ($code === 404 || $code === 410) {
                q('DELETE FROM push_subscriptions WHERE id = ?', [$sub['id']]);
            } elseif ($code >= 200 && $code < 300) {
                q('UPDATE push_subscriptions SET last_used_at = NOW() WHERE id = ?', [$sub['id']]);
                $sent++;
            }
        } catch (Throwable $ex) {
            error_log('push: ' . $ex->getMessage());
        }
    }
    return $sent;
}

function push_save_subscription(int $userId, array $s): void
{
    $endpoint = (string) ($s['endpoint'] ?? '');
    $p256dh = (string) ($s['keys']['p256dh'] ?? '');
    $auth = (string) ($s['keys']['auth'] ?? '');
    if (!preg_match('#^https://#', $endpoint) || strlen(b64u_dec($p256dh)) !== 65 || strlen(b64u_dec($auth)) !== 16) {
        throw new RuntimeException('Neispravna pretplata');
    }
    q(
        'INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent)',
        [$userId, $endpoint, hash('sha256', $endpoint), $p256dh, $auth, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]
    );
}

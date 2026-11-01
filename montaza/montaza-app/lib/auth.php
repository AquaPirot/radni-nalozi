<?php
declare(strict_types=1);

const REMEMBER_COOKIE = 'mz_remember';
const REMEMBER_DAYS   = 30;
const LOGIN_MAX_TRIES = 5;
const LOGIN_WINDOW_MIN = 15;

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || str_starts_with((string) config('base_url', ''), 'https://');
}

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('mz_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    if (empty($_SESSION['uid']) && !empty($_COOKIE[REMEMBER_COOKIE])) {
        remember_login();
    }
}

/** Trenutni korisnik ili null. Keširano po zahtevu. */
function current_user(): ?array
{
    static $cachedUid = false;
    static $user = null;
    $uid = $_SESSION['uid'] ?? null;
    if ($cachedUid === $uid) {
        return $user;
    }
    $cachedUid = $uid;
    $user = $uid ? q_one('SELECT id, name, username, role, telegram_chat_id FROM users WHERE id = ? AND active = 1', [$uid]) : null;
    if ($uid && !$user) {
        // korisnik deaktiviran ili obrisan
        logout();
    }
    return $user;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect(url('login'));
    }
    return $u;
}

/** Proverava ulogu NA SERVERU. 403 ako nema pravo. */
function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        view('403', ['title' => 'Nemaš pristup']);
        exit;
    }
    return $u;
}

function has_role(string ...$roles): bool
{
    $u = current_user();
    return $u !== null && in_array($u['role'], $roles, true);
}

// ── Login / logout ──────────────────────────────────────────

function login_is_blocked(string $ip): bool
{
    $n = (int) q_val(
        'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MIN . ' MINUTE)',
        [$ip]
    );
    return $n >= LOGIN_MAX_TRIES;
}

/** Vraća null ako je uspešno, inače poruku greške. */
function attempt_login(string $username, string $password, bool $remember): ?string
{
    $ip = client_ip();
    if (login_is_blocked($ip)) {
        return 'Previše pokušaja. Sačekaj ' . LOGIN_WINDOW_MIN . ' minuta.';
    }

    $user = q_one('SELECT id, password_hash, active FROM users WHERE username = ?', [$username]);
    // password_verify se poziva i kad korisnik ne postoji, da vreme odgovora ne otkriva postojanje naloga
    $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    $ok = password_verify($password, $hash) && $user && (int) $user['active'] === 1;

    if (!$ok) {
        q('INSERT INTO login_attempts (ip, username) VALUES (?, ?)', [$ip, mb_substr($username, 0, 50)]);
        return 'Pogrešno korisničko ime ili lozinka.';
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];

    if ($remember) {
        remember_issue((int) $user['id']);
    }
    log_activity('login');
    return null;
}

function logout(): void
{
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        [$selector] = explode(':', (string) $_COOKIE[REMEMBER_COOKIE]) + [''];
        q('DELETE FROM auth_tokens WHERE selector = ?', [$selector]);
        remember_cookie('', time() - 3600);
    }
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

// ── "Zapamti me" (selector:validator, u bazi samo hash) ──────

function remember_cookie(string $value, int $expires): void
{
    setcookie(REMEMBER_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function remember_issue(int $userId): void
{
    $selector  = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires   = time() + REMEMBER_DAYS * 86400;
    q(
        'INSERT INTO auth_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)',
        [$userId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expires)]
    );
    remember_cookie($selector . ':' . $validator, $expires);
}

function remember_login(): void
{
    [$selector, $validator] = explode(':', (string) $_COOKIE[REMEMBER_COOKIE]) + ['', ''];
    if ($selector === '' || $validator === '') {
        remember_cookie('', time() - 3600);
        return;
    }
    $row = q_one(
        'SELECT t.id, t.user_id, t.token_hash FROM auth_tokens t JOIN users u ON u.id = t.user_id
         WHERE t.selector = ? AND t.expires_at > NOW() AND u.active = 1',
        [$selector]
    );
    if (!$row || !hash_equals($row['token_hash'], hash('sha256', $validator))) {
        if ($row) {
            q('DELETE FROM auth_tokens WHERE id = ?', [$row['id']]);
        }
        remember_cookie('', time() - 3600);
        return;
    }
    // Rotacija tokena pri svakoj upotrebi
    q('DELETE FROM auth_tokens WHERE id = ?', [$row['id']]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $row['user_id'];
    remember_issue((int) $row['user_id']);
}

// ── CSRF ────────────────────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Poziva se na početku SVAKE POST akcije. */
function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        exit('Sesija je istekla. Osveži stranicu i pokušaj ponovo.');
    }
}

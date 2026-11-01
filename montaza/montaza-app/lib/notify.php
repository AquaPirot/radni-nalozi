<?php
declare(strict_types=1);

// Obaveštenja preko Telegram bota (spec tačka 9).
// Slanje NIKAD ne obara akciju: sve greške idu u log.

/** Šalje poruku na jedan chat. Vraća null ako je uspelo, inače opis greške. */
function telegram_send(string $chatId, string $text, ?string $token = null): ?string
{
    $token = $token ?? (string) setting('telegram_bot_token', '');
    if ($token === '' || $chatId === '') {
        return 'Telegram nije podešen.';
    }
    $res = telegram_api($token, 'sendMessage', [
        'chat_id'                  => $chatId,
        'text'                     => mb_substr($text, 0, 4000),
        'disable_web_page_preview' => 'true',
    ]);
    return $res['ok'] ?? false ? null : ($res['description'] ?? 'Nepoznata greška');
}

/** Poziv Telegram API-ja (timeout 5 s). */
function telegram_api(string $token, string $method, array $params = []): array
{
    $url = 'https://api.telegram.org/bot' . rawurlencode($token) . '/' . $method;
    try {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT        => 5,
            ]);
            $body = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
        } else {
            $body = @file_get_contents($url, false, stream_context_create(['http' => [
                'method' => 'POST', 'timeout' => 5, 'ignore_errors' => true,
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($params),
            ]]));
            $err = $body === false ? 'veza nije uspela' : '';
        }
        if ($body === false || $body === '') {
            error_log("telegram $method: $err");
            return ['ok' => false, 'description' => 'Nema veze sa Telegramom' . ($err ? " ($err)" : '')];
        }
        $json = json_decode((string) $body, true);
        if (!is_array($json)) {
            return ['ok' => false, 'description' => 'Neispravan odgovor Telegrama'];
        }
        if (empty($json['ok'])) {
            error_log("telegram $method: " . ($json['description'] ?? $body));
        }
        return $json;
    } catch (Throwable $ex) {
        error_log('telegram: ' . $ex->getMessage());
        return ['ok' => false, 'description' => $ex->getMessage()];
    }
}

/** Chat ID-jevi aktivnih korisnika sa datim ulogama (+ vlasnikov chat iz podešavanja). */
function chat_ids_for_roles(array $roles, ?int $exceptUserId = null): array
{
    if (!$roles) {
        return [];
    }
    $in = implode(',', array_fill(0, count($roles), '?'));
    $ids = [];
    foreach (q_all("SELECT id, telegram_chat_id FROM users WHERE active = 1 AND role IN ($in) AND telegram_chat_id IS NOT NULL AND telegram_chat_id <> ''", $roles) as $u) {
        if ((int) $u['id'] !== $exceptUserId) {
            $ids[] = $u['telegram_chat_id'];
        }
    }
    $owner = (string) setting('telegram_owner_chat_id', '');
    if (in_array('vlasnik', $roles, true) && $owner !== '') {
        $ids[] = $owner;
    }
    return array_values(array_unique($ids));
}

/**
 * Poruka svim korisnicima sa datim ulogama (onaj ko je izazvao događaj ne dobija poruku).
 * @param list<string> $roles
 */
function notify(array $roles, string $text, ?int $projectId = null): void
{
    try {
        $me = current_user();
        if ($projectId) {
            $text .= "\n" . url('projekat', ['id' => $projectId]);
        }
        // Onaj ko je izazvao događaj ne dobija sopstvenu poruku
        $skip = [];
        if ($me) {
            $skip[] = (string) $me['telegram_chat_id'];
            if ($me['role'] === 'vlasnik') {
                $skip[] = (string) setting('telegram_owner_chat_id', '');
            }
        }
        foreach (chat_ids_for_roles($roles) as $chat) {
            if (!in_array($chat, $skip, true)) {
                telegram_send($chat, $text);
            }
        }
    } catch (Throwable $ex) {
        error_log('notify: ' . $ex->getMessage());
    }
}

/** Poruka jednom korisniku. */
function notify_user(int $userId, string $text, ?string $link = null): void
{
    try {
        $chat = (string) q_val('SELECT telegram_chat_id FROM users WHERE id = ? AND active = 1', [$userId]);
        if ($chat === '' && q_val("SELECT role FROM users WHERE id = ?", [$userId]) === 'vlasnik') {
            $chat = (string) setting('telegram_owner_chat_id', '');
        }
        if ($chat !== '') {
            telegram_send($chat, $text . ($link ? "\n$link" : ''));
        }
    } catch (Throwable $ex) {
        error_log('notify_user: ' . $ex->getMessage());
    }
}

function project_ref(array $project): string
{
    return $project['customer_name'] . ($project['city'] ? ', ' . $project['city'] : '');
}

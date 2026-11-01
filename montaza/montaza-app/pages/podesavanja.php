<?php
declare(strict_types=1);

// Podešavanja (vlasnik): korisnici, kapacitet, broj slika, Telegram.

$errors = [];
$back = url('podesavanja');

if (is_post()) {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $chat = fn($v) => preg_match('/^-?\d{1,20}$/', trim((string) $v)) ? trim((string) $v) : null;

    switch ($action) {
        case 'settings':
            $nums = [
                'weekly_capacity'    => [1, 50, 'Montaža nedeljno'],
                'min_photos_hala'    => [0, 10, 'Slike u hali'],
                'min_photos_montaza' => [1, 20, 'Slike za montažu'],
            ];
            foreach ($nums as $k => [$min, $max, $label]) {
                $v = filter_var($_POST[$k] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
                if ($v === false) {
                    $errors[] = "$label: unesi broj od {$min} do {$max}.";
                } else {
                    setting_set($k, (string) $v);
                }
            }
            $token = trim((string) ($_POST['telegram_bot_token'] ?? ''));
            if ($token !== '') {
                if (!preg_match('/^\d+:[A-Za-z0-9_-]{30,}$/', $token)) {
                    $errors[] = 'Telegram token nije ispravan (oblik 123456:ABC…).';
                } else {
                    setting_set('telegram_bot_token', $token);
                }
            }
            if (!empty($_POST['telegram_clear'])) {
                setting_set('telegram_bot_token', '');
            }
            $owner = trim((string) ($_POST['telegram_owner_chat_id'] ?? ''));
            if ($owner !== '' && $chat($owner) === null) {
                $errors[] = 'Chat ID vlasnika mora biti broj.';
            } else {
                setting_set('telegram_owner_chat_id', $owner);
            }
            if (!$errors) {
                log_activity('settings_updated');
                flash('ok', 'Podešavanja su sačuvana.');
            }
            break;

        case 'add_user':
            $name = trim((string) ($_POST['name'] ?? ''));
            $username = mb_strtolower(trim((string) ($_POST['username'] ?? '')));
            $role = (string) ($_POST['role'] ?? '');
            $pass = (string) ($_POST['password'] ?? '');
            if ($name === '' || !preg_match('/^[a-z0-9._-]{3,50}$/', $username) || !isset(ROLE_LABELS[$role]) || mb_strlen($pass) < PASSWORD_MIN) {
                $errors[] = 'Popuni ime, korisničko ime (3–50: mala slova, brojevi, . _ -), ulogu i lozinku (min. ' . PASSWORD_MIN . ').';
            } elseif (q_val('SELECT 1 FROM users WHERE username = ?', [$username])) {
                $errors[] = 'Korisničko ime već postoji.';
            } else {
                q('INSERT INTO users (name, username, password_hash, role, telegram_chat_id) VALUES (?, ?, ?, ?, ?)',
                    [$name, $username, password_hash($pass, PASSWORD_DEFAULT), $role, $chat($_POST['telegram_chat_id'] ?? '')]);
                log_activity('user_created', null, null, ['username' => $username, 'role' => $role]);
                flash('ok', "Korisnik $name je dodat.");
            }
            break;

        case 'update_user':
            $u = q_one('SELECT * FROM users WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
            if (!$u) {
                $errors[] = 'Korisnik ne postoji.';
                break;
            }
            $self = (int) $u['id'] === (int) $user['id'];
            $name = trim((string) ($_POST['name'] ?? '')) ?: $u['name'];
            $role = $self ? $u['role'] : ((string) ($_POST['role'] ?? $u['role']));
            $active = $self ? 1 : (int) !empty($_POST['active']);
            $chatId = trim((string) ($_POST['telegram_chat_id'] ?? ''));
            if (!isset(ROLE_LABELS[$role]) || ($chatId !== '' && $chat($chatId) === null)) {
                $errors[] = 'Neispravna uloga ili chat ID.';
                break;
            }
            q('UPDATE users SET name = ?, role = ?, active = ?, telegram_chat_id = ? WHERE id = ?', [$name, $role, $active, $chatId ?: null, $u['id']]);
            $pass = (string) ($_POST['password'] ?? '');
            if ($pass !== '') {
                if (mb_strlen($pass) < PASSWORD_MIN) {
                    $errors[] = 'Nova lozinka mora imati bar ' . PASSWORD_MIN . ' znaka.';
                    break;
                }
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
                if (!$self) {
                    q('DELETE FROM auth_tokens WHERE user_id = ?', [$u['id']]); // odjavi ga sa svih telefona
                }
                q('DELETE FROM login_attempts WHERE username = ?', [$u['username']]); // otključaj nalog
            }
            if (!$active) {
                q('DELETE FROM auth_tokens WHERE user_id = ?', [$u['id']]);
            }
            log_activity('user_updated', null, null, ['user_id' => (int) $u['id'], 'active' => $active, 'password_changed' => $pass !== '']);
            flash('ok', 'Korisnik ' . $name . ' je sačuvan.');
            break;

        case 'unlock':
            q('DELETE FROM login_attempts WHERE username = ?', [(string) ($_POST['username'] ?? '')]);
            flash('ok', 'Nalog je otključan.');
            break;

        case 'test_telegram':
            $to = trim((string) ($_POST['chat_id'] ?? ''));
            $err = telegram_send($to, 'Montaža — test poruka. Obaveštenja rade. ' . date('j.n. H:i'));
            flash($err ? 'warn' : 'ok', $err ? 'Telegram: ' . $err : 'Test poruka je poslata.');
            break;
    }
    if (!$errors) {
        redirect($back);
    }
}

// Poslednji chatovi bota (za pronalaženje chat ID-ja) — samo na zahtev
$updates = null;
$updatesError = null;
if (!empty($_GET['chats']) && setting('telegram_bot_token')) {
    $res = telegram_api((string) setting('telegram_bot_token'), 'getUpdates', ['limit' => 50]);
    if (!empty($res['ok'])) {
        $updates = [];
        foreach ($res['result'] as $upd) {
            $c = $upd['message']['chat'] ?? $upd['my_chat_member']['chat'] ?? null;
            if ($c) {
                $updates[$c['id']] = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '') . ' ' . ($c['title'] ?? '')) . (isset($c['username']) ? ' @' . $c['username'] : '');
            }
        }
    } else {
        $updatesError = $res['description'] ?? 'greška';
    }
}

$users = q_all("SELECT u.*, (SELECT COUNT(*) FROM login_attempts a WHERE a.username = u.username AND a.created_at > NOW() - INTERVAL 1 DAY) AS fails,
                       (SELECT COUNT(*) FROM push_subscriptions s WHERE s.user_id = u.id) AS phones
                FROM users u ORDER BY active DESC, FIELD(role, 'vlasnik', 'hala', 'teren'), name");

view('podesavanja', [
    'title'        => 'Podešavanja',
    'tab'          => 'podesavanja',
    'user'         => $user,
    'users'        => $users,
    'errors'       => $errors,
    'updates'      => $updates,
    'updatesError' => $updatesError,
]);

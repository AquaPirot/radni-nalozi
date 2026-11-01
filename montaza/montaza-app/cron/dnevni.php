<?php
declare(strict_types=1);

// Dnevni poslovi. cPanel → Cron Jobs (zameni KORISNIK svojim nalogom):
//   jutro (7:05):  5 7 * * *   /usr/local/bin/php /home/KORISNIK/montaza.aggroup.rs/montaza-app/cron/dnevni.php
//   veče (18:05):  5 18 * * *  /usr/local/bin/php /home/KORISNIK/montaza.aggroup.rs/montaza-app/cron/dnevni.php --vece
//
// Jutro: današnje montaže svima · ponedeljkom montaže ove nedelje · čišćenje neposlatih slika · rezervna kopija baze.
// Veče:  sutrašnje montaže svima; ako proizvod nije gotov — upozorenje.
// Probni rad bez slanja: php dnevni.php --dry-run   (ili --vece --dry-run). Isti dan se ne šalje dvaput (osim --force).

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

$args = $argv ?? [];
$dry = in_array('--dry-run', $args, true);
$evening = in_array('--vece', $args, true);
$force = in_array('--force', $args, true);
$todayS = today()->format('Y-m-d');
$runKey = $evening ? 'cron_last_evening' : 'cron_last_morning';
if (!$force && !$dry && setting($runKey) === $todayS) {
    echo "Već pokrenuto danas.\n";
    exit;
}
$sent = 0;

/** Kratko na telefon, duže na Telegram (ako je povezan). */
function cron_send(array $u, string $title, string $short, string $long, string $link, bool $dry, int &$sent): void
{
    if ($dry) {
        echo "── {$u['name']}: $title\n   push: $short\n" . preg_replace('/^/m', '   ', $long) . "\n";
        return;
    }
    try {
        $sent += push_to_user((int) $u['id'], $title, $short, $link) > 0 ? 1 : 0;
        $chat = (string) $u['telegram_chat_id'];
        if ($chat === '' && $u['role'] === 'vlasnik') {
            $chat = (string) setting('telegram_owner_chat_id', '');
        }
        if ($chat !== '' && !telegram_send($chat, $title . "\n\n" . $long . "\n\n" . $link)) {
            $sent++;
        }
    } catch (Throwable $ex) {
        error_log('cron: ' . $ex->getMessage());
    }
}

/** Aktivni projekti sa montažom u periodu, kojima terenska stavka nije završena. */
function installs_between(string $from, string $to): array
{
    return bundle(q_all("SELECT p.* FROM projects p WHERE p.status = 'aktivan' AND p.install_date BETWEEN ? AND ?
                         AND EXISTS (SELECT 1 FROM project_items i WHERE i.project_id = p.id AND i.kind IN ('montaza','servis','dijagnoza') AND i.status <> 'zavrseno')
                         ORDER BY p.install_date, p.install_time IS NULL, p.install_time", [$from, $to]));
}

function install_line(array $b, bool $withDay = false): string
{
    $p = $b['p'];
    $ready = ready_for_field($b['items']) ? '' : ' — NIJE SPREMNO (' . mb_strtolower(item_label(current_item($b['items']) ?? ['kind' => ''])) . ')';
    return ($withDay ? install_when($p, false) : ($p['install_time'] ? substr((string) $p['install_time'], 0, 5) : 'ceo dan')) . ' · '
        . project_ref($p) . ($p['address'] ? ', ' . $p['address'] : '') . ($p['phone'] ? ' · ' . $p['phone'] : '') . $ready;
}

$users = q_all('SELECT * FROM users WHERE active = 1');
$link = url('montaze');

if ($evening) {
    $tomorrow = today()->modify('+1 day')->format('Y-m-d');
    $list = installs_between($tomorrow, $tomorrow);
    if ($list) {
        $notReady = array_filter($list, fn($b) => !ready_for_field($b['items']));
        $title = 'Sutra montaža: ' . count($list) . ($notReady ? ' · nije spremno: ' . count($notReady) : '');
        $long = '• ' . implode("\n• ", array_map('install_line', $list));
        foreach ($users as $u) {
            cron_send($u, $title, implode(' · ', array_map(fn($b) => $b['p']['customer_name'], $list)), $long, $link, $dry, $sent);
        }
    }
    if (!$dry) {
        setting_set($runKey, $todayS);
    }
    echo "Veče: poslato $sent\n";
    exit;
}

// Jutro: danas (+ ponedeljkom cela nedelja)
$monday = (int) date('N') === 1 || in_array('--nedelja', $args, true);
$list = installs_between($todayS, $todayS);
if ($list) {
    $long = '• ' . implode("\n• ", array_map('install_line', $list));
    foreach ($users as $u) {
        cron_send($u, 'Danas montaža: ' . count($list), implode(' · ', array_map(fn($b) => $b['p']['customer_name'], $list)), $long, $link, $dry, $sent);
    }
}
if ($monday) {
    $mon = week_monday(today());
    $week = installs_between($mon->format('Y-m-d'), $mon->modify('+6 days')->format('Y-m-d'));
    $noDate = (int) q_val("SELECT COUNT(*) FROM projects p WHERE p.status = 'aktivan' AND p.install_date IS NULL");
    $stops = (int) q_val("SELECT COUNT(*) FROM stoppages s JOIN projects p ON p.id = s.project_id WHERE s.resolved_at IS NULL AND p.status = 'aktivan'");
    $long = 'MONTAŽE OVE NEDELJE (' . count($week) . "):\n" . ($week ? '• ' . implode("\n• ", array_map(fn($b) => install_line($b, true), $week)) : '• nema')
        . "\n\nBez datuma montaže: $noDate · Otvoreni zastoji: $stops";
    foreach ($users as $u) {
        cron_send($u, 'Nedelja ' . week_label($mon), 'Montaža: ' . count($week) . ' · bez datuma: ' . $noDate . ' · zastoji: ' . $stops, $long, $link, $dry, $sent);
    }
}

if (!$dry) {
    $cleaned = cleanup_pending_files();
    $backup = backup_database();
    setting_set($runKey, $todayS);
    echo "Jutro: poslato $sent · obrisano neposlatih slika: $cleaned · kopija: " . ($backup ?? 'GREŠKA (vidi log)') . "\n";
} else {
    echo "(probni rad — ništa nije poslato ni obrisano)\n";
}

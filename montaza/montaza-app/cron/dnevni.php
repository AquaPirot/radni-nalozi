<?php
declare(strict_types=1);

// Dnevni poslovi. cPanel → Cron Jobs (zameni KORISNIK svojim nalogom):
//   jutro (7:05):  5 7 * * *   /usr/local/bin/php /home/KORISNIK/montaza-app/cron/dnevni.php
//   veče (18:05):  5 18 * * *  /usr/local/bin/php /home/KORISNIK/montaza-app/cron/dnevni.php --vece
//
// Jutro:  današnji termini ekipama · rokovi (zadaci, faze, projekti) · ponedeljkom pregled nedelje vlasniku
//         · čišćenje neposlatih slika · rezervna kopija baze (čuva poslednjih 14).
// Veče:   sutrašnji termini ekipama i hali (šta treba da bude spremno).
// Probni rad bez slanja:  php dnevni.php --dry-run   (ili --vece --dry-run)
// Ponovno pokretanje istog dana ne šalje ponovo (osim sa --force).

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
$tomorrowS = today()->modify('+1 day')->format('Y-m-d');
$sent = 0;

/** Kratko na telefon (push), duže na Telegram ako je povezan. */
function cron_send(array $u, string $title, string $short, string $long, ?string $link, bool $dry, int &$sent): void
{
    if ($dry) {
        echo "── {$u['name']}: $title\n   push: $short\n" . ($long !== $short ? preg_replace('/^/m', '   ', $long) . "\n" : '');
        return;
    }
    try {
        $sent += push_to_user((int) $u['id'], $title, $short, $link) > 0 ? 1 : 0;
        $chat = (string) $u['telegram_chat_id'];
        if ($chat === '' && $u['role'] === 'vlasnik') {
            $chat = (string) setting('telegram_owner_chat_id', '');
        }
        if ($chat !== '' && !telegram_send($chat, $title . "\n\n" . $long . ($link ? "\n\n$link" : ''))) {
            $sent++;
        }
    } catch (Throwable $ex) {
        error_log('cron: ' . $ex->getMessage());
    }
}

function visit_line(array $v): string
{
    return ($v['start_time'] ? substr($v['start_time'], 0, 5) . ' ' : '') . $v['customer_name'] . ($v['city'] ? ', ' . $v['city'] : '')
        . ' — ' . mb_strtolower(VISIT_KINDS[$v['kind']]) . ((int) $v['days'] > 1 ? ' (' . $v['days'] . ' d)' : '') . ($v['confirmed'] ? '' : ' · okvirno');
}

$users = q_all('SELECT * FROM users WHERE active = 1');
$byId = array_column($users, null, 'id');
$runKey = $evening ? 'cron_last_evening' : 'cron_last_morning';
if (!$force && !$dry && setting($runKey) === $todayS) {
    echo "Već pokrenuto danas.\n";
    exit;
}

// ── Termini po ekipi (danas ujutru / sutra uveče) ──
$day = $evening ? $tomorrowS : $todayS;
$dayVisits = visits_between($day, $day);
$byLeader = [];
foreach ($dayVisits as $v) {
    $leader = (int) ($v['leader_id'] ?: 0);
    $byLeader[$leader][] = $v;
}
foreach ($users as $u) {
    if ($u['role'] !== 'teren') {
        continue;
    }
    $mine = array_merge($byLeader[(int) $u['id']] ?? [], $byLeader[0] ?? []);
    if (!$mine) {
        continue;
    }
    $lines = array_map('visit_line', $mine);
    $title = ($evening ? 'Sutra' : 'Danas') . ' na terenu: ' . count($mine);
    cron_send($u, $title, implode(' · ', array_map(fn($v) => $v['customer_name'], $mine)), '• ' . implode("\n• ", $lines), url('danas'), $dry, $sent);
}

if ($evening) {
    // Hali: sutrašnje montaže čiji proizvod nije gotov
    $pids = array_values(array_unique(array_map('intval', array_column($dayVisits, 'pid'))));
    $bundles = $pids ? bundle(q_all('SELECT * FROM projects WHERE id IN (' . in_list($pids) . ')', $pids)) : [];
    $notReady = [];
    foreach ($bundles as $b) {
        if ($b['phases'] && !phases_done($b['phases'])) {
            $left = array_filter($b['phases'], fn($ph) => $ph['status'] !== 'zavrseno');
            $notReady[] = project_ref($b['p']) . ' — fali: ' . implode(', ', array_unique(array_column($left, 'name')));
        }
    }
    if ($notReady) {
        foreach ($users as $u) {
            if (in_array($u['role'], ['hala', 'vlasnik'], true)) {
                cron_send($u, 'Sutra montaža, a proizvod nije gotov (' . count($notReady) . ')', $notReady[0], '• ' . implode("\n• ", $notReady), url('danas'), $dry, $sent);
            }
        }
    }
    if (!$dry) {
        setting_set($runKey, $todayS);
    }
    echo "Veče: poslato $sent\n";
    exit;
}

// ── Rokovi: zadaci (danas / kasni), faze, projekti ──
foreach ($users as $u) {
    $tasks = q_all(
        "SELECT * FROM tasks WHERE assigned_to = ? AND status <> 'zavrseno' AND archived_at IS NULL AND due_date IS NOT NULL AND due_date <= ?
         ORDER BY due_date, due_time IS NULL, due_time",
        [$u['id'], $todayS]
    );
    $phases = q_all(
        "SELECT ph.*, p.customer_name, p.city FROM production_phases ph JOIN projects p ON p.id = ph.project_id
         WHERE ph.assignee_id = ? AND ph.status <> 'zavrseno' AND ph.due_date IS NOT NULL AND ph.due_date <= ?
           AND p.status = 'aktivan' AND p.archived_at IS NULL ORDER BY ph.due_date",
        [$u['id'], $todayS]
    );
    if (!$tasks && !$phases) {
        continue;
    }
    $lines = [];
    foreach ($tasks as $t) {
        $late = days_late($t['due_date']);
        $lines[] = ($late ? "KASNI $late d · " : 'danas' . ($t['due_time'] ? ' ' . substr($t['due_time'], 0, 5) : '') . ' · ') . $t['title'];
    }
    foreach ($phases as $ph) {
        $late = days_late($ph['due_date']);
        $lines[] = ($late ? "KASNI $late d · " : 'danas · ') . $ph['name'] . ' — ' . $ph['customer_name'];
    }
    cron_send($u, 'Rokovi: ' . count($lines), $lines[0] . (count($lines) > 1 ? ' (+' . (count($lines) - 1) . ')' : ''), '• ' . implode("\n• ", $lines), url('zadaci'), $dry, $sent);
}

// Projekti čiji rok ističe za 3 dana ili danas, a nisu završeni → vlasnicima
$soon = q_all(
    "SELECT * FROM projects WHERE status = 'aktivan' AND archived_at IS NULL AND deadline IN (?, ?) ORDER BY deadline",
    [$todayS, today()->modify('+3 days')->format('Y-m-d')]
);
if ($soon) {
    $lines = array_map(fn($p) => project_ref($p) . ' — rok ' . ($p['deadline'] === $todayS ? 'DANAS' : fmt_dm($p['deadline'])), $soon);
    foreach ($users as $u) {
        if ($u['role'] === 'vlasnik') {
            cron_send($u, 'Rok se bliži: ' . count($soon), $lines[0], '• ' . implode("\n• ", $lines), url('projekti'), $dry, $sent);
        }
    }
}

// ── Ponedeljak: pregled nedelje vlasniku ──
if ((int) date('N') === 1 || in_array('--nedelja', $args, true)) {
    $mon = (int) date('N') >= 6 ? week_monday(today()->modify('+7 days')) : week_monday(today());
    $week = visits_between($mon->format('Y-m-d'), $mon->modify('+6 days')->format('Y-m-d'));
    foreach ($users as $u) {
        if ($u['role'] !== 'vlasnik') {
            continue;
        }
        $decisions = owner_decisions($u);
        $stops = open_stops_list($u);
        $late = late_list($u);
        [, $due] = due_payments();
        $cash = (float) q_val("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE method = 'gotovina' AND handed_at IS NULL");
        $msg = 'TERMINI (' . count($week) . "):\n" . ($week ? '• ' . implode("\n• ", array_map(fn($v) => fmt_dm($v['start_date']) . ' ' . visit_line($v), $week)) : '• nema') . "\n";
        foreach (['TRAŽI ODLUKU' => $decisions, 'ZASTOJI' => $stops, 'KASNI' => $late] as $h => $list) {
            if ($list) {
                $msg .= "\n$h (" . count($list) . "):\n• " . implode("\n• ", array_map(fn($x) => $x['title'] . ' — ' . $x['why'], array_slice($list, 0, 8))) . "\n";
            }
        }
        $msg .= "\nZa naplatu: " . fmt_money((string) $due) . ($cash > 0 ? ' · gotovina kod radnika: ' . fmt_money((string) $cash) : '');
        $short = 'Termini: ' . count($week) . ' · Odluke: ' . count($decisions) . ' · Zastoji: ' . count($stops) . ' · Kasni: ' . count($late);
        cron_send($u, 'Pregled nedelje — ' . week_label($mon), $short, $msg, url('danas'), $dry, $sent);
    }
}

// ── Održavanje ──
if (!$dry) {
    $cleaned = cleanup_pending_files();
    $backup = backup_database();
    setting_set($runKey, $todayS);
    echo "Jutro: poslato $sent · obrisano neposlatih slika: $cleaned · kopija: " . ($backup ?? 'GREŠKA (vidi log)') . "\n";
} else {
    echo "(probni rad — ništa nije poslato ni obrisano)\n";
}

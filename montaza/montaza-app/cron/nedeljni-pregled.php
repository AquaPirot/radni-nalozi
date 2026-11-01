<?php
declare(strict_types=1);

// Ponedeljni pregled nedelje preko Telegrama (spec tačka 9).
// cPanel → Cron Jobs, ponedeljkom u 7:00:
//   0 7 * * 1  /usr/local/bin/php /home/KORISNIK/montaza-app/cron/nedeljni-pregled.php
// Probni rad bez slanja:  php nedeljni-pregled.php --dry-run

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
define('PUBLIC_DIR', dirname(__DIR__)); // nije potreban za ovaj posao
require dirname(__DIR__) . '/bootstrap.php';

$dry = in_array('--dry-run', $argv, true);
$mon = week_monday(today());
$monS = $mon->format('Y-m-d');

$week = q_all("SELECT * FROM projects WHERE status = 'aktivan' AND install_week_start = ? ORDER BY deadline IS NULL, deadline", [$monS]);
$late = q_all("SELECT * FROM projects WHERE status = 'aktivan' AND deadline < CURDATE() ORDER BY deadline");
$stops = q_all(
    "SELECT s.*, p.customer_name, p.city, t.title FROM stoppages s
     LEFT JOIN projects p ON p.id = s.project_id LEFT JOIN tasks t ON t.id = s.task_id
     WHERE s.resolved_at IS NULL ORDER BY s.created_at"
);
$unscheduled = (int) q_val("SELECT COUNT(*) FROM projects WHERE status = 'aktivan' AND install_week_start IS NULL");
$capacity = (int) setting('weekly_capacity', '5');
$itemsBy = load_items_for(array_column($week, 'id'));
$stopBy = open_stoppages_for(array_column($week, 'id'));

$base = 'PREGLED — nedelja ' . (int) $mon->format('W') . ' (' . week_range_label($mon) . ")\n\n";
$base .= 'MONTAŽE OVE NEDELJE (' . count($week) . ' / ' . $capacity . "):\n";
foreach ($week as $p) {
    [$line] = project_status_line($p, $itemsBy[$p['id']] ?? [], $stopBy[$p['id']] ?? null);
    $base .= '• ' . project_ref($p) . ' — ' . $line . "\n";
}
if (!$week) {
    $base .= "• nema\n";
}
if ($late) {
    $base .= "\nKASNI (" . count($late) . "):\n";
    foreach ($late as $p) {
        $base .= '• ' . project_ref($p) . ' — rok ' . fmt_dm($p['deadline']) . ' (' . days_late($p['deadline']) . " d)\n";
    }
}
if ($stops) {
    $base .= "\nOTVORENI ZASTOJI (" . count($stops) . "):\n";
    foreach ($stops as $s) {
        $what = $s['title'] ? 'zadatak „' . $s['title'] . '"' : $s['customer_name'] . ($s['city'] ? ', ' . $s['city'] : '');
        $base .= '• ' . $what . ' — ' . REASON_LABELS[$s['reason']] . ($s['note'] ? ' (' . $s['note'] . ')' : '') . "\n";
    }
}

$sent = 0;
foreach (q_all("SELECT * FROM users WHERE active = 1") as $u) {
    $chat = (string) $u['telegram_chat_id'];
    if ($chat === '' && $u['role'] === 'vlasnik') {
        $chat = (string) setting('telegram_owner_chat_id', '');
    }
    if ($chat === '') {
        continue;
    }
    $msg = $base;
    if ($u['role'] === 'vlasnik' && $unscheduled) {
        $msg .= "\nNije raspoređeno: $unscheduled\n";
    }
    $tasks = q_all(
        "SELECT * FROM tasks WHERE assigned_to = ? AND status <> 'zavrseno'
         ORDER BY priority = 'hitno' DESC, due_date IS NULL, due_date LIMIT 10",
        [$u['id']]
    );
    if ($tasks) {
        $msg .= "\nTVOJI ZADACI (" . count($tasks) . "):\n";
        foreach ($tasks as $t) {
            $msg .= '• ' . ($t['priority'] === 'hitno' ? 'HITNO ' : '') . $t['title'] . ($t['due_date'] ? ' — rok ' . fmt_dm($t['due_date']) : '') . "\n";
        }
    }
    $msg .= "\n" . url();
    if ($dry) {
        echo "=== {$u['name']} ($chat) ===\n$msg\n\n";
        continue;
    }
    $err = telegram_send($chat, $msg);
    if ($err) {
        fwrite(STDERR, "{$u['name']}: $err\n");
    } else {
        $sent++;
    }
}
if (!$dry) {
    echo "Poslato: $sent\n";
}

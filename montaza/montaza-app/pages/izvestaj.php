<?php
declare(strict_types=1);

// Mesečni izveštaj (vlasnik) — spec ekran 6. ?m=YYYY-MM, &print=1 za štampu / PDF.

$m = (string) ($_GET['m'] ?? date('Y-m'));
$start = DateTimeImmutable::createFromFormat('!Y-m-d', $m . '-01') ?: new DateTimeImmutable('first day of this month');
$start = $start->setTime(0, 0);
$end = $start->modify('+1 month');
$S = $start->format('Y-m-d H:i:s');
$E = $end->format('Y-m-d H:i:s');
$monthLabel = MONTHS_FULL[(int) $start->format('n')] . ' ' . $start->format('Y');
$nextLabel = mb_strtolower(MONTHS_FULL[(int) $end->format('n')]);
$isCurrent = $start->format('Y-m') === date('Y-m');

// 1. Završeni projekti
$completed = q_all(
    "SELECT * FROM projects WHERE status = 'zavrsen' AND completed_at >= ? AND completed_at < ? ORDER BY completed_at",
    [$S, $E]
);
$pergole = count(array_filter($completed, fn($p) => $p['type'] !== 'reklamacija'));
$reklamacije = count($completed) - $pergole;

// 2. Prosek dana od otvaranja do završetka (samo NOVI — započeti su uneti naknadno)
$avgDays = q_val(
    "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) / 24 FROM projects
     WHERE status = 'zavrsen' AND type = 'novi' AND completed_at >= ? AND completed_at < ?",
    [$S, $E]
);

// 3–4. Zastoji (projekti i zadaci) prijavljeni u mesecu
$stops = q_all(
    "SELECT reason, TIMESTAMPDIFF(HOUR, created_at, LEAST(COALESCE(resolved_at, NOW()), ?)) / 24 AS days
     FROM stoppages WHERE created_at >= ? AND created_at < ?",
    [$E, $S, $E]
);
$lostDays = (int) round(array_sum(array_column($stops, 'days')));
$byReason = [];
foreach ($stops as $s) {
    $byReason[$s['reason']] = ($byReason[$s['reason']] ?? 0) + 1;
}
arsort($byReason);

// 5. Najsporija stavka (prosek dana od početka do završetka)
$itemSpeed = q_all(
    "SELECT kind, AVG(TIMESTAMPDIFF(HOUR, started_at, completed_at)) / 24 AS days, COUNT(*) AS n
     FROM project_items WHERE status = 'zavrseno' AND imported = 0 AND started_at IS NOT NULL
       AND completed_at >= ? AND completed_at < ?
     GROUP BY kind ORDER BY days DESC",
    [$S, $E]
);

// 6. Projekti koji prelaze u sledeći mesec (otvoreni na kraju meseca)
$carry = q_all(
    "SELECT * FROM projects WHERE status <> 'otkazan' AND created_at < ?
       AND (completed_at IS NULL OR completed_at >= ?) AND (status = 'aktivan' OR completed_at IS NOT NULL)
     ORDER BY deadline IS NULL, deadline",
    [$E, $E]
);
$carryLate = count(array_filter($carry, fn($p) => $p['deadline'] && $p['deadline'] < $end->format('Y-m-d') && ($isCurrent ? days_late($p['deadline']) > 0 : true)));

// 7. Naplaćeno
$paid = q_one(
    "SELECT COALESCE(SUM(payment_received), 0) AS total, COUNT(*) AS n,
            SUM(payment_received <> payment_amount) AS diffs
     FROM projects WHERE payment_confirmed_at >= ? AND payment_confirmed_at < ?",
    [$S, $E]
);

// 8. Zadaci van projekata
$tasks = q_one(
    "SELECT SUM(status = 'zavrseno' AND completed_at >= ? AND completed_at < ?) AS done,
            SUM(status = 'zavrseno' AND completed_at >= ? AND completed_at < ? AND due_date IS NOT NULL AND DATE(completed_at) > due_date) AS done_late,
            SUM(status <> 'zavrseno' AND due_date IS NOT NULL AND due_date < LEAST(CURDATE(), ?)) AS open_late
     FROM tasks",
    [$S, $E, $S, $E, $end->format('Y-m-d')]
);

$data = compact('start', 'monthLabel', 'nextLabel', 'isCurrent', 'completed', 'pergole', 'reklamacije', 'avgDays',
    'stops', 'lostDays', 'byReason', 'itemSpeed', 'carry', 'carryLate', 'paid', 'tasks');

if (!empty($_GET['print'])) {
    $ids = array_column($completed, 'id');
    $photos = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (q_all("SELECT id, project_id, kind FROM files WHERE kind IN ('lokacija_pre','posle') AND project_id IN ($in) ORDER BY id", $ids) as $f) {
            $photos[$f['project_id']][$f['kind']] ??= $f;
        }
    }
    view('izvestaj_print', $data + ['title' => 'Izveštaj — ' . $monthLabel, 'photos' => $photos], false);
    return;
}

view('izvestaj', $data + [
    'title' => 'Izveštaj',
    'tab'   => 'izvestaj',
    'user'  => $user,
    'prevM' => $start->modify('-1 month')->format('Y-m'),
    'nextM' => $end->format('Y-m'),
    'm'     => $start->format('Y-m'),
]);

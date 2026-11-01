<?php
declare(strict_types=1);

// Plan: naredne 4 nedelje, kapacitet, "nije raspoređeno" (spec ekran 2).

$capacity = max(1, (int) setting('weekly_capacity', '5'));
$mon = week_monday(today());
$weeks = [];
for ($i = 0; $i < 4; $i++) {
    $w = $mon->modify("+$i weeks");
    $weeks[$w->format('Y-m-d')] = ['mon' => $w, 'projects' => []];
}
$last = array_key_last($weeks);

$rows = q_all(
    "SELECT * FROM projects WHERE status <> 'otkazan' AND (
        (install_week_start BETWEEN ? AND ?)
        OR (status = 'aktivan' AND (install_week_start IS NULL OR install_week_start < ?))
     ) ORDER BY install_week_start, deadline IS NULL, deadline, customer_name",
    [$mon->format('Y-m-d'), $last, $mon->format('Y-m-d')]
);
$ids = array_column($rows, 'id');
$itemsBy = load_items_for($ids);
$stopBy = open_stoppages_for($ids);

$unscheduled = [];
$overdue = [];
foreach ($rows as $r) {
    if ($r['install_week_start'] === null) {
        $unscheduled[] = $r;
    } elseif ($r['install_week_start'] < $mon->format('Y-m-d')) {
        $overdue[] = $r;
    } else {
        $weeks[$r['install_week_start']]['projects'][] = $r;
    }
}

view('plan', [
    'title'       => 'Plan',
    'eyebrow'     => 'NAREDNE 4 NEDELJE · PON–PET',
    'tab'         => 'plan',
    'user'        => $user,
    'capacity'    => $capacity,
    'weeks'       => $weeks,
    'unscheduled' => $unscheduled,
    'overdue'     => $overdue,
    'itemsBy'     => $itemsBy,
    'stopBy'      => $stopBy,
]);

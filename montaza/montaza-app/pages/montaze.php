<?php
declare(strict_types=1);

// Montaže: gde i kad — po danima, sa telefonom, adresom, navigacijom i nalogom.

$rows = q_all("SELECT p.* FROM projects p WHERE p.status = 'aktivan'
               AND EXISTS (SELECT 1 FROM project_items i WHERE i.project_id = p.id AND i.kind IN ('montaza','servis','dijagnoza') AND i.status <> 'zavrseno')
               ORDER BY p.install_date IS NULL, p.install_date, p.install_time IS NULL, p.install_time, p.customer_name");
$days = [];
$noDate = [];
$todayS = today()->format('Y-m-d');
foreach (bundle($rows) as $b) {
    $d = $b['p']['install_date'];
    if (!$d) {
        $noDate[] = $b;
        continue;
    }
    $key = $d < $todayS ? '0000' : $d;
    $days[$key][] = $b;
}

view('montaze', [
    'title'  => 'Montaže',
    'tab'    => 'montaze',
    'user'   => $user,
    'days'   => $days,
    'noDate' => $noDate,
]);

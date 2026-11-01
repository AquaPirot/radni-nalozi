<?php
declare(strict_types=1);

// "Danas": vlasniku komandna tabla, montažeru "Moj današnji posao", hali nalozi i faze na redu.

$role = $user['role'];
$uid = (int) $user['id'];
$todayS = today()->format('Y-m-d');
$ahead = today()->modify('+' . (int) setting('plan_days_ahead', '7') . ' days')->format('Y-m-d');
$data = ['user' => $user, 'role' => $role];

// Moji zadaci (svi)
$data['myTasks'] = q_all(
    "SELECT t.*, a.name AS assigned_name, cb.name AS created_name, NULL AS completed_name FROM tasks t
     JOIN users a ON a.id = t.assigned_to LEFT JOIN users cb ON cb.id = t.created_by
     WHERE t.assigned_to = ? AND t.status <> 'zavrseno' AND t.archived_at IS NULL
     ORDER BY t.status = 'zastoj' DESC, t.priority = 'hitno' DESC, t.due_date IS NULL, t.due_date LIMIT 8",
    [$uid]
);
$data['given'] = q_all(
    "SELECT t.*, a.name AS assigned_name, NULL AS completed_name FROM tasks t JOIN users a ON a.id = t.assigned_to
     WHERE t.created_by = ? AND t.assigned_to <> ? AND t.status <> 'zavrseno' AND t.archived_at IS NULL
     ORDER BY t.accepted_at IS NOT NULL, t.due_date IS NULL, t.due_date LIMIT 8",
    [$uid, $uid]
);

if ($role === 'vlasnik') {
    $data['decisions'] = owner_decisions($user);
    $data['stops'] = open_stops_list($user);
    $data['late'] = late_list($user);
    [$data['payList'], $data['paySum']] = due_payments();
    $data['today'] = visits_between($todayS, $todayS);
    $data['next'] = array_values(array_filter(visits_between(today()->modify('+1 day')->format('Y-m-d'), $ahead), fn($v) => $v['start_date'] > $todayS));
} elseif ($role === 'teren') {
    $data['today'] = visits_between($todayS, $todayS);
    $data['next'] = array_values(array_filter(visits_between(today()->modify('+1 day')->format('Y-m-d'), $ahead), fn($v) => $v['start_date'] > $todayS));
    $data['stops'] = open_stops_list($user);
} else { // hala
    $data['orders'] = q_all(
        "SELECT o.*, p.customer_name, p.city FROM project_orders o JOIN projects p ON p.id = o.project_id
         WHERE o.accepted_at IS NULL AND p.status = 'aktivan' AND p.archived_at IS NULL
           AND o.version = (SELECT MAX(version) FROM project_orders o2 WHERE o2.project_id = o.project_id)
         ORDER BY o.issued_at"
    );
    // Faze na redu: prva nezavršena faza svakog sistema, uz preuzet nalog; sortirano po terminu montaže
    $rows = q_all(
        "SELECT ph.*, p.customer_name, p.city, s.qty, s.width, s.length, s.height, t.name AS type_name,
                (SELECT MIN(v.start_date) FROM visits v WHERE v.project_id = p.id AND v.status = 'planiran' AND v.start_date IS NOT NULL) AS next_date
         FROM production_phases ph
         JOIN projects p ON p.id = ph.project_id JOIN project_systems s ON s.id = ph.system_id LEFT JOIN system_types t ON t.id = s.system_type_id
         WHERE p.status = 'aktivan' AND p.archived_at IS NULL AND ph.status <> 'zavrseno'
           AND ph.sort = (SELECT MIN(x.sort) FROM production_phases x WHERE x.system_id = ph.system_id AND x.status <> 'zavrseno')
           AND EXISTS (SELECT 1 FROM project_orders o WHERE o.project_id = p.id AND o.accepted_at IS NOT NULL
                       AND o.version = (SELECT MAX(version) FROM project_orders o2 WHERE o2.project_id = p.id))
         ORDER BY ph.status = 'zastoj' DESC, ph.status = 'u_toku' DESC, next_date IS NULL, next_date, ph.due_date IS NULL, ph.due_date"
    );
    $data['phases'] = $rows;
    $data['stops'] = open_stops_list($user);
}

$hour = (int) date('G');
view('danas', $data + [
    'title'   => 'Danas',
    'eyebrow' => mb_strtoupper(DAYS_FULL[(int) date('w')] . ', ' . date('j') . '. ' . MONTHS_FULL[(int) date('n')]) . ' · ' . mb_strtoupper(($hour < 12 ? 'Dobro jutro' : ($hour < 18 ? 'Dobar dan' : 'Dobro veče')) . ', ' . $user['name']),
    'tab'     => 'danas',
]);

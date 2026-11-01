<?php
declare(strict_types=1);

// Početni ekran "Danas": šta je NA MENI — zadaci, stavke projekata, zastoji, kašnjenja.

$uid = (int) $user['id'];
$role = $user['role'];
$isOwner = $role === 'vlasnik';
$mon = week_monday(today());
$nextMon = $mon->modify('+1 week')->format('Y-m-d');

// 1. Moji otvoreni zadaci
$myTasks = q_all(
    "SELECT t.*, a.name AS assigned_name, cb.name AS created_name, NULL AS completed_name, p.customer_name AS project_name
     FROM tasks t JOIN users a ON a.id = t.assigned_to LEFT JOIN users cb ON cb.id = t.created_by LEFT JOIN projects p ON p.id = t.project_id
     WHERE t.assigned_to = ? AND t.status <> 'zavrseno'
     ORDER BY t.status = 'zastoj' DESC, t.priority = 'hitno' DESC, t.due_date IS NULL, t.due_date, t.created_at
     LIMIT 8",
    [$uid]
);
$myTasksTotal = my_open_tasks($uid);

// 2. Stavke projekata koje čekaju mene (moja uloga)
$itemWhere = "p.status = 'aktivan' AND i.status <> 'zavrseno' AND i.responsible = ?";
$params = [$role];
if ($role === 'teren') {
    // teren: ova i sledeća nedelja, prošle nedelje, i reklamacije bez nedelje
    $itemWhere .= " AND (p.install_week_start <= ? OR (p.install_week_start IS NULL AND p.type = 'reklamacija'))";
    $params[] = $nextMon;
}
$myItems = q_all(
    "SELECT i.*, p.customer_name, p.city, p.install_week_start, p.deadline, p.type, p.payment_when, p.payment_confirmed_at
     FROM project_items i JOIN projects p ON p.id = i.project_id
     WHERE $itemWhere
     ORDER BY i.status = 'zastoj' DESC, i.status = 'u_toku' DESC, p.install_week_start IS NULL, p.install_week_start,
              COALESCE(i.due_date, p.deadline) IS NULL, COALESCE(i.due_date, p.deadline), i.sort
     LIMIT 60",
    $params
);
// Hala: samo stavke koje su "na redu" (prethodne stavke hale završene ili paralelne) — prikazujemo sve otvorene, sortirano.
// Teren: montaža je "spremna" kad je pakovanje gotovo.
$readyIds = [];
if ($myItems) {
    $pids = array_unique(array_column($myItems, 'project_id'));
    $all = load_items_for($pids);
    foreach ($myItems as $it) {
        $items = $all[$it['project_id']] ?? [];
        $cur = current_item($items);
        if ($cur && (int) $cur['id'] === (int) $it['id']) {
            $readyIds[$it['id']] = true;
        }
    }
}
// Prikaži samo ono što može da se radi sada; ostalo samo prebroj
$waitingCount = 0;
$myItems = array_values(array_filter($myItems, function ($it) use ($readyIds, &$waitingCount) {
    $now = isset($readyIds[$it['id']]) || in_array($it['status'], ['u_toku', 'zastoj'], true);
    if (!$now) {
        $waitingCount++;
    }
    return $now;
}));
$myItems = array_slice($myItems, 0, 15);

// 3. Zastoji (vlasnik: svi; ostali: na njihovim stavkama/zadacima)
[$vis, $vp] = visibility_sql($user);
$stops = q_all(
    "SELECT s.*, p.customer_name, p.city, t.title AS task_title, i.kind AS item_kind
     FROM stoppages s
     LEFT JOIN projects p ON p.id = s.project_id
     LEFT JOIN project_items i ON i.id = s.item_id
     LEFT JOIN tasks t ON t.id = s.task_id
     WHERE s.resolved_at IS NULL" . ($isOwner ? '' : ' AND (i.responsible = ? OR t.assigned_to = ? OR t.created_by = ?)') . "
     ORDER BY s.created_at",
    $isOwner ? [] : [$role, $uid, $uid]
);

// 4. Vlasnik: šta kasni, nije raspoređeno, zadaci koje je dao drugima
$late = $isOwner ? q_all("SELECT * FROM projects WHERE status = 'aktivan' AND deadline < CURDATE() ORDER BY deadline LIMIT 10") : [];
$unscheduled = $isOwner ? (int) q_val("SELECT COUNT(*) FROM projects WHERE status = 'aktivan' AND install_week_start IS NULL") : 0;
$given = q_all(
    "SELECT t.*, a.name AS assigned_name, NULL AS completed_name FROM tasks t JOIN users a ON a.id = t.assigned_to
     WHERE t.created_by = ? AND t.assigned_to <> ? AND t.status <> 'zavrseno'
     ORDER BY t.status = 'zastoj' DESC, t.due_date IS NULL, t.due_date LIMIT 6",
    [$uid, $uid]
);

// 5. Montaže ove nedelje
$week = q_all(
    "SELECT p.* FROM projects p WHERE $vis AND p.status = 'aktivan' AND p.install_week_start = ? ORDER BY p.deadline IS NULL, p.deadline",
    array_merge($vp, [$mon->format('Y-m-d')])
);
$weekItems = load_items_for(array_column($week, 'id'));
$weekStops = open_stoppages_for(array_column($week, 'id'));

$days = ['nedelja', 'ponedeljak', 'utorak', 'sreda', 'četvrtak', 'petak', 'subota'];
$hour = (int) date('G');
view('danas', [
    'title'        => 'Danas',
    'eyebrow'      => mb_strtoupper($days[(int) date('w')] . ' ' . date('j.n.')) . ' · ' . mb_strtoupper(($hour < 12 ? 'Dobro jutro' : ($hour < 18 ? 'Dobar dan' : 'Dobro veče')) . ', ' . $user['name']),
    'tab'          => 'danas',
    'user'         => $user,
    'myTasks'      => $myTasks,
    'myTasksTotal' => $myTasksTotal,
    'myItems'      => $myItems,
    'readyIds'     => $readyIds,
    'waitingCount' => $waitingCount,
    'stops'        => $stops,
    'late'         => $late,
    'unscheduled'  => $unscheduled,
    'given'        => $given,
    'week'         => $week,
    'weekItems'    => $weekItems,
    'weekStops'    => $weekStops,
    'mon'          => $mon,
]);

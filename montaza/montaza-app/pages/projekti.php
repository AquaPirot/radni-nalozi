<?php
declare(strict_types=1);

// Lista projekata (?p=projekti&f=…&q=…), grupisano po nedelji sledećeg termina.

$isOwner = $user['role'] === 'vlasnik';
$filters = ['aktivni' => 'Aktivni', 'nacrti' => 'Nacrti', 'reklamacije' => 'Servis i rekl.', 'zavrseno' => 'Završeno', 'arhiva' => 'Arhiva'];
$f = isset($filters[$_GET['f'] ?? '']) ? $_GET['f'] : 'aktivni';
$search = trim((string) ($_GET['q'] ?? ''));

[$vis, $vp] = visibility_sql($user);
$conds = [
    'aktivni'     => "p.status = 'aktivan' AND p.archived_at IS NULL",
    'nacrti'      => "p.status = 'nacrt' AND p.archived_at IS NULL",
    'reklamacije' => "p.status = 'aktivan' AND p.archived_at IS NULL AND p.work_type <> 'novi'",
    'zavrseno'    => "p.status = 'zavrsen' AND p.archived_at IS NULL",
    'arhiva'      => 'p.archived_at IS NOT NULL',
];
$counts = [];
foreach ($filters as $k => $_) {
    $counts[$k] = (int) q_val("SELECT COUNT(*) FROM projects p WHERE $vis AND {$conds[$k]}", $vp);
}
$where = "$vis AND {$conds[$f]}";
$params = $vp;
if ($search !== '') {
    $where .= ' AND (p.customer_name LIKE ? OR p.city LIKE ? OR p.code LIKE ? OR p.address LIKE ?)';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    array_push($params, $like, $like, $like, $like);
}
$order = in_array($f, ['zavrseno', 'arhiva'], true) ? 'COALESCE(p.completed_at, p.archived_at) DESC' : 'p.deadline IS NULL, p.deadline, p.id';
$rows = q_all("SELECT p.* FROM projects p WHERE $where ORDER BY $order LIMIT 300", $params);
$bundles = bundle($rows);

// Grupisanje: aktivni po nedelji sledećeg termina; završeni po mesecu
$groups = [];
foreach ($bundles as $pid => $b) {
    $p = $b['p'];
    if (in_array($f, ['zavrseno', 'arhiva'], true)) {
        $d = new DateTimeImmutable((string) ($p['completed_at'] ?? $p['archived_at'] ?? $p['created_at']));
        $key = $d->format('Y-m');
        $groups[$key] ??= ['title' => mb_strtoupper(MONTHS_FULL[(int) $d->format('n')]) . ' ' . $d->format('Y'), 'sub' => '', 'items' => []];
    } else {
        $nv = next_visit($b['visits']);
        if ($nv && $nv['start_date']) {
            $mon = week_monday($nv['start_date']);
            $key = $mon->format('Y-m-d');
            $groups[$key] ??= ['title' => week_label($mon, true), 'sub' => mb_strtoupper(week_relative($mon)), 'items' => []];
        } else {
            $key = 'zz';
            $groups[$key] ??= ['title' => $f === 'nacrti' ? 'NACRTI' : 'BEZ TERMINA', 'sub' => '', 'items' => []];
        }
    }
    $groups[$key]['items'][] = $b;
}
ksort($groups);
if (in_array($f, ['zavrseno', 'arhiva'], true)) {
    krsort($groups);
}
$taskCount = [];
if ($rows) {
    [$tv, $tvp] = task_visibility_sql($user);
    $ids = array_column($rows, 'id');
    foreach (q_all("SELECT t.project_id, COUNT(*) n FROM tasks t WHERE $tv AND t.status <> 'zavrseno' AND t.project_id IN (" . in_list($ids) . ') GROUP BY t.project_id', array_merge($tvp, $ids)) as $r) {
        $taskCount[(int) $r['project_id']] = (int) $r['n'];
    }
}

view('projekti', [
    'title'     => 'Projekti',
    'tab'       => 'projekti',
    'user'      => $user,
    'f'         => $f,
    'filters'   => $filters,
    'counts'    => $counts,
    'search'    => $search,
    'groups'    => $groups,
    'taskCount' => $taskCount,
    'topExtra'  => '<button class="icon-btn" type="button" data-toggle="#search-row" aria-label="Pretraga">' . icon('search') . '</button>',
]);

<?php
declare(strict_types=1);

// Lista zadataka (?p=zadaci&f=…)

$isOwner = $user['role'] === 'vlasnik';
$filters = $isOwner
    ? ['otvoreni' => 'Otvoreni', 'moji' => 'Moji', 'zavrseno' => 'Završeno']
    : ['moji' => 'Moji', 'zadao' => 'Zadao sam', 'zavrseno' => 'Završeno'];
$f = isset($filters[$_GET['f'] ?? '']) ? $_GET['f'] : array_key_first($filters);

[$vis, $vp] = task_visibility_sql($user);
$uid = (int) $user['id'];
$conds = [
    'otvoreni' => ["t.status <> 'zavrseno'", []],
    'moji'     => ["t.status <> 'zavrseno' AND t.assigned_to = ?", [$uid]],
    'zadao'    => ["t.status <> 'zavrseno' AND t.created_by = ? AND t.assigned_to <> ?", [$uid, $uid]],
    'zavrseno' => ["t.status = 'zavrseno' AND t.completed_at > (NOW() - INTERVAL 60 DAY)", []],
];
$counts = [];
foreach ($filters as $k => $_) {
    [$c, $cp] = $conds[$k];
    $counts[$k] = (int) q_val("SELECT COUNT(*) FROM tasks t WHERE $vis AND $c", array_merge($vp, $cp));
}
[$c, $cp] = $conds[$f];
$order = $f === 'zavrseno'
    ? 't.completed_at DESC'
    : "t.status = 'zastoj' DESC, t.priority = 'hitno' DESC, t.due_date IS NULL, t.due_date, t.created_at DESC";
$tasks = q_all(
    "SELECT t.*, a.name AS assigned_name, cb.name AS created_name, d.name AS completed_name,
            p.customer_name AS project_name
     FROM tasks t
     JOIN users a ON a.id = t.assigned_to
     LEFT JOIN users cb ON cb.id = t.created_by
     LEFT JOIN users d ON d.id = t.completed_by
     LEFT JOIN projects p ON p.id = t.project_id
     WHERE $vis AND $c ORDER BY $order LIMIT 200",
    array_merge($vp, $cp)
);

view('zadaci', [
    'title'   => 'Zadaci',
    'tab'     => 'zadaci',
    'user'    => $user,
    'f'       => $f,
    'filters' => $filters,
    'counts'  => $counts,
    'tasks'   => $tasks,
]);

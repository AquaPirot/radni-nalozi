<?php
declare(strict_types=1);

$id = (int) ($_GET['id'] ?? 0);
$project = $id ? load_project($id) : null;
if (!$project || !can_view_project($user, $id)) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'projekti']);
    return;
}

$items = load_items($id);
$files = load_files($id);
$filesByItem = [];
$lokacija = null;
foreach ($files as $f) {
    if ($f['kind'] === 'lokacija_pre') {
        $lokacija = $f;
    } elseif ($f['item_id']) {
        $filesByItem[$f['item_id']][] = $f;
    }
}
$stoppage = open_stoppages_for([$id])[$id] ?? null;
$parent = $project['parent_project_id'] ? load_project((int) $project['parent_project_id']) : null;
$children = q_all("SELECT id, code, customer_name, status FROM projects WHERE parent_project_id = ? ORDER BY created_at", [$id]);

$log = $user['role'] === 'vlasnik' ? q_all(
    'SELECT a.*, u.name AS user_name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
     WHERE a.project_id = ? ORDER BY a.created_at DESC, a.id DESC LIMIT 100',
    [$id]
) : [];

view('projekat', [
    'title'       => $project['customer_name'],
    'tab'         => 'projekti',
    'hideTop'     => true,
    'user'        => $user,
    'project'     => $project,
    'items'       => $items,
    'filesByItem' => $filesByItem,
    'lokacija'    => $lokacija,
    'stoppage'    => $stoppage,
    'parent'      => $parent,
    'children'    => $children,
    'log'         => $log,
]);

<?php
declare(strict_types=1);

// Projekat — centralno mesto: Pregled · Proizvodnja · Montaža · Dokumenti i istorija (?t=…)

$id = (int) ($_GET['id'] ?? 0);
$b = $id && can_view_project($user, $id) ? bundle_one($id) : null;
if (!$b) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'projekti']);
    return;
}
$tabs = ['pregled' => 'Pregled', 'proizvodnja' => 'Proizvodnja', 'montaza' => 'Montaža', 'dokumenti' => 'Dokumenti'];
$t = isset($tabs[$_GET['t'] ?? '']) ? $_GET['t'] : 'pregled';

$p = $b['p'];
$files = q_all('SELECT f.*, u.name AS uploader FROM files f LEFT JOIN users u ON u.id = f.uploaded_by WHERE f.project_id = ? AND f.pending = 0 ORDER BY f.created_at, f.id', [$id]);
$lokacija = null;
foreach ($files as $f) {
    if ($f['kind'] === 'lokacija_pre') {
        $lokacija = $f;
    }
}
$tasks = array_values(array_filter(q_all(
    "SELECT t.*, a.name AS assigned_name, d.name AS completed_name FROM tasks t JOIN users a ON a.id = t.assigned_to
     LEFT JOIN users d ON d.id = t.completed_by WHERE t.project_id = ? AND t.archived_at IS NULL ORDER BY t.status = 'zavrseno', t.due_date IS NULL, t.due_date",
    [$id]
), fn($x) => can_view_task($user, $x)));
$log = $t === 'dokumenti' ? q_all(
    'SELECT a.*, u.name AS user_name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.project_id = ? ORDER BY a.created_at DESC, a.id DESC LIMIT 150',
    [$id]
) : [];
$parent = $p['parent_project_id'] ? load_project((int) $p['parent_project_id']) : null;
$children = q_all('SELECT id, code, customer_name, status FROM projects WHERE parent_project_id = ? ORDER BY created_at', [$id]);
$allStops = q_all('SELECT * FROM stoppages WHERE project_id = ? AND task_id IS NULL ORDER BY created_at DESC', [$id]);
$teams = teams_active();
$halaUsers = array_filter(users_map(), fn($u) => in_array($u['role'], ['hala', 'vlasnik'], true) && $u['active']);

view('projekat', [
    'title'    => $p['customer_name'],
    'tab'      => 'projekti',
    'hideTop'  => true,
    'user'     => $user,
    'b'        => $b,
    't'        => $t,
    'tabs'     => $tabs,
    'files'    => $files,
    'lokacija' => $lokacija,
    'tasks'    => $tasks,
    'log'      => $log,
    'parent'   => $parent,
    'children' => $children,
    'allStops' => $allStops,
    'teams'    => $teams,
    'halaUsers' => $halaUsers,
]);

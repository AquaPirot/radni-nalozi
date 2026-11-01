<?php
declare(strict_types=1);

// Projekat — sve na jednoj strani: kupac, montaža, nalog, stavke, slike, komentari, istorija.

$id = (int) ($_GET['id'] ?? 0);
$b = bundle_one($id);
if (!$b) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'projekti']);
    return;
}
$p = $b['p'];
$photos = q_all("SELECT f.*, i.kind AS item_kind, i.label AS item_label FROM files f LEFT JOIN project_items i ON i.id = f.item_id
                 WHERE f.project_id = ? AND f.pending = 0 AND f.kind IN ('foto','lokacija_pre','posle')
                 ORDER BY f.created_at DESC, f.id DESC", [$id]);
$photoCount = [];
foreach ($photos as $f) {
    if ($f['item_id']) {
        $photoCount[(int) $f['item_id']] = ($photoCount[(int) $f['item_id']] ?? 0) + 1;
    }
}
$log = q_all('SELECT l.*, u.name AS user_name FROM activity_log l LEFT JOIN users u ON u.id = l.user_id
              WHERE l.project_id = ? ORDER BY l.id DESC LIMIT 60', [$id]);

view('projekat', [
    'title'      => $p['customer_name'],
    'eyebrow'    => $p['code'] . ' · ' . mb_strtoupper(TYPE_LABELS[$p['type']] ?? ''),
    'tab'        => 'projekti',
    'user'       => $user,
    'b'          => $b,
    'photos'     => $photos,
    'photoCount' => $photoCount,
    'comments'   => project_comments($id),
    'log'        => $log,
    'parent'     => $p['parent_project_id'] ? load_project((int) $p['parent_project_id']) : null,
    'children'   => q_all('SELECT id, code, status FROM projects WHERE parent_project_id = ? ORDER BY created_at', [$id]),
    'topExtra'   => '<a class="icon-btn" href="' . e(url('projekti')) . '" aria-label="Nazad">' . icon('back') . '</a>'
        . ($user['role'] === 'vlasnik' ? '<a class="icon-btn" href="' . e(url('izmeni', ['id' => $id])) . '" aria-label="Izmeni">' . icon('edit') . '</a>' : ''),
]);

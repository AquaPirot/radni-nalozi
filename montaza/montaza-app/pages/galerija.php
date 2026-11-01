<?php
declare(strict_types=1);

// Galerija slika projekta (?p=galerija&id=…[&item=…])

$project = load_project((int) ($_GET['id'] ?? 0));
if (!$project || !can_view_project($user, (int) $project['id'])) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'projekti']);
    return;
}
$itemId = (int) ($_GET['item'] ?? 0);
$sql = "SELECT f.*, i.kind AS item_kind FROM files f LEFT JOIN project_items i ON i.id = f.item_id
        WHERE f.project_id = ? AND f.task_id IS NULL AND f.mime LIKE 'image/%'";
$params = [$project['id']];
if ($itemId) {
    $sql .= ' AND f.item_id = ?';
    $params[] = $itemId;
}
$photos = q_all($sql . ' ORDER BY f.created_at, f.id', $params);

$groups = [];
foreach ($photos as $f) {
    $label = $f['kind'] === 'lokacija_pre' ? 'Mesto montaže · PRE' : ITEM_LABELS[$f['item_kind']] ?? 'Ostalo';
    $groups[$label][] = $f;
}

view('galerija', [
    'title'    => 'Fotografije',
    'eyebrow'  => mb_strtoupper(project_ref($project)),
    'tab'      => 'projekti',
    'focus'    => true,
    'user'     => $user,
    'groups'   => $groups,
    'topExtra' => '<a class="icon-btn" href="' . e(url('projekat', ['id' => $project['id']])) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

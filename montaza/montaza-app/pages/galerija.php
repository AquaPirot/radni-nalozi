<?php
declare(strict_types=1);

// Galerija slika projekta (?p=galerija&id=…[&item=…])

$project = load_project((int) ($_GET['id'] ?? 0));
if (!$project || !can_view_project($user, (int) $project['id'])) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'projekti']);
    return;
}
$sql = "SELECT f.*, ph.name AS phase_name, v.kind AS visit_kind, v.start_date AS visit_date FROM files f
        LEFT JOIN production_phases ph ON ph.id = f.phase_id LEFT JOIN visits v ON v.id = f.visit_id
        WHERE f.project_id = ? AND f.task_id IS NULL AND f.pending = 0 AND f.mime LIKE 'image/%'";
$params = [$project['id']];
foreach (['phase' => 'f.phase_id', 'visit' => 'f.visit_id'] as $k => $col) {
    if (!empty($_GET[$k])) {
        $sql .= " AND $col = ?";
        $params[] = (int) $_GET[$k];
    }
}
$photos = q_all($sql . ' ORDER BY f.created_at, f.id', $params);

$groups = [];
foreach ($photos as $f) {
    $label = match (true) {
        $f['kind'] === 'lokacija_pre' => 'Mesto montaže · PRE',
        (bool) $f['phase_name'] => 'Proizvodnja · ' . $f['phase_name'],
        (bool) $f['visit_kind'] => VISIT_KINDS[$f['visit_kind']] . ($f['visit_date'] ? ' ' . fmt_dm($f['visit_date']) : '') . ($f['purpose'] ? ' · ' . PHOTO_PURPOSE[$f['purpose']] : ''),
        default => 'Ostalo',
    };
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

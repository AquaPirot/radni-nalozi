<?php
declare(strict_types=1);

// Završi fazu proizvodnje (?p=faza&id=…): fotografije (već poslate) + napomena.

$ph = q_one('SELECT * FROM production_phases WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
$project = $ph ? load_project((int) $ph['project_id']) : null;
if (!$ph || !$project || !can_view_project($user, (int) $project['id'])) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'projekti']);
    return;
}
$back = url('projekat', ['id' => $project['id'], 't' => 'proizvodnja']);
if (!can_act_phase($user, $project, $ph)) {
    redirect($back);
}
$sys = q_one('SELECT s.*, t.name AS type_name FROM project_systems s LEFT JOIN system_types t ON t.id = s.system_type_id WHERE s.id = ?', [$ph['system_id']]);

$error = null;
$note = trim((string) ($_POST['note'] ?? ''));
if (is_post()) {
    csrf_check();
    if (!once_check()) {
        redirect($back);
    }
    try {
        $r = phase_complete((int) $ph['id'], $user, $note, posted_ids('foto_ids'));
        flash('ok', $ph['name'] . ' — završeno.' . ($r['all_done'] ? ' Proizvod je spreman za montažu.' : ''));
        redirect($back);
    } catch (RuleError $ex) {
        $error = $ex->getMessage();
    }
}
$existing = (int) q_val('SELECT COUNT(*) FROM files WHERE phase_id = ?', [$ph['id']]);
$min = $ph['requires_photo'] ? (int) setting('min_photos_hala', '1') : 0;

view('faza', [
    'title'    => $ph['name'] . ' — završi',
    'eyebrow'  => mb_strtoupper(project_ref($project) . ($sys ? ' · ' . system_label($sys) : '')),
    'tab'      => 'projekti',
    'focus'    => true,
    'user'     => $user,
    'ph'       => $ph,
    'error'    => $error,
    'note'     => $note,
    'existing' => $existing,
    'min'      => $min,
    'blocked'  => phase_blocked($project, $user),
    'topExtra' => '<a class="icon-btn" href="' . e($back) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

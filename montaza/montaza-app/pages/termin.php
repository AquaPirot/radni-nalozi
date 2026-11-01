<?php
declare(strict_types=1);

// Termin na terenu (?p=termin&project=…[&id=…]) — vlasnik: ekipa, datum, vreme, trajanje, okvirno/potvrđeno.
// Pomeranje traži razlog; potvrda traži spremnost (ili izuzetak sa beleškom).

if ($user['role'] !== 'vlasnik') {
    redirect(url());
}
$b = bundle_one((int) ($_GET['project'] ?? 0));
if (!$b) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'plan']);
    return;
}
$project = $b['p'];
$visit = !empty($_GET['id']) ? load_visit((int) $_GET['id']) : null;
if ($visit && (int) $visit['project_id'] !== (int) $project['id']) {
    $visit = null;
}
$back = (string) ($_GET['back'] ?? '') === 'plan' ? url('plan') : url('projekat', ['id' => $project['id'], 't' => 'montaza']);

$error = null;
$missing = [];
if (is_post()) {
    csrf_check();
    if (!once_check()) {
        redirect($back);
    }
    $in = visit_input($_POST);
    try {
        visit_save($project, $visit, $in, $user);
        flash('ok', 'Termin je sačuvan' . ($in['start_date'] ? ' — ' . visit_when(['start_date' => $in['start_date'], 'days' => $in['days'], 'start_time' => $in['start_time']]) : '') . '.');
        redirect($back);
    } catch (RuleError $ex) {
        $error = $ex->getMessage();
        $missing = $ex->missing;
    }
    $v = $in + ['id' => $visit['id'] ?? null];
} else {
    $v = $visit ?: [
        'kind' => $project['work_type'] === 'novi' ? 'montaza' : 'dijagnoza', 'team_id' => teams_active()[0]['id'] ?? null,
        'start_date' => (string) ($_GET['date'] ?? ''), 'start_time' => '08:00:00', 'days' => 1, 'confirmed' => 0, 'note' => '',
    ];
    $v['reason'] = '';
    $v['override'] = '';
}
$overlap = visit_overlaps($v['team_id'] ? (int) $v['team_id'] : null, $v['start_date'] ?: null, (int) $v['days'], (int) ($visit['id'] ?? 0));
$checks = readiness($b, ['team_id' => $v['team_id'], 'confirmed' => 1]);

view('termin', [
    'title'    => $visit ? 'Izmena termina' : 'Novi termin',
    'eyebrow'  => mb_strtoupper(project_ref($project)),
    'tab'      => 'plan',
    'focus'    => true,
    'user'     => $user,
    'b'        => $b,
    'visit'    => $visit,
    'v'        => $v,
    'error'    => $error,
    'missing'  => $missing,
    'overlap'  => $overlap,
    'checks'   => $checks,
    'teams'    => teams_active(),
    'topExtra' => '<a class="icon-btn" href="' . e($back) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

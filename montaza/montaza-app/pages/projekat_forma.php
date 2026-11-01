<?php
declare(strict_types=1);

// Novi projekat (?p=novi) i izmena (?p=izmeni&id=…). Samo vlasnik.
// Dve akcije: "Sačuvaj nacrt" (samo kupac je obavezan) i "Pusti u realizaciju" (puna provera).

$isNew = $p === 'novi';
$project = null;
$b = null;
if (!$isNew) {
    $b = bundle_one((int) ($_GET['id'] ?? 0));
    if (!$b) {
        http_response_code(404);
        view('404', ['title' => 'Nije pronađeno']);
        return;
    }
    $project = $b['p'];
}

$errors = [];
if (is_post()) {
    csrf_check();
    if (!once_check()) {
        redirect($project ? url('projekat', ['id' => $project['id']]) : url('projekti'));
    }
    $in = project_input($_POST);
    $mode = (string) ($_POST['mode'] ?? 'draft'); // draft | release | save
    $release = $mode === 'release';
    $hasLok = (bool) $in['lokacija_id'] || ($project && q_val("SELECT 1 FROM files WHERE project_id = ? AND kind = 'lokacija_pre'", [$project['id']]));
    $needFull = $release || ($project && $project['status'] !== 'nacrt');
    $errors = validate_project($in, $needFull, $hasLok);
    if (!$errors) {
        try {
            $id = project_save($project, $in, $user, $release);
            flash('ok', $release ? 'Projekat je pušten u realizaciju — hala i teren su obavešteni.' : ($project && $project['status'] !== 'nacrt' ? 'Izmene su sačuvane.' : 'Nacrt je sačuvan. Kad dopuniš podatke, pusti ga u realizaciju.'));
            redirect(url('projekat', ['id' => $id]));
        } catch (RuleError | UploadError $ex) {
            $errors['_'] = $ex->getMessage();
        }
    }
    $values = $in;
    $values['deadline'] = $in['deadline_raw'];
    $values['payment_amount'] = $in['payment_amount_raw'];
    $values['systems'] = array_values(array_filter($in['systems'], fn($s) => !$s['delete']));
} elseif ($isNew) {
    $values = [
        'work_type' => 'novi', 'intake' => 'novi', 'customer_name' => '', 'phone' => '', 'city' => '', 'address' => '',
        'description' => '', 'deadline' => '', 'payment_when' => 'pre', 'payment_amount' => '', 'payment_collector' => 'teren',
        'notes' => '', 'parent_project_id' => null, 'systems' => [],
    ];
} else {
    $values = $project;
    $values['payment_amount'] = $project['payment_amount'] !== null ? fmt_m($project['payment_amount']) : '';
    $values['deadline'] = (string) $project['deadline'];
    $values['systems'] = array_map(fn($s) => [
        'id' => (int) $s['id'], 'type_id' => (int) $s['system_type_id'], 'qty' => (int) $s['qty'],
        'width' => fmt_m($s['width']), 'length' => fmt_m($s['length']), 'height' => fmt_m($s['height']),
        'ral' => $s['ral'], 'spec' => $s['spec'], 'note' => $s['note'], 'with_steel' => (bool) $s['with_steel'], 'done_until' => (int) ($s['spec']['_done_until'] ?? 0),
    ], $b['systems']);
}

$types = q_all('SELECT * FROM system_types WHERE active = 1 ORDER BY sort, name');
$tpl = [];
foreach (q_all('SELECT system_type_id, name, sort FROM phase_templates ORDER BY sort') as $t) {
    $tpl[$t['system_type_id']][] = [(int) $t['sort'], $t['name']];
}
$started = [];
if ($b) {
    foreach ($b['phases'] as $ph) {
        if ($ph['status'] !== 'ceka' || $ph['imported']) {
            $started[(int) $ph['system_id']] = true;
        }
    }
}
$parents = q_all("SELECT id, code, customer_name, city FROM projects WHERE work_type <> 'reklamacija' AND archived_at IS NULL ORDER BY created_at DESC LIMIT 200");
$lokacija = $project ? q_one("SELECT id FROM files WHERE project_id = ? AND kind = 'lokacija_pre' ORDER BY id DESC LIMIT 1", [$project['id']]) : null;

view('projekat_forma', [
    'title'    => $isNew ? 'Novi projekat' : ($project['status'] === 'nacrt' ? 'Nacrt projekta' : 'Izmena projekta'),
    'eyebrow'  => $isNew ? 'AG GROUP · REALIZACIJA' : $project['code'] . ' · ' . mb_strtoupper(WORK_TYPES[$project['work_type']]),
    'tab'      => 'projekti',
    'focus'    => true,
    'user'     => $user,
    'isNew'    => $isNew,
    'project'  => $project,
    'v'        => $values,
    'errors'   => $errors,
    'types'    => $types,
    'tpl'      => $tpl,
    'started'  => $started,
    'parents'  => $parents,
    'lokacija' => $lokacija,
    'topExtra' => '<a class="icon-btn" href="' . e($isNew ? url('projekti') : url('projekat', ['id' => $project['id']])) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

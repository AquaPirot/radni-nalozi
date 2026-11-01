<?php
declare(strict_types=1);

// Novi projekat (?p=novi) i izmena (?p=izmeni&id=…) — samo vlasnik. Detalji posla su u PDF nalogu.

$isNew = $p === 'novi';
$project = null;
if (!$isNew) {
    $project = load_project((int) ($_GET['id'] ?? 0));
    if (!$project) {
        http_response_code(404);
        view('404', ['title' => 'Nije pronađeno', 'tab' => 'projekti']);
        return;
    }
}

$errors = [];
if (is_post()) {
    csrf_check();
    if (!once_check()) {
        redirect($isNew ? url('projekti') : url('projekat', ['id' => $project['id']]));
    }
    if (!$isNew && ($_POST['do'] ?? '') === 'delete') {
        project_delete($project, $user);
        flash('ok', 'Projekat ' . $project['code'] . ' je obrisan.');
        redirect(url('projekti'));
    }
    $in = project_input($_POST);
    $errors = project_validate($in);
    if (!$errors) {
        $id = project_save($project, $in, $user, posted_ids('nalog_id'), posted_ids('lokacija_id'));
        flash('ok', $isNew ? 'Projekat je otvoren — hala i teren dobijaju obaveštenje.' : 'Sačuvano.');
        redirect(url('projekat', ['id' => $id]));
    }
    $v = $in;
    $v['install_date'] = $in['install_raw'];
} elseif ($isNew) {
    $v = ['type' => 'novi', 'customer_name' => '', 'phone' => '', 'city' => '', 'address' => '', 'has_steel' => false,
          'install_date' => '', 'install_time' => null, 'description' => '', 'parent_project_id' => (int) ($_GET['parent'] ?? 0) ?: null];
    if ($v['parent_project_id'] && ($par = load_project($v['parent_project_id']))) {
        $v = array_merge($v, ['type' => 'reklamacija', 'customer_name' => $par['customer_name'], 'phone' => (string) $par['phone'], 'city' => (string) $par['city'], 'address' => (string) $par['address']]);
    }
} else {
    $v = $project;
    $v['has_steel'] = (bool) $project['has_steel'];
}
$done = q_all("SELECT id, code, customer_name, city FROM projects WHERE status = 'zavrsen' OR id = ? ORDER BY completed_at DESC LIMIT 200", [(int) ($v['parent_project_id'] ?? 0)]);

view('projekat_forma', [
    'title'    => $isNew ? 'Novi projekat' : 'Izmena projekta',
    'tab'      => 'projekti',
    'focus'    => true,
    'isNew'    => $isNew,
    'project'  => $project,
    'v'        => $v,
    'errors'   => $errors,
    'done'     => $done,
    'topExtra' => '<a class="icon-btn" href="' . e($isNew ? url('projekti') : url('projekat', ['id' => $project['id']])) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

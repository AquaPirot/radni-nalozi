<?php
declare(strict_types=1);

// Novi projekat (?p=novi) i izmena (?p=izmeni&id=…). Samo vlasnik (proverava ruter).

$isNew = $p === 'novi';
$project = null;
$items = [];
if (!$isNew) {
    $project = load_project((int) ($_GET['id'] ?? 0));
    if (!$project) {
        http_response_code(404);
        view('404', ['title' => 'Nije pronađeno']);
        return;
    }
    $items = load_items((int) $project['id']);
}

$errors = [];
$notices = [];

if (is_post()) {
    csrf_check();
    $in = project_input($_POST);
    if (!$isNew) {
        $in['type'] = $project['type'];
    }
    $lokUploads = $isNew ? uploaded_files('lokacija') : [];
    $pdfUploads = $isNew ? uploaded_files('nalog_pdf') : [];
    $errors = rules_validate_project($in, $isNew, (bool) $lokUploads);

    if ($isNew && $in['parent_project_id'] && !load_project($in['parent_project_id'])) {
        $in['parent_project_id'] = null;
    }

    if (!$errors) {
        try {
            if ($isNew) {
                $id = create_project($in, $user, $lokUploads[0] ?? null, $in['type'] !== 'reklamacija' ? ($pdfUploads[0] ?? null) : null);
                flash('ok', 'Projekat je sačuvan.');
            } else {
                $id = (int) $project['id'];
                $notices = update_project($project, $in, $user, (array) ($_POST['due'] ?? []));
                foreach ($notices as $n) {
                    flash('warn', $n);
                }
                flash('ok', 'Izmene su sačuvane.');
            }
            redirect(url('projekat', ['id' => $id]));
        } catch (UploadError $ex) {
            $errors['upload'] = $ex->getMessage();
        }
    }
    if ($isNew && $lokUploads && !isset($errors['lokacija'])) {
        $errors['reattach'] = 'Zbog greške ponovo izaberi sliku lokacije i PDF nalog.';
    }
    $values = $in;
    $values['payment_amount'] = $in['payment_amount_raw'];
    $values['deadline'] = $in['deadline_raw'];
} elseif ($isNew) {
    $values = [
        'type' => 'novi', 'customer_name' => '', 'phone' => '', 'city' => '', 'address' => '', 'description' => '',
        'has_steel' => false, 'install_week_start' => null, 'deadline' => '', 'payment_when' => 'pre',
        'payment_amount' => '', 'notes' => '', 'done_until' => '', 'parent_project_id' => null,
    ];
} else {
    $values = $project;
    $values['has_steel'] = (bool) $project['has_steel'];
    $values['payment_amount'] = $project['payment_amount'] !== null ? rtrim(rtrim($project['payment_amount'], '0'), '.') : '';
    $values['deadline'] = (string) $project['deadline'];
}

// Projekti za vezu reklamacije (poslednjih 200 ne-reklamacija)
$parents = $isNew ? q_all(
    "SELECT id, code, customer_name, city FROM projects WHERE type <> 'reklamacija' ORDER BY created_at DESC LIMIT 200"
) : [];

view('projekat_forma', [
    'title'    => $isNew ? 'Novi projekat' : 'Izmena projekta',
    'eyebrow'  => $isNew ? 'AG GROUP · REALIZACIJA' : $project['code'] . ' · ' . mb_strtoupper(TYPE_LABELS[$project['type']]),
    'tab'      => 'projekti',
    'focus'    => true,
    'user'     => $user,
    'isNew'    => $isNew,
    'project'  => $project,
    'items'    => $items,
    'values'   => $values,
    'errors'   => $errors,
    'parents'  => $parents,
    'topExtra' => '<a class="icon-btn" href="' . e($isNew ? url() : url('projekat', ['id' => $project['id']])) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

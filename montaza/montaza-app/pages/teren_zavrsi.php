<?php
declare(strict_types=1);

// Završetak termina na terenu (?p=teren_zavrsi&id=…): ishod, slike po svrsi, naplata, štikle.

$visit = load_visit((int) ($_GET['id'] ?? 0));
$b = $visit ? bundle_one((int) $visit['project_id']) : null;
if (!$visit || !$b || !can_view_project($user, (int) $visit['project_id'])) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'projekti']);
    return;
}
$project = $b['p'];
$back = url('projekat', ['id' => $project['id'], 't' => 'montaza']);
if (!can_act_visit($user, $project, $visit)) {
    redirect($back);
}

$miss = [];
$error = null;
$in = [
    'result' => (string) ($_POST['result'] ?? ''), 'remaining' => trim((string) ($_POST['remaining'] ?? '')),
    'note' => trim((string) ($_POST['note'] ?? '')), 'video' => !empty($_POST['video']), 'handover' => !empty($_POST['handover']),
    'paid' => null, 'method' => (string) ($_POST['method'] ?? 'gotovina'), 'debt_ok' => !empty($_POST['debt_ok']),
];
if (is_post()) {
    csrf_check();
    if (!once_check()) {
        redirect($back);
    }
    $paidRaw = trim((string) ($_POST['paid'] ?? ''));
    $in['paid'] = $paidRaw !== '' ? (float) (parse_amount($paidRaw) ?? 0) : null;
    try {
        $miss = visit_complete((int) $visit['id'], $user, $in, [
            'celina' => posted_ids('celina'), 'detalj' => posted_ids('detalj'), 'nedostatak' => posted_ids('nedostatak'),
        ]);
        if (!$miss) {
            flash('ok', VISIT_KINDS[$visit['kind']] . ' — ' . mb_strtolower(VISIT_RESULTS[$in['result']]) . '.' . ($in['result'] !== 'celo' ? ' Napravljen je ponovni dolazak za ostatak.' : ''));
            redirect($back);
        }
        $error = 'Fali: ' . implode(', ', $miss) . '.';
    } catch (RuleError $ex) {
        $error = $ex->getMessage();
    }
}

$lokacija = q_one("SELECT id FROM files WHERE project_id = ? AND kind = 'lokacija_pre' ORDER BY id DESC LIMIT 1", [$project['id']]);
$existing = [];
foreach (q_all('SELECT purpose, COUNT(*) n FROM files WHERE visit_id = ? GROUP BY purpose', [$visit['id']]) as $r) {
    $existing[(string) $r['purpose']] = (int) $r['n'];
}

view('teren_zavrsi', [
    'title'    => VISIT_KINDS[$visit['kind']] . ' — završetak',
    'eyebrow'  => mb_strtoupper(project_ref($project)),
    'tab'      => 'projekti',
    'focus'    => true,
    'user'     => $user,
    'b'        => $b,
    'visit'    => $visit,
    'in'       => $in,
    'error'    => $error,
    'lokacija' => $lokacija,
    'existing' => $existing,
    'collects' => visit_collects($b, $visit),
    'pay'      => payment_info($b),
    'topExtra' => '<a class="icon-btn" href="' . e($back) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

<?php
declare(strict_types=1);

// Prijava zastoja (?p=zastoj&phase=… | &visit=… | &task=… | &project=…):
// razlog, kratak opis, ko rešava, sledeći korak, očekivano rešenje.

$target = [];
$back = url();
$title = '';
if (!empty($_GET['phase'])) {
    $ph = q_one('SELECT * FROM production_phases WHERE id = ?', [(int) $_GET['phase']]);
    $p = $ph ? load_project((int) $ph['project_id']) : null;
    if ($ph && $p && can_act_phase($user, $p, $ph)) {
        $target = ['project_id' => (int) $p['id'], 'phase_id' => (int) $ph['id']];
        $title = project_ref($p) . ' · ' . $ph['name'];
        $back = url('projekat', ['id' => $p['id'], 't' => 'proizvodnja']);
    }
} elseif (!empty($_GET['visit'])) {
    $v = load_visit((int) $_GET['visit']);
    $p = $v ? load_project((int) $v['project_id']) : null;
    if ($v && $p && can_act_visit($user, $p, $v)) {
        $target = ['project_id' => (int) $p['id'], 'visit_id' => (int) $v['id']];
        $title = project_ref($p) . ' · ' . VISIT_KINDS[$v['kind']];
        $back = url('projekat', ['id' => $p['id'], 't' => 'montaza']);
    }
} elseif (!empty($_GET['task'])) {
    $t = load_task((int) $_GET['task']);
    if ($t && can_act_task($user, $t)) {
        $target = ['task_id' => (int) $t['id']];
        $title = $t['title'];
        $back = url('zadatak', ['id' => $t['id']]);
    }
} elseif (!empty($_GET['project'])) {
    $p = load_project((int) $_GET['project']);
    if ($p && $p['status'] === 'aktivan' && can_view_project($user, (int) $p['id'])) {
        $target = ['project_id' => (int) $p['id']];
        $title = project_ref($p);
        $back = url('projekat', ['id' => $p['id']]);
    }
}
if (!$target) {
    redirect($back);
}

$defaults = ['materijal' => 'vlasnik', 'proizvodnja' => 'hala', 'kupac' => 'vlasnik', 'teren' => 'teren', 'vreme' => 'vlasnik', 'transport' => 'vlasnik', 'ostalo' => 'vlasnik'];
$in = [
    'reason' => (string) ($_POST['reason'] ?? ''), 'note' => trim((string) ($_POST['note'] ?? '')),
    'owner_id' => (int) ($_POST['owner_id'] ?? 0), 'next_step' => trim((string) ($_POST['next_step'] ?? '')),
    'expected_date' => (string) ($_POST['expected_date'] ?? ''),
];
$error = null;
if (is_post()) {
    csrf_check();
    if (!once_check()) {
        redirect($back);
    }
    try {
        if (!empty($target['task_id']) && empty($target['project_id'])) {
            task_stoppage((int) $target['task_id'], $user, $in);
        } else {
            stoppage_open($target, $in, $user);
        }
        flash('warn', 'Zastoj je prijavljen — odgovorna osoba je obaveštena.');
        redirect($back);
    } catch (RuleError $ex) {
        $error = $ex->getMessage();
    }
}

view('zastoj', [
    'title'    => 'Prijavi problem',
    'eyebrow'  => mb_strtoupper(mb_substr($title, 0, 60)),
    'tab'      => 'projekti',
    'focus'    => true,
    'user'     => $user,
    'in'       => $in,
    'error'    => $error,
    'people'   => array_filter(users_map(), fn($u) => $u['active']),
    'defaults' => $defaults,
    'topExtra' => '<a class="icon-btn" href="' . e($back) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

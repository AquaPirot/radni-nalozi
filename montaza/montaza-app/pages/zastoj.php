<?php
declare(strict_types=1);

// Prijava zastoja na stavci (?p=zastoj&item=…) — pravilo 9.

$item = q_one('SELECT * FROM project_items WHERE id = ?', [(int) ($_GET['item'] ?? 0)]);
$project = $item ? load_project((int) $item['project_id']) : null;
if (!$item || !$project || !can_view_project($user, (int) $project['id'])) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'projekti']);
    return;
}
$back = url('projekat', ['id' => $project['id']]);
if (!can_act_item($user, $project, $item)) {
    redirect($back);
}

$error = null;
$reason = (string) ($_POST['reason'] ?? '');
$note = trim((string) ($_POST['note'] ?? ''));
if (is_post()) {
    csrf_check();
    try {
        item_stoppage((int) $item['id'], $user, $reason, $note);
        flash('warn', 'Zastoj je prijavljen.');
        redirect($back);
    } catch (RuleError $ex) {
        $error = $ex->getMessage();
    }
}

view('zastoj', [
    'title'    => 'Zastoj',
    'eyebrow'  => mb_strtoupper(project_ref($project) . ' · ' . ITEM_LABELS[$item['kind']]),
    'tab'      => 'projekti',
    'focus'    => true,
    'user'     => $user,
    'item'     => $item,
    'reason'   => $reason,
    'note'     => $note,
    'error'    => $error,
    'topExtra' => '<a class="icon-btn" href="' . e($back) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

<?php
declare(strict_types=1);

$task = load_task((int) ($_GET['id'] ?? 0));
if (!$task || !can_view_task($user, $task)) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'zadaci']);
    return;
}
$back = url('zadatak', ['id' => $task['id']]);
if (!can_act_task($user, $task)) {
    redirect($back);
}
$error = null;
$reason = (string) ($_POST['reason'] ?? '');
$note = trim((string) ($_POST['note'] ?? ''));
if (is_post()) {
    csrf_check();
    try {
        task_stoppage((int) $task['id'], $user, $reason, $note);
        flash('warn', 'Zastoj je prijavljen.');
        redirect($back);
    } catch (RuleError $ex) {
        $error = $ex->getMessage();
    }
}
view('zastoj', [
    'title'    => 'Zastoj',
    'eyebrow'  => mb_strtoupper(mb_substr($task['title'], 0, 40)),
    'tab'      => 'zadaci',
    'focus'    => true,
    'user'     => $user,
    'action'   => url('zadatak_zastoj', ['id' => $task['id']]),
    'reason'   => $reason,
    'note'     => $note,
    'error'    => $error,
    'topExtra' => '<a class="icon-btn" href="' . e($back) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

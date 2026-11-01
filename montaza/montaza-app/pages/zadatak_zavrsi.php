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
$note = trim((string) ($_POST['note'] ?? ''));
if (is_post()) {
    csrf_check();
    if (!once_check()) {
        redirect(url('zadaci'));
    }
    try {
        task_complete((int) $task['id'], $user, $note, posted_ids('foto_ids'));
        flash('ok', 'Zadatak je završen.');
        redirect(url('zadaci'));
    } catch (RuleError | UploadError $ex) {
        $error = $ex->getMessage();
    }
}
$existing = (int) q_val('SELECT COUNT(*) FROM files WHERE task_id = ?', [$task['id']]);

view('zadatak_zavrsi', [
    'title'    => 'Završi zadatak',
    'eyebrow'  => mb_strtoupper(mb_substr($task['title'], 0, 40)),
    'tab'      => 'zadaci',
    'focus'    => true,
    'user'     => $user,
    'task'     => $task,
    'existing' => $existing,
    'error'    => $error,
    'note'     => $note,
    'topExtra' => '<a class="icon-btn" href="' . e($back) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

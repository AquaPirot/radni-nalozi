<?php
declare(strict_types=1);

$task = load_task((int) ($_GET['id'] ?? 0));
if (!$task || !can_view_task($user, $task)) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'zadaci']);
    return;
}
$photos = q_all("SELECT * FROM files WHERE task_id = ? ORDER BY id", [$task['id']]);
$stop = $task['status'] === 'zastoj' ? q_one('SELECT * FROM stoppages WHERE task_id = ? AND resolved_at IS NULL ORDER BY id DESC LIMIT 1', [$task['id']]) : null;

view('zadatak', [
    'title'    => 'Zadatak',
    'eyebrow'  => $task['priority'] === 'hitno' && $task['status'] !== 'zavrseno' ? 'HITNO' : 'ZADATAK',
    'tab'      => 'zadaci',
    'focus'    => true,
    'user'     => $user,
    'task'     => $task,
    'photos'   => $photos,
    'stop'     => $stop,
    'topExtra' => (can_edit_task($user, $task) ? '<a class="icon-btn" href="' . e(url('izmeni_zadatak', ['id' => $task['id']])) . '" aria-label="Izmeni">' . icon('edit') . '</a>' : '')
        . '<a class="icon-btn" href="' . e(url('zadaci')) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

<?php
declare(strict_types=1);

// Novi zadatak (?p=novi_zadatak[&project=…]) i izmena (?p=izmeni_zadatak&id=…)

$isNew = $p === 'novi_zadatak';
$task = null;
if (!$isNew) {
    $task = load_task((int) ($_GET['id'] ?? 0));
    if (!$task || !can_edit_task($user, $task)) {
        http_response_code(404);
        view('404', ['title' => 'Nije pronađeno', 'tab' => 'zadaci']);
        return;
    }
}

$errors = [];
if (is_post()) {
    csrf_check();
    if (!once_check()) {
        redirect($isNew ? url('zadaci') : url('zadatak', ['id' => $task['id']]));
    }
    $in = task_input($_POST);
    $errors = task_validate($in, $user);
    if (!$errors) {
        if ($isNew) {
            $id = task_create($in, $user);
            flash('ok', 'Zadatak je zadat.');
        } else {
            $id = (int) $task['id'];
            task_update($task, $in, $user);
            flash('ok', 'Zadatak je izmenjen.');
        }
        redirect(url('zadatak', ['id' => $id]));
    }
    $v = $in;
    $v['due_date'] = $in['due_raw'];
    $v['due_time'] = $in['due_time'];
} elseif ($isNew) {
    $v = [
        'title' => '', 'description' => '', 'assigned_to' => $user['role'] === 'vlasnik' ? 0 : (int) $user['id'],
        'project_id' => (int) ($_GET['project'] ?? 0) ?: null, 'due_date' => '', 'due_time' => null, 'no_deadline' => false, 'priority' => 'normal', 'requires_photo' => false,
    ];
} else {
    $v = $task;
    $v['requires_photo'] = (bool) $task['requires_photo'];
    $v['no_deadline'] = !$task['due_date'];
}

$projects = q_all("SELECT id, code, customer_name, city FROM projects WHERE status = 'aktivan' OR id = ? ORDER BY customer_name", [(int) ($v['project_id'] ?? 0)]);

view('zadatak_forma', [
    'title'    => $isNew ? 'Novi zadatak' : 'Izmena zadatka',
    'tab'      => 'zadaci',
    'focus'    => true,
    'user'     => $user,
    'isNew'    => $isNew,
    'task'     => $task,
    'v'        => $v,
    'errors'   => $errors,
    'people'   => assignable_users($user),
    'projects' => $projects,
    'topExtra' => '<a class="icon-btn" href="' . e($isNew ? url('zadaci') : url('zadatak', ['id' => $task['id']])) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

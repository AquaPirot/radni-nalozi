<?php
declare(strict_types=1);

// Kratke akcije nad zadatkom: done (bez slike), start/nastavak, delete.

require dirname(__DIR__) . '/_init.php';
session_boot();

if (!is_post()) {
    http_response_code(405);
    exit;
}
csrf_check();
$user = require_login();
$task = load_task((int) ($_POST['id'] ?? 0));
if (!$task || !can_view_task($user, $task)) {
    flash('warn', 'Zadatak ne postoji.');
    redirect(url('zadaci'));
}
$back = url('zadatak', ['id' => $task['id']]);

try {
    switch ($_POST['action'] ?? '') {
        case 'done':
            task_complete((int) $task['id'], $user, '', []);
            flash('ok', 'Završeno: ' . $task['title']);
            $f = (string) ($_POST['back'] ?? '');
            $back = $f === 'danas' ? url('danas') : url('zadaci', preg_match('/^[a-z]+$/', $f) ? ['f' => $f] : []);
            break;
        case 'start':
            task_start((int) $task['id'], $user);
            flash('ok', $task['status'] === 'zastoj' ? 'Zastoj je zatvoren — nastavljeno.' : 'Zadatak je započet.');
            break;
        case 'delete':
            if (!can_edit_task($user, $task)) {
                throw new RuleError('Nemaš pravo da obrišeš ovaj zadatak.');
            }
            task_delete($task);
            flash('ok', 'Zadatak je obrisan.');
            $back = url('zadaci');
            break;
        default:
            http_response_code(400);
            exit;
    }
} catch (RuleError $ex) {
    flash('warn', $ex->getMessage());
}
redirect($back);

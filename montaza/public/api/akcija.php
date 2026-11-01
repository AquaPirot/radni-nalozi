<?php
declare(strict_types=1);

// Kratke akcije (dugmad): POST action=…, uvek CSRF + jednokratni token (protiv duplog slanja).
// Prava se proveravaju u funkcijama (lib/*), ne ovde.

require dirname(__DIR__) . '/_init.php';
session_boot();

if (!is_post()) {
    http_response_code(405);
    exit;
}
csrf_check();
$user = require_login();

// Povratak: samo lokalna adresa
$back = (string) ($_POST['back'] ?? '');
if ($back === '' || !str_starts_with($back, '/') || str_starts_with($back, '//')) {
    $back = url();
} else {
    $back = rtrim((string) config('base_url', ''), '/') . $back;
}
if (!once_check()) {
    redirect($back);
}

$id = (int) ($_POST['id'] ?? 0);
$project = fn() => load_project($id) ?? throw new RuleError('Projekat ne postoji.');

try {
    switch ((string) ($_POST['action'] ?? '')) {
        case 'install':
            $date = parse_date($_POST['install_date'] ?? '');
            if (trim((string) ($_POST['install_date'] ?? '')) !== '' && !$date) {
                throw new RuleError('Neispravan datum.');
            }
            project_set_install($project(), $date, parse_time($_POST['install_time'] ?? ''), $user);
            flash('ok', $date ? 'Montaža: ' . install_when(load_project($id), false) . ' — teren i hala su obavešteni.' : 'Datum montaže je uklonjen.');
            break;
        case 'nalog':
            nalog_upload($project(), posted_ids('file_id')[0] ?? 0, $user);
            flash('ok', 'Nalog je sačuvan — hala je obaveštena.');
            break;
        case 'photos':
            $n = photos_add($project(), posted_ids('foto_ids'), $user);
            flash('ok', $n ? "Sačuvano slika: $n." : 'Nijedna slika nije stigla — pokušaj ponovo.');
            break;
        case 'item_start':
            item_start($id, $user);
            flash('ok', 'U radu.');
            break;
        case 'item_reset':
            item_reset($id, $user);
            flash('ok', 'Stavka je vraćena na „čeka".');
            break;
        case 'stop_open':
            stop_open($project(), (string) ($_POST['reason'] ?? ''), (string) ($_POST['note'] ?? ''), $user);
            flash('ok', 'Zastoj je prijavljen — svi su obavešteni.');
            break;
        case 'stop_resolve':
            stop_resolve($project(), $user);
            flash('ok', 'Zastoj je rešen.');
            break;
        case 'project_comment':
            project_comment_add($project(), $user, (string) ($_POST['body'] ?? ''), posted_ids('file_id')[0] ?? null);
            flash('ok', 'Komentar je poslat.');
            break;
        default:
            http_response_code(400);
            exit;
    }
} catch (RuleError | UploadError $ex) {
    flash('warn', $ex->getMessage());
}
redirect($back);

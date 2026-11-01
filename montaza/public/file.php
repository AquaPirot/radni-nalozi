<?php
declare(strict_types=1);

// Servira upload-ovane fajlove SAMO prijavljenim korisnicima koji smeju da vide projekat.

require __DIR__ . '/_init.php';
session_boot();
$user = current_user();
if (!$user) {
    http_response_code(401);
    exit;
}

$file = q_one('SELECT * FROM files WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
// Svi prijavljeni vide sve; fajl koji je upravo poslat (još nije zakačen) vidi samo onaj ko ga je poslao.
$allowed = $file && (!$file['pending'] || (int) $file['uploaded_by'] === (int) $user['id']);
if (!$allowed) {
    http_response_code(404);
    exit;
}

$rel = !empty($_GET['t']) && $file['thumb_path'] ? $file['thumb_path'] : $file['path'];
$path = upload_root() . '/' . $rel;
if (str_contains($rel, '..') || !is_file($path)) {
    http_response_code(404);
    exit;
}

$code = $file['project_id'] ? (string) q_val('SELECT code FROM projects WHERE id = ?', [$file['project_id']]) : ($file['task_id'] ? 'zadatak' . $file['task_id'] : 'novo');
$name = ($file['kind'] === 'nalog_pdf' ? 'Nalog-' : 'Foto-') . $code . '-' . $file['id'] . ($file['mime'] === 'application/pdf' ? '.pdf' : '.jpg');

header('Content-Type: ' . $file['mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $name . '"');
header('Cache-Control: private, max-age=604800, immutable');
header('X-Content-Type-Options: nosniff');
readfile($path);

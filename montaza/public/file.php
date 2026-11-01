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
if (!$file || !can_view_project($user, (int) $file['project_id'])) {
    http_response_code(404);
    exit;
}

$rel = !empty($_GET['t']) && $file['thumb_path'] ? $file['thumb_path'] : $file['path'];
$path = upload_root() . '/' . $rel;
if (str_contains($rel, '..') || !is_file($path)) {
    http_response_code(404);
    exit;
}

$code = (string) q_val('SELECT code FROM projects WHERE id = ?', [$file['project_id']]);
$name = ($file['kind'] === 'nalog_pdf' ? 'Nalog-' : 'Foto-') . $code . '-' . $file['id'] . ($file['mime'] === 'application/pdf' ? '.pdf' : '.jpg');

header('Content-Type: ' . $file['mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $name . '"');
header('Cache-Control: private, max-age=604800, immutable');
header('X-Content-Type-Options: nosniff');
readfile($path);

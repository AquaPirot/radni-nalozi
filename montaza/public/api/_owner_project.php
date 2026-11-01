<?php
declare(strict_types=1);

// Zajednički početak API akcija vlasnika nad projektom: POST + CSRF + uloga + projekat.
// Postavlja $user i $project.

require dirname(__DIR__) . '/_init.php';
session_boot();

if (!is_post()) {
    http_response_code(405);
    exit;
}
csrf_check();
$user = require_role('vlasnik');
$project = load_project((int) ($_POST['id'] ?? 0));
if (!$project) {
    flash('warn', 'Projekat ne postoji.');
    redirect(url());
}
$back = url('projekat', ['id' => $project['id']]);

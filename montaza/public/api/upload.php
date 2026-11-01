<?php
declare(strict_types=1);

// Prima JEDAN fajl odmah pri izboru (slika ili PDF) i vraća njegov id.
// Forma kasnije šalje samo id-jeve — slaba veza ne gubi tekst ni slike.

require dirname(__DIR__) . '/_init.php';
session_boot();

if (!is_post()) {
    http_response_code(405);
    exit;
}
csrf_check();
$user = current_user();
if (!$user) {
    json_out(['ok' => false, 'error' => 'Nisi prijavljen.'], 401);
}
$f = uploaded_files('file')[0] ?? null;
if (!$f) {
    json_out(['ok' => false, 'error' => 'Fajl nije stigao — pokušaj ponovo.'], 400);
}
$type = ($_POST['type'] ?? '') === 'pdf' ? 'pdf' : 'foto';
try {
    $row = store_pending($f, $type, (int) $user['id']);
    json_out(['ok' => true, 'id' => (int) $row['id'], 'thumb' => $row['thumb_path'] ? file_url((int) $row['id'], true) : null]);
} catch (UploadError $ex) {
    json_out(['ok' => false, 'error' => $ex->getMessage()], 422);
}

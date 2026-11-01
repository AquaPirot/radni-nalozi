<?php
declare(strict_types=1);

require __DIR__ . '/_owner_project.php';

$f = uploaded_files('lokacija')[0] ?? null;
if (!$f) {
    flash('warn', 'Izaberi sliku.');
    redirect($back);
}
try {
    set_lokacija_photo($project, $f, $user);
    flash('ok', 'Slika lokacije je sačuvana.');
} catch (UploadError $ex) {
    flash('warn', $ex->getMessage());
}
redirect($back);

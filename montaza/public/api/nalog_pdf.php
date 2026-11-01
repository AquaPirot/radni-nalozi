<?php
declare(strict_types=1);

require __DIR__ . '/_owner_project.php';

$f = uploaded_files('nalog_pdf')[0] ?? null;
if (!$f) {
    flash('warn', 'Izaberi PDF fajl.');
    redirect($back);
}
try {
    attach_nalog_pdf($project, $f, $user);
    flash('ok', 'Nalog je zakačen.');
} catch (UploadError $ex) {
    flash('warn', $ex->getMessage());
}
redirect($back);

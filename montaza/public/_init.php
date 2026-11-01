<?php
declare(strict_types=1);

// Pronalazi montaza-app: prvo VAN web root-a, pa u podfolderu (zaštićenom .htaccess-om).
define('PUBLIC_DIR', __DIR__);

$candidates = [
    dirname(__DIR__) . '/montaza-app/bootstrap.php',      // /home/korisnik/montaza-app (doc root: /home/korisnik/montaza.aggroup.rs)
    dirname(__DIR__, 2) . '/montaza-app/bootstrap.php',   // /home/korisnik/montaza-app (doc root: /home/korisnik/public_html/montaza)
    __DIR__ . '/montaza-app/bootstrap.php',
];
foreach ($candidates as $c) {
    if (is_file($c)) {
        require $c;
        return;
    }
}
http_response_code(500);
exit('Ne mogu da pronađem folder montaza-app.');

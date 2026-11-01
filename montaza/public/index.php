<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
session_boot();

// ?p=… → [handler u montaza-app/pages/, dozvoljene uloge (prazno = svi prijavljeni)]
$routes = [
    'danas'       => ['danas',          []],
    'projekti'    => ['projekti',       []],
    'projekat'    => ['projekat',       []],
    'novi'        => ['projekat_forma', ['vlasnik']],
    'izmeni'      => ['projekat_forma', ['vlasnik']],
    'faza'        => ['faza',           []],
    'termin'      => ['termin',         ['vlasnik']],
    'teren_zavrsi' => ['teren_zavrsi',  []],
    'zastoj'      => ['zastoj',         []],
    'galerija'    => ['galerija',       []],
    'zadaci'         => ['zadaci',          []],
    'zadatak'        => ['zadatak',         []],
    'novi_zadatak'   => ['zadatak_forma',   []],
    'izmeni_zadatak' => ['zadatak_forma',   []],
    'zadatak_zavrsi' => ['zadatak_zavrsi',  []],
    'plan'        => ['plan',           ['vlasnik']],
    'izvestaj'    => ['izvestaj',       ['vlasnik']],
    'podesavanja' => ['podesavanja',    ['vlasnik']],
];

$p = (string) ($_GET['p'] ?? 'danas');

if ($p === 'login') {
    require APP_DIR . '/pages/login.php';
    exit;
}

if (!isset($routes[$p])) {
    redirect(url());
}

[$handler, $roles] = $routes[$p];
$user = $roles ? require_role(...$roles) : require_login();

require APP_DIR . '/pages/' . $handler . '.php';

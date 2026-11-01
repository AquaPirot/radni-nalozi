<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
session_boot();

// ?p=… → [handler u montaza-app/pages/, dozvoljene uloge (prazno = svi prijavljeni)]
$routes = [
    'projekti'    => ['projekti',       []],
    'projekat'    => ['projekat',       []],
    'novi'        => ['projekat_forma', ['vlasnik']],
    'izmeni'      => ['projekat_forma', ['vlasnik']],
    'stavka'      => ['stavka',         []],
    'montaze'     => ['montaze',        []],
    'izvestaj'    => ['izvestaj',       []],
    'podesavanja' => ['podesavanja',    ['vlasnik']],
];

$p = (string) ($_GET['p'] ?? 'projekti');
// stare adrese (obaveštenja, prečice) → nova početna
if (in_array($p, ['danas', 'plan', 'zadaci', 'zadatak', 'pregled', 'termin'], true)) {
    $p = $p === 'plan' || $p === 'termin' ? 'montaze' : 'projekti';
}

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

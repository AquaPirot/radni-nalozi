<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
session_boot();

// Stranice: ključ → [view, dozvoljene uloge (prazno = svi prijavljeni), naslov, aktivni tab]
$routes = [
    'projekti'    => ['projekti',    [],          'Projekti',    'projekti'],
    'plan'        => ['plan',        ['vlasnik'], 'Plan',        'plan'],
    'izvestaj'    => ['izvestaj',    ['vlasnik'], 'Izveštaj',    'izvestaj'],
    'podesavanja' => ['podesavanja', ['vlasnik'], 'Podešavanja', 'podesavanja'],
];

$p = (string) ($_GET['p'] ?? 'projekti');

if ($p === 'login') {
    if (current_user()) {
        redirect(url());
    }
    $error = null;
    $username = '';
    if (is_post()) {
        csrf_check();
        $username = trim((string) ($_POST['username'] ?? ''));
        $error = attempt_login($username, (string) ($_POST['password'] ?? ''), !empty($_POST['remember']));
        if ($error === null) {
            redirect(url());
        }
    }
    view('login', ['title' => 'Prijava', 'error' => $error, 'username' => $username], false);
    exit;
}

if (!isset($routes[$p])) {
    http_response_code(404);
    $p = 'projekti';
}

[$viewName, $roles, $title, $tab] = $routes[$p];
$user = $roles ? require_role(...$roles) : require_login();

view($viewName, ['title' => $title, 'tab' => $tab, 'user' => $user]);

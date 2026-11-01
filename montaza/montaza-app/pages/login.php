<?php
declare(strict_types=1);

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

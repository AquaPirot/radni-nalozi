<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_init.php';
session_boot();

if (!is_post()) {
    http_response_code(405);
    exit;
}
csrf_check();
if (current_user()) {
    log_activity('logout');
}
logout();
redirect(url('login'));

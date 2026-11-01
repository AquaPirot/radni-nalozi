<?php
declare(strict_types=1);

// Učitava se iz svakog ulaznog PHP fajla u web root-u.
// PUBLIC_DIR mora biti definisan pre include-a.

define('APP_DIR', __DIR__);

$configFile = APP_DIR . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Nedostaje config.php — kopiraj config.php.example u config.php i popuni ga.');
}
$GLOBALS['CONFIG'] = require $configFile;

date_default_timezone_set(config('timezone', 'Europe/Belgrade'));
mb_internal_encoding('UTF-8');

// Greške: nikad na ekran u produkciji, uvek u log
$logFile = config('log_file') ?: APP_DIR . '/logs/app.log';
if (!is_dir(dirname($logFile))) {
    @mkdir(dirname($logFile), 0750, true);
}
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', $logFile);
ini_set('display_errors', config('env') === 'dev' ? '1' : '0');

set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (config('env') === 'dev') {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'Došlo je do greške. Pokušaj ponovo.';
    }
});

require APP_DIR . '/lib/helpers.php';
require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/dates.php';
require APP_DIR . '/lib/projects.php';
require APP_DIR . '/lib/upload.php';
require APP_DIR . '/lib/rules.php';
require APP_DIR . '/lib/push.php';
require APP_DIR . '/lib/notify.php';
require APP_DIR . '/lib/production.php';
require APP_DIR . '/lib/field.php';
require APP_DIR . '/lib/stoppages.php';
require APP_DIR . '/lib/project_actions.php';
require APP_DIR . '/lib/tasks.php';
require APP_DIR . '/lib/cards.php';
require APP_DIR . '/lib/migrate.php';

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['CONFIG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

// Automatska nadogradnja baze posle ažuriranja fajlova
if (!defined('MZ_NO_MIGRATE')) {
    migrate_if_needed();
}

<?php
declare(strict_types=1);

// Instalacija kroz browser (posle raspakivanja ZIP-a u folder subdomena):
//   korak 1 — podaci baze → proveri vezu, napravi config.php i tabele
//   korak 2 — nalozi vlasnik / hala / teren
// Posle uspeha fajl briše samog sebe. Odbija rad ako korisnici već postoje.

define('PUBLIC_DIR', __DIR__);

$appDir = null;
foreach ([dirname(__DIR__) . '/montaza-app', dirname(__DIR__, 2) . '/montaza-app', __DIR__ . '/montaza-app'] as $d) {
    if (is_file("$d/bootstrap.php")) {
        $appDir = $d;
        break;
    }
}
if (!$appDir) {
    http_response_code(500);
    exit('Ne mogu da pronađem folder montaza-app. Raspakuj ceo ZIP u folder subdomena.');
}
$configFile = $appDir . '/config.php';

/** Uvozi schema.sql naredbu po naredbu (bez phpMyAdmin-a). */
function install_schema(PDO $pdo, string $file): void
{
    $sql = (string) file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }
}

function h(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function install_page(string $title, string $body): never
{
    ?><!doctype html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title><?= h($title) ?> — Montaža</title>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth">
<main class="auth-box">
    <div class="eyebrow">AG GROUP · MONTAŽA · INSTALACIJA</div>
    <h1 class="h1"><?= h($title) ?></h1>
    <?= $body ?>
</main>
</body>
</html><?php
    exit;
}

// ══ KORAK 1: baza i config.php ══════════════════════════════
if (!is_file($configFile)) {
    session_name('mz_install');
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));

    $in = [
        'host' => trim((string) ($_POST['host'] ?? 'localhost')),
        'name' => trim((string) ($_POST['name'] ?? '')),
        'user' => trim((string) ($_POST['user'] ?? '')),
        'pass' => (string) ($_POST['pass'] ?? ''),
    ];
    $error = null;
    $manual = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
            $error = 'Sesija je istekla — pokušaj ponovo.';
        } elseif ($in['name'] === '' || $in['user'] === '') {
            $error = 'Upiši ime baze i korisnika baze.';
        } else {
            try {
                $pdo = new PDO(
                    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $in['host'], $in['name']),
                    $in['user'], $in['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
                );
                // Zaštita: baza mora biti prazna (ili već Montažina). Nikad ne diramo tuđe tabele.
                $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                if ($existing && !in_array('project_items', $existing, true)) {
                    throw new RuntimeException('Ova baza već sadrži tabele druge aplikacije ('
                        . implode(', ', array_slice($existing, 0, 6)) . (count($existing) > 6 ? '…' : '')
                        . '). Ništa nije menjano. Napravi novu, praznu bazu u cPanel → MySQL Databases i upiši nju.');
                }
                install_schema($pdo, $appDir . '/schema.sql');

                $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
                $base = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                    . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
                $config = [
                    'db'         => ['host' => $in['host'], 'name' => $in['name'], 'user' => $in['user'], 'pass' => $in['pass'], 'charset' => 'utf8mb4'],
                    'base_url'   => $base,
                    'env'        => 'production',
                    'timezone'   => 'Europe/Belgrade',
                    'log_file'   => '',
                    'upload_dir' => '',
                ];
                $php = "<?php\n// MONTAŽA — napravljeno instalacijom " . date('Y-m-d H:i') . "\nreturn " . var_export($config, true) . ";\n";
                if (@file_put_contents($configFile, $php, LOCK_EX) === false) {
                    $manual = $php;
                } else {
                    @chmod($configFile, 0640);
                    header('Location: install.php');
                    exit;
                }
            } catch (PDOException $ex) {
                $msg = $ex->getMessage();
                $error = match (true) {
                    str_contains($msg, 'Access denied') => 'Pogrešan korisnik ili lozinka baze, ili korisnik nije dodat na bazu (cPanel → MySQL Databases → Add User To Database → ALL PRIVILEGES).',
                    str_contains($msg, 'Unknown database') => 'Baza sa tim imenom ne postoji. U cPanel-u ime obično počinje prefiksom naloga, npr. korisnik_montaza.',
                    default => 'Nema veze sa bazom: ' . $msg,
                };
            }
            catch (RuntimeException $ex) {
                $error = $ex->getMessage();
            }
        }
    }

    $body = '';
    if ($error) {
        $body .= '<div class="alert warn">' . h($error) . '</div>';
    }
    if ($manual !== null) {
        $body .= '<div class="alert warn">Veza sa bazom radi i tabele su napravljene, ali server ne dozvoljava upis fajla. '
            . 'U File Manager-u napravi fajl <code>montaza-app/config.php</code>, nalepi tekst ispod i osveži ovu stranicu.</div>'
            . '<textarea class="input mono" rows="16" readonly>' . h($manual) . '</textarea>';
        install_page('Korak 1 od 2 · baza', $body);
    }
    $body .= '<p class="muted">Upiši podatke baze koju si napravio u cPanel → <strong>MySQL Databases</strong>.</p>
    <form method="post" class="stack" autocomplete="off">
        <input type="hidden" name="_csrf" value="' . h($_SESSION['csrf']) . '">
        <label class="field"><span>Ime baze</span><input class="input" name="name" value="' . h($in['name']) . '" placeholder="npr. korisnik_montaza" required autocapitalize="none"></label>
        <label class="field"><span>Korisnik baze</span><input class="input" name="user" value="' . h($in['user']) . '" placeholder="npr. korisnik_montaza" required autocapitalize="none"></label>
        <label class="field"><span>Lozinka baze</span><input class="input" type="password" name="pass" required></label>
        <label class="field"><span>Server baze</span><input class="input" name="host" value="' . h($in['host']) . '"></label>
        <button class="btn primary block" type="submit">Poveži i napravi tabele</button>
    </form>';
    install_page('Korak 1 od 2 · baza', $body);
}

// ══ KORAK 2: nalozi ════════════════════════════════════════
require __DIR__ . '/_init.php';
session_boot();

$errors = [];
try {
    $count = (int) q_val('SELECT COUNT(*) FROM users');
} catch (PDOException $ex) {
    // config.php postoji, ali tabele ne (npr. config napravljen ručno) → uvezi šemu
    try {
        install_schema(db(), APP_DIR . '/schema.sql');
        $count = (int) q_val('SELECT COUNT(*) FROM users');
    } catch (PDOException $ex2) {
        error_log((string) $ex2);
        install_page('Greška', '<div class="alert warn">Nema veze sa bazom. Proveri podatke u <code>montaza-app/config.php</code> ili ga obriši pa ponovo otvori install.php.</div>');
    }
}

$roles = ['vlasnik', 'hala', 'teren'];
$defaults = [
    'vlasnik' => ['Aleksandar', 'aleksandar'],
    'hala'    => ['Oliver', 'oliver'],
    'teren'   => ['Steva', 'steva'],
];
$input = [];
foreach ($roles as $r) {
    $input[$r] = [
        'name'     => trim((string) ($_POST[$r]['name'] ?? $defaults[$r][0])),
        'username' => mb_strtolower(trim((string) ($_POST[$r]['username'] ?? $defaults[$r][1]))),
        'password' => (string) ($_POST[$r]['password'] ?? ''),
    ];
}

if ($count > 0) {
    $self = @unlink(__FILE__);
    install_page('Već instalirano', '<div class="alert ok">Aplikacija je već instalirana.</div>'
        . ($self ? '' : '<p class="muted">Obriši <code>install.php</code> sa servera.</p>')
        . '<a class="btn primary block" href="' . h(url('login')) . '">Idi na prijavu</a>');
}

if (is_post()) {
    csrf_check();
    $seen = [];
    foreach ($roles as $r) {
        $u = $input[$r];
        $label = ROLE_LABELS[$r];
        if ($u['name'] === '') {
            $errors[] = "$label: unesi ime.";
        }
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $u['username'])) {
            $errors[] = "$label: korisničko ime 3–50 znakova (mala slova, brojevi, . _ -).";
        } elseif (isset($seen[$u['username']])) {
            $errors[] = "$label: korisničko ime je već upotrebljeno.";
        }
        $seen[$u['username']] = true;
        if (mb_strlen($u['password']) < PASSWORD_MIN) {
            $errors[] = "$label: lozinka mora imati bar " . PASSWORD_MIN . ' znaka.';
        }
    }
    if (!$errors) {
        db()->beginTransaction();
        if ((int) q_val('SELECT COUNT(*) FROM users FOR UPDATE') === 0) {
            foreach ($roles as $r) {
                $u = $input[$r];
                q('INSERT INTO users (name, username, password_hash, role) VALUES (?, ?, ?, ?)',
                    [$u['name'], $u['username'], password_hash($u['password'], PASSWORD_DEFAULT), $r]);
            }
            log_activity('install', null, null, ['users' => array_column($input, 'username')]);
        }
        db()->commit();

        $deleted = @unlink(__FILE__);
        $cronFile = realpath(APP_DIR . '/cron/dnevni.php') ?: APP_DIR . '/cron/dnevni.php';
        $cron = "5 7 * * *   /usr/local/bin/php $cronFile\n5 18 * * *  /usr/local/bin/php $cronFile --vece";
        install_page('Gotovo', '<div class="alert ok">Aplikacija je instalirana i nalozi su napravljeni.</div>'
            . ($deleted ? '<p class="muted small">install.php je obrisan sa servera.</p>'
                        : '<div class="alert warn">Obriši <code>install.php</code> sa servera (File Manager → Delete).</div>')
            . '<div class="card stack"><strong>Dnevna obaveštenja i rezervna kopija</strong>'
            . '<span class="small muted">cPanel → Cron Jobs → dodaj dva posla (jutro 7:05 i veče 18:05):</span>'
            . '<textarea class="input mono small" rows="3" readonly onclick="this.select()">' . h($cron) . '</textarea></div>'
            . '<a class="btn primary block" href="' . h(url('login')) . '">Idi na prijavu</a>');
    }
}

$body = '';
foreach ($errors as $er) {
    $body .= '<div class="alert warn">' . h($er) . '</div>';
}
$body .= '<p class="muted">Baza je spremna. Napravi naloge — lozinku (' . PASSWORD_MIN . ' cifre) svako upisuje sam.</p>
<form method="post" autocomplete="off" class="stack">' . csrf_field();
foreach ($roles as $r) {
    $body .= '<fieldset class="card"><legend class="mono label">' . h(mb_strtoupper(ROLE_LABELS[$r])) . '</legend>
        <label class="field"><span>Ime</span><input class="input" name="' . $r . '[name]" value="' . h($input[$r]['name']) . '" required></label>
        <label class="field"><span>Korisničko ime</span><input class="input" name="' . $r . '[username]" value="' . h($input[$r]['username']) . '" autocapitalize="none" required></label>
        <label class="field"><span>Lozinka</span><input class="input" type="password" name="' . $r . '[password]" minlength="' . PASSWORD_MIN . '" inputmode="numeric" required></label>
    </fieldset>';
}
$body .= '<button class="btn primary block" type="submit">Napravi naloge</button></form>';
install_page('Korak 2 od 2 · nalozi', $body);

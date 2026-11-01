<?php
declare(strict_types=1);

// Jednokratna instalacija: pravi naloge vlasnik / hala / teren.
// Odbija rad ako korisnici već postoje. OBRIŠI OVAJ FAJL posle instalacije.

require __DIR__ . '/_init.php';
session_boot();

$errors = [];

try {
    $count = (int) q_val('SELECT COUNT(*) FROM users');
} catch (PDOException $ex) {
    error_log((string) $ex);
    $count = -1;
}

$roles = ['vlasnik', 'hala', 'teren'];
$input = [];
foreach ($roles as $r) {
    $input[$r] = [
        'name'     => trim((string) ($_POST[$r]['name'] ?? '')),
        'username' => trim((string) ($_POST[$r]['username'] ?? '')),
        'password' => (string) ($_POST[$r]['password'] ?? ''),
    ];
}

$done = false;
if ($count === 0 && is_post()) {
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
        if (mb_strlen($u['password']) < 8) {
            $errors[] = "$label: lozinka mora imati bar 8 znakova.";
        }
    }
    if (!$errors) {
        db()->beginTransaction();
        // ponovna provera unutar transakcije
        if ((int) q_val('SELECT COUNT(*) FROM users FOR UPDATE') === 0) {
            foreach ($roles as $r) {
                $u = $input[$r];
                q(
                    'INSERT INTO users (name, username, password_hash, role) VALUES (?, ?, ?, ?)',
                    [$u['name'], $u['username'], password_hash($u['password'], PASSWORD_DEFAULT), $r]
                );
            }
            log_activity('install', null, null, ['users' => array_column($input, 'username')]);
        }
        db()->commit();
        $done = true;
    }
}
?>
<!doctype html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>Instalacija — Montaža</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth">
<main class="auth-box">
    <div class="eyebrow">AG GROUP · MONTAŽA</div>
    <h1 class="h1">Instalacija</h1>

    <?php if ($count === -1): ?>
        <div class="alert warn">Nema veze sa bazom ili tabele ne postoje. Proveri <code>config.php</code> i uvezi <code>schema.sql</code> kroz phpMyAdmin.</div>
    <?php elseif ($done): ?>
        <div class="alert ok">Nalozi su napravljeni.</div>
        <p class="muted">Sada <strong>obriši <code>install.php</code></strong> sa servera, pa se prijavi.</p>
        <a class="btn primary block" href="<?= e(url('login')) ?>">Idi na prijavu</a>
    <?php elseif ($count > 0): ?>
        <div class="alert warn">Instalacija je već završena — korisnici postoje. Obriši <code>install.php</code> sa servera.</div>
    <?php else: ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert warn"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" autocomplete="off" class="stack">
            <?= csrf_field() ?>
            <?php foreach ($roles as $r): ?>
                <fieldset class="card">
                    <legend class="mono label"><?= e(strtoupper(ROLE_LABELS[$r])) ?></legend>
                    <label class="field"><span>Ime</span>
                        <input class="input" name="<?= $r ?>[name]" value="<?= e($input[$r]['name']) ?>" required></label>
                    <label class="field"><span>Korisničko ime</span>
                        <input class="input" name="<?= $r ?>[username]" value="<?= e($input[$r]['username']) ?>" autocapitalize="none" required></label>
                    <label class="field"><span>Lozinka (min. 8)</span>
                        <input class="input" type="password" name="<?= $r ?>[password]" minlength="8" required></label>
                </fieldset>
            <?php endforeach; ?>
            <button class="btn primary block" type="submit">Napravi naloge</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>

<?php /** @var ?string $error  @var string $username */ ?>
<!doctype html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0E0F11">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32.png">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Montaža">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="robots" content="noindex">
    <title>Prijava — Montaža</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth">
<main class="auth-box">
    <div class="eyebrow">AG GROUP · REALIZACIJA</div>
    <h1 class="h1">Montaža</h1>

    <?php if ($error): ?>
        <div class="alert warn" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('login')) ?>" class="stack">
        <?= csrf_field() ?>
        <label class="field"><span>Korisničko ime</span>
            <input class="input" name="username" value="<?= e($username) ?>" autocomplete="username" autocapitalize="none" autocorrect="off" required autofocus></label>
        <label class="field"><span>Lozinka</span>
            <input class="input" type="password" name="password" autocomplete="current-password" required></label>
        <label class="check">
            <input type="checkbox" name="remember" value="1" checked>
            <span class="box"></span>
            <span>Zapamti me 30 dana</span>
        </label>
        <button class="btn primary block" type="submit">Prijavi se</button>
    </form>
</main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>

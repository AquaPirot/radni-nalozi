<?php
/** @var string $content  @var string $title */
$u   = current_user();
$tab = $tab ?? '';
$nav = [
    ['projekti', 'Projekti', 'projekti', []],
    ['zadaci',   'Zadaci',   'zadaci',   []],
    ['plan',     'Plan',     'plan',     ['vlasnik']],
    ['izvestaj', 'Izveštaj', 'izvestaj', ['vlasnik']],
];
$taskBadge = $u ? my_open_tasks((int) $u['id']) : 0;
$nav = array_values(array_filter($nav, fn($n) => !$n[3] || ($u && in_array($u['role'], $n[3], true))));
$focus = !empty($focus); // forma: bez donjeg menija i bez podešavanja/odjave
if ($focus) {
    $nav = [];
}
?>
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
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Montaža') ?> — AG GROUP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="app<?= count($nav) > 1 ? ' has-nav' : '' ?>">
    <?php if (empty($hideTop)): ?>
    <div class="eyebrow top-eyebrow"><?= e($eyebrow ?? 'AG GROUP · REALIZACIJA') ?></div>
    <header class="top">
        <h1 class="h1"><?= e($title ?? '') ?></h1>
        <div class="top-actions">
            <?= $topExtra ?? '' ?>
            <?php if (!$focus): ?>
            <?php if ($u && $u['role'] === 'vlasnik'): ?>
                <a class="icon-btn<?= $tab === 'podesavanja' ? ' on' : '' ?>" href="<?= e(url('podesavanja')) ?>" aria-label="Podešavanja"><?= icon('podesavanja') ?></a>
            <?php endif; ?>
            <form method="post" action="<?= e(url() . 'api/logout.php') ?>">
                <?= csrf_field() ?>
                <button class="icon-btn" type="submit" aria-label="Odjava" title="Odjava — <?= e($u['name'] ?? '') ?>"><?= icon('logout') ?></button>
            </form>
            <?php endif; ?>
        </div>
    </header>
    <?php endif; ?>

    <?php foreach (flashes() as $fl): ?>
        <div class="alert <?= e($fl['type']) ?>"><?= e($fl['msg']) ?></div>
    <?php endforeach; ?>

    <main class="main">
        <?= $content ?>
    </main>

    <?php if (count($nav) > 1): ?>
    <nav class="bottom-nav" aria-label="Glavni meni">
        <?php foreach ($nav as [$key, $label, $ic]): ?>
            <a href="<?= e(url($key)) ?>" class="<?= $tab === $key ? 'on' : '' ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
                <span class="nav-ic"><?= icon($ic, 24) ?><?php if ($key === 'zadaci' && $taskBadge): ?><b class="badge"><?= $taskBadge ?></b><?php endif; ?></span><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>

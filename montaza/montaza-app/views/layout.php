<?php
/** @var string $content  @var string $title */
$u   = current_user();
$tab = $tab ?? '';
$nav = [
    ['projekti', 'Projekti', 'projekti', []],
    ['plan',     'Plan',     'plan',     ['vlasnik']],
    ['izvestaj', 'Izveštaj', 'izvestaj', ['vlasnik']],
];
$nav = array_values(array_filter($nav, fn($n) => !$n[3] || ($u && in_array($u['role'], $n[3], true))));
?>
<!doctype html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0E0F11">
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
    <header class="top">
        <div>
            <div class="eyebrow">AG GROUP · REALIZACIJA</div>
            <h1 class="h1"><?= e($title ?? '') ?></h1>
        </div>
        <div class="top-actions">
            <?php if ($u && $u['role'] === 'vlasnik'): ?>
                <a class="icon-btn<?= $tab === 'podesavanja' ? ' on' : '' ?>" href="<?= e(url('podesavanja')) ?>" aria-label="Podešavanja"><?= icon('podesavanja') ?></a>
            <?php endif; ?>
            <form method="post" action="<?= e(url() . 'api/logout.php') ?>">
                <?= csrf_field() ?>
                <button class="icon-btn" type="submit" aria-label="Odjava" title="Odjava — <?= e($u['name'] ?? '') ?>"><?= icon('logout') ?></button>
            </form>
        </div>
    </header>

    <?php foreach (flashes() as $f): ?>
        <div class="alert <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>

    <main class="main">
        <?= $content ?>
    </main>

    <?php if (count($nav) > 1): ?>
    <nav class="bottom-nav" aria-label="Glavni meni">
        <?php foreach ($nav as [$key, $label, $ic]): ?>
            <a href="<?= e(url($key)) ?>" class="<?= $tab === $key ? 'on' : '' ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
                <?= icon($ic, 24) ?><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>

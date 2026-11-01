<?php
/** @var string $content  @var string $title */
$u   = current_user();
$tab = $tab ?? '';
$nav = [
    ['danas',    'Danas',    'home',     []],
    ['projekti', 'Projekti', 'projekti', []],
    ['zadaci',   'Zadaci',   'zadaci',   []],
    ['plan',     'Plan',     'plan',     []],
    ['izvestaj', 'Izveštaj', 'izvestaj', []],
];
$taskBadge = $u ? my_open_tasks((int) $u['id']) : 0;
$nav = array_values(array_filter($nav, fn($n) => !$n[3] || ($u && in_array($u['role'], $n[3], true))));
$focus = !empty($focus); // forma: bez donjeg menija i bez podešavanja/odjave
if ($focus) {
    $nav = [];
}
?>
<!doctype html>
<html lang="sr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0E0F11">
    <script>(function(){var t='dark';try{t=localStorage.getItem('mz-theme')||'dark'}catch(e){}document.documentElement.setAttribute('data-theme',t)})()</script>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32.png">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Montaža">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <?php if (push_supported()): ?><meta name="vapid-key" content="<?= e(push_vapid()[0]) ?>"><?php endif; ?>
    <title><?= e($title ?? 'Montaža') ?> — AG GROUP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/montaza.css')) ?>">
</head>
<body>
<div class="app<?= count($nav) > 1 ? ' has-nav' : '' ?>">
    <?php if (empty($hideTop)): ?>
    <div class="eyebrow top-eyebrow"><?= e($eyebrow ?? 'AG GROUP · REALIZACIJA') ?></div>
    <header class="top">
        <h1 class="h1"><?= e($title ?? '') ?></h1>
        <div class="top-actions">
            <?= $topExtra ?? '' ?>
            <?php if (!$focus && $u): ?>
            <details class="menu" data-menu>
                <summary class="icon-btn avatar" aria-label="Meni — <?= e($u['name']) ?>"><span><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span><i class="push-dot" data-push-dot hidden></i></summary>
                <div class="menu-panel" role="menu">
                    <div class="menu-who"><strong><?= e($u['name']) ?></strong><span><?= e(ROLE_LABELS[$u['role']] ?? $u['role']) ?></span></div>
                    <button class="menu-row" type="button" data-push-toggle hidden><?= icon('bell', 20) ?><span>Obaveštenja</span><em data-push-state>isključena</em></button>
                    <div class="menu-row static"><?= icon('sun', 20) ?><span>Tema</span></div>
                    <div class="theme-seg" role="group" aria-label="Tema">
                        <button type="button" data-theme-set="dark">Tamna</button><button type="button" data-theme-set="light">Svetla</button><button type="button" data-theme-set="system">Kao telefon</button>
                    </div>
                    <?php if ($u['role'] === 'vlasnik'): ?>
                        <a class="menu-row<?= $tab === 'podesavanja' ? ' on' : '' ?>" href="<?= e(url('podesavanja')) ?>"><?= icon('podesavanja', 20) ?><span>Podešavanja</span></a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url() . 'api/logout.php') ?>">
                        <?= csrf_field() ?>
                        <button class="menu-row" type="submit"><?= icon('logout', 20) ?><span>Odjava</span></button>
                    </form>
                </div>
            </details>
            <?php endif; ?>
        </div>
    </header>
    <?php endif; ?>

    <?php if (!$focus && empty($hideTop)): ?>
    <div class="push-banner" data-install-banner hidden>
        <div><strong>Instaliraj Montažu kao aplikaciju</strong><span class="small muted" data-install-text>Ikonica u spisku aplikacija, otvara se bez adresne trake — kao prava aplikacija.</span></div>
        <div class="btn-row">
            <button class="btn primary sm" type="button" data-install-go><?= icon('download', 18) ?>Instaliraj</button>
            <button class="btn ghost sm" type="button" data-install-dismiss>Ne sada</button>
        </div>
    </div>
    <div class="push-banner" data-push-banner hidden>
        <div><strong>Obaveštenja na ovom telefonu</strong><span class="small muted" data-push-banner-text>Zastoji, nove montaže, zadaci — odmah na ekranu telefona.</span></div>
        <div class="btn-row">
            <button class="btn primary sm" type="button" data-push-enable><?= icon('bell', 18) ?>Uključi</button>
            <button class="btn ghost sm" type="button" data-push-dismiss>Ne sada</button>
        </div>
    </div>
    <?php endif; ?>

    <?php foreach (flashes() as $fl): ?>
        <div class="alert <?= e($fl['type']) ?>"><?= e($fl['msg']) ?></div>
    <?php endforeach; ?>

    <main class="main">
        <?= $content ?>
    </main>

    <?php if (count($nav) > 1): ?>
    <nav class="bottom-nav" aria-label="Glavni meni">
        <div class="side-brand">AG GROUP<strong>Montaža</strong></div>
        <?php foreach ($nav as [$key, $label, $ic]): ?>
            <a href="<?= e(url($key)) ?>" class="<?= $tab === $key ? 'on' : '' ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
                <span class="nav-ic"><?= icon($ic, 24) ?><?php if ($key === 'zadaci' && $taskBadge): ?><b class="badge"><?= $taskBadge ?></b><?php endif; ?></span><span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
</div>
<script src="<?= e(asset('js/montaza.js')) ?>" defer></script>
</body>
</html>

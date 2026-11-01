<?php /** @var array $user @var string $f @var array $filters @var array $counts @var string $search @var array $groups */ ?>
<form id="search-row" class="search-row" method="get" action="<?= e(url()) ?>"<?= $search === '' ? ' hidden' : '' ?>>
    <input type="hidden" name="p" value="projekti">
    <input class="input" type="search" name="q" value="<?= e($search) ?>" placeholder="Kupac, mesto, adresa, telefon" enterkeyhint="search">
    <?php if ($search !== ''): ?><a class="btn ghost" href="<?= e(url('projekti')) ?>">Poništi</a><?php endif; ?>
</form>

<?php if ($search === ''): ?>
<nav class="segments">
    <?php foreach ($filters as $key => $label): ?>
        <a class="seg<?= $f === $key ? ' on' : '' ?>" href="<?= e(url('projekti', ['f' => $key])) ?>"><?= e($label) ?> · <?= (int) $counts[$key] ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if (!$groups): ?>
    <div class="empty"><?= icon('projekti', 32) ?>
        <p><?= $search !== '' ? 'Nema rezultata za „' . e($search) . '".' : ['aktivni' => 'Nema aktivnih projekata.', 'reklamacije' => 'Nema otvorenih reklamacija.', 'zavrseni' => 'Još nema završenih projekata.'][$f] ?></p>
    </div>
<?php endif; ?>

<div class="cards-grid">
<?php foreach ($groups as $g): ?>
    <section class="group">
        <header class="group-head"><span class="<?= $g['title'] === 'MONTAŽA KASNI' ? 'warn' : '' ?>"><?= e($g['title']) ?></span><span><?= count($g['items']) ?></span></header>
        <?php foreach ($g['items'] as $b) { echo project_card($b); } ?>
    </section>
<?php endforeach; ?>
</div>

<?php if ($user['role'] === 'vlasnik'): ?>
    <a class="fab" href="<?= e(url('novi')) ?>"><?= icon('plus') ?><span>Novi projekat</span></a>
<?php endif; ?>

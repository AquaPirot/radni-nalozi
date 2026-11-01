<?php /** @var array $user @var string $f @var array $filters @var array $counts @var string $search @var array $groups @var array $taskCount */ ?>
<form id="search-row" class="search-row" method="get" action="<?= e(url()) ?>"<?= $search === '' ? ' hidden' : '' ?>>
    <input type="hidden" name="p" value="projekti">
    <input type="hidden" name="f" value="<?= e($f) ?>">
    <input class="input" type="search" name="q" value="<?= e($search) ?>" placeholder="Kupac, mesto, adresa ili šifra" enterkeyhint="search">
    <?php if ($search !== ''): ?><a class="btn ghost" href="<?= e(url('projekti', ['f' => $f])) ?>">Poništi</a><?php endif; ?>
</form>

<nav class="segments scroll">
    <?php foreach ($filters as $key => $label): ?>
        <a class="seg<?= $f === $key ? ' on' : '' ?>" href="<?= e(url('projekti', array_filter(['f' => $key, 'q' => $search]))) ?>"><?= e($label) ?> · <?= (int) $counts[$key] ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$groups): ?>
    <div class="empty"><?= icon('projekti', 32) ?>
        <p><?= $search !== '' ? 'Nema rezultata za „' . e($search) . '".' : ['aktivni' => 'Nema aktivnih projekata.', 'nacrti' => 'Nema nacrta.', 'reklamacije' => 'Nema servisa ni reklamacija.', 'zavrseno' => 'Još nema završenih projekata.', 'arhiva' => 'Arhiva je prazna.'][$f] ?></p>
    </div>
<?php endif; ?>

<div class="cards-grid">
<?php foreach ($groups as $g): ?>
    <section class="group">
        <header class="group-head"><span><?= e($g['title']) ?><?php if ($g['sub']): ?><small><?= e($g['sub']) ?></small><?php endif; ?></span><span><?= count($g['items']) ?></span></header>
        <?php foreach ($g['items'] as $b) { echo pcard($b, $user, $taskCount[(int) $b['p']['id']] ?? 0); } ?>
    </section>
<?php endforeach; ?>
</div>

<?php if ($user['role'] === 'vlasnik'): ?>
    <a class="fab" href="<?= e(url('novi')) ?>"><?= icon('plus') ?><span>Novi projekat</span></a>
<?php endif; ?>

<?php /** @var string $monthLabel @var string $nextLabel @var bool $isCurrent @var array $completed @var array $byType @var int $opened @var int $montaze @var mixed $avgDays @var int $stops @var int $lostDays @var array $byReason @var array $itemSpeed @var array $carry @var string $prevM @var string $nextM @var string $m */
$maxReason = $byReason ? max($byReason) : 1;
?>
<nav class="month-nav">
    <a class="icon-btn flat" href="<?= e(url('izvestaj', ['m' => $prevM])) ?>" aria-label="Prethodni mesec"><?= icon('back') ?></a>
    <strong><?= e($monthLabel) ?></strong>
    <?php if ($nextM <= date('Y-m')): ?><a class="icon-btn flat" href="<?= e(url('izvestaj', ['m' => $nextM])) ?>" aria-label="Sledeći mesec"><?= icon('chevron') ?></a><?php else: ?><span class="icon-btn flat" aria-hidden="true"></span><?php endif; ?>
</nav>

<div class="tiles">
    <div class="tile accent"><span>Završeno</span><strong><?= count($completed) ?></strong><span><?= count($completed) === 1 ? 'projekat' : 'projekata' ?><?= !empty($byType['reklamacija']) ? ' · ' . $byType['reklamacija'] . ' rekl.' : '' ?></span></div>
    <div class="tile"><span>Montaža urađeno</span><strong><?= $montaze ?></strong><span>otvoreno novih: <?= $opened ?></span></div>
    <div class="tile"><span>Prosečno trajanje</span><strong><?= $avgDays !== null ? (int) round((float) $avgDays) : '—' ?></strong><span>dana, od otvaranja do kraja</span></div>
    <div class="tile"><span>Zastoji</span><strong class="<?= $stops ? 'warn' : '' ?>"><?= $stops ?></strong><span><?= $lostDays ?> izgubljenih dana</span></div>
</div>

<?php if ($byReason): ?>
    <section class="stack">
        <h2 class="sec-head"><span>ZASTOJI PO RAZLOGU</span></h2>
        <?php foreach ($byReason as $r => $n): ?>
            <div class="bar-row"><div class="row-between"><span><?= e(REASON_LABELS[$r] ?? $r) ?></span><span class="mono muted"><?= $n ?></span></div><div class="bar"><i style="width: <?= round($n / $maxReason * 100) ?>%"></i></div></div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($itemSpeed): ?>
    <section class="stack">
        <h2 class="sec-head"><span>KOLIKO TRAJE (PROSEK DANA)</span></h2>
        <div class="list">
            <?php foreach ($itemSpeed as $s): ?><div class="list-row"><span><?= e(item_label($s)) ?> <span class="muted small">(<?= (int) $s['n'] ?>)</span></span><span class="mono"><?= e(fmt_days($s['days'])) ?></span></div><?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="stack">
    <h2 class="sec-head"><span>ZAVRŠENI PROJEKTI</span><span><?= count($completed) ?></span></h2>
    <?php if ($completed): ?>
        <div class="list">
            <?php foreach ($completed as $p): ?><a class="list-row" href="<?= e(url('projekat', ['id' => $p['id']])) ?>"><span><strong><?= e($p['customer_name']) ?></strong> <span class="muted"><?= e($p['city']) ?></span></span><span class="mono small muted"><?= e(fmt_dm(substr($p['completed_at'], 0, 10))) ?></span></a><?php endforeach; ?>
        </div>
    <?php else: ?><p class="small muted">Nema završenih projekata u ovom mesecu.</p><?php endif; ?>
</section>

<details class="rcard">
    <summary><span><strong><?= $isCurrent ? 'Prelazi u ' . e($nextLabel) : 'Prešlo u ' . e($nextLabel) ?></strong><span class="muted"><?= count($carry) ?> projekata</span></span><?= icon('chevron') ?></summary>
    <ul><?php foreach ($carry as $p): ?><li><a class="row-between" href="<?= e(url('projekat', ['id' => $p['id']])) ?>"><span><?= e($p['customer_name']) ?> <span class="muted small"><?= e($p['city']) ?></span></span><span class="mono small muted"><?= $p['install_date'] ? 'MONTAŽA ' . e(fmt_dm($p['install_date'])) : '' ?></span></a></li><?php endforeach; ?></ul>
</details>

<a class="btn ghost block" href="<?= e(url('izvestaj', ['m' => $m, 'print' => 1])) ?>" target="_blank" rel="noopener"><?= icon('download', 20) ?>PDF sa fotografijama</a>

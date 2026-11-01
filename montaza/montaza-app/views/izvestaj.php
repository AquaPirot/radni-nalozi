<?php /** @var string $monthLabel @var string $nextLabel @var bool $isCurrent @var array $completed @var int $pergole @var int $reklamacije @var mixed $avgDays @var array $stops @var int $lostDays @var array $byReason @var array $itemSpeed @var array $carry @var int $carryLate @var array $paid @var array $tasks @var string $prevM @var string $nextM @var string $m */
$maxReason = $byReason ? max($byReason) : 1;
$slow = $itemSpeed[0] ?? null;
$canNext = $nextM <= date('Y-m');
?>
<nav class="month-nav">
    <a class="icon-btn flat" href="<?= e(url('izvestaj', ['m' => $prevM])) ?>" aria-label="Prethodni mesec"><?= icon('back') ?></a>
    <strong><?= e($monthLabel) ?></strong>
    <?php if ($canNext): ?>
        <a class="icon-btn flat" href="<?= e(url('izvestaj', ['m' => $nextM])) ?>" aria-label="Sledeći mesec"><?= icon('chevron') ?></a>
    <?php else: ?><span class="icon-btn flat" aria-hidden="true"></span><?php endif; ?>
</nav>

<div class="tiles">
    <div class="tile accent">
        <span>Završeno</span><strong><?= count($completed) ?></strong>
        <span><?= count($completed) === 1 ? 'projekat' : 'projekata' ?><?= $reklamacije ? ' · ' . $reklamacije . ' rekl.' : '' ?></span>
    </div>
    <div class="tile">
        <span>Napravljeno</span><strong><?= $pergole ?></strong><span><?= $pergole === 1 ? 'pergola' : 'pergola' ?></span>
    </div>
    <div class="tile">
        <span>Otvaranje → kraj</span><strong><?= $avgDays !== null ? (int) round((float) $avgDays) : '—' ?></strong><span>dana prosek (novi)</span>
    </div>
    <div class="tile">
        <span>Zastoji</span><strong class="<?= $stops ? 'warn' : '' ?>"><?= count($stops) ?></strong><span><?= $lostDays ?> izgubljenih dana</span>
    </div>
</div>

<?php if ($byReason): ?>
    <section class="stack">
        <h2 class="h2">Zastoji po razlogu</h2>
        <?php foreach ($byReason as $r => $n): ?>
            <div class="bar-row">
                <div class="row-between"><span><?= e(REASON_LABELS[$r]) ?></span><span class="mono muted"><?= $n ?></span></div>
                <div class="bar"><i style="width: <?= round($n / $maxReason * 100) ?>%"></i></div>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<details class="rcard">
    <summary><span><strong>Najsporija stavka</strong><span class="muted"><?= $slow ? e(ITEM_LABELS[$slow['kind']]) . ' · prosek ' . e(number_format((float) $slow['days'], 1, ',', '')) . ' dana' : 'nema podataka' ?></span></span><?= icon('chevron') ?></summary>
    <?php if ($itemSpeed): ?>
        <ul><?php foreach ($itemSpeed as $s): ?><li class="row-between"><span><?= e(ITEM_LABELS[$s['kind']]) ?> <span class="muted small">(<?= (int) $s['n'] ?>)</span></span><span class="mono"><?= e(number_format((float) $s['days'], 1, ',', '')) ?> d</span></li><?php endforeach; ?></ul>
    <?php endif; ?>
</details>

<details class="rcard">
    <summary><span><strong><?= $isCurrent ? 'Prelazi u ' . e($nextLabel) : 'Prešlo u ' . e($nextLabel) ?></strong><span class="muted"><?= count($carry) ?> projekata<?= $carryLate ? ' · ' . $carryLate . ' kasni' : '' ?></span></span><?= icon('chevron') ?></summary>
    <?php if ($carry): ?>
        <ul><?php foreach ($carry as $p): ?><li><a class="row-between" href="<?= e(url('projekat', ['id' => $p['id']])) ?>"><span><?= e($p['customer_name']) ?> <span class="muted small"><?= e($p['city']) ?></span></span><span class="mono small<?= $p['deadline'] && days_late($p['deadline']) && $p['status'] === 'aktivan' ? ' warn' : ' muted' ?>"><?= $p['deadline'] ? 'ROK ' . e(fmt_dm($p['deadline'])) : '' ?></span></a></li><?php endforeach; ?></ul>
    <?php endif; ?>
</details>

<div class="rcard static">
    <span><strong>Naplaćeno na terenu</strong><span class="muted"><?= (int) $paid['n'] ?> naplata<?= (int) $paid['diffs'] ? ' · ' . (int) $paid['diffs'] . ' sa razlikom' : '' ?></span></span>
    <strong class="mono"><?= e(fmt_money((string) $paid['total'])) ?></strong>
</div>

<div class="rcard static">
    <span><strong>Zadaci</strong><span class="muted"><?= (int) $tasks['done'] ?> završeno<?= (int) $tasks['done_late'] ? ' · ' . (int) $tasks['done_late'] . ' posle roka' : '' ?><?= (int) $tasks['open_late'] ? ' · ' . (int) $tasks['open_late'] . ' kasni' : '' ?></span></span>
    <a class="small accent" href="<?= e(url('zadaci')) ?>">Otvori</a>
</div>

<a class="btn ghost block" href="<?= e(url('izvestaj', ['m' => $m, 'print' => 1])) ?>" target="_blank" rel="noopener"><?= icon('download', 20) ?>Preuzmi PDF sa fotografijama</a>

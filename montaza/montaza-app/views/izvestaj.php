<?php /** @var string $monthLabel @var string $nextLabel @var bool $isCurrent @var array $completed @var array $byWork @var int $pergole @var int $released @var mixed $avgDays @var array $carry @var int $carryLate @var array $prod @var int $phasesDone @var int $visits @var array $visitKinds @var array $visitResults @var int $revisits @var int $mounted @var int $openFollowups @var int $stops @var int $stopsOpen @var int $lostDays @var array $byReason @var float $received @var array $receivedBy @var float $handed @var float $cashHeld @var float $remaining @var array $tasks @var string $prevM @var string $nextM @var string $m */
$maxReason = $byReason ? max($byReason) : 1;
$canNext = $nextM <= date('Y-m');
$money = fn($v) => fmt_money((string) $v);
$n = fn($v) => (int) $v;
?>
<nav class="month-nav">
    <a class="icon-btn flat" href="<?= e(url('izvestaj', ['m' => $prevM])) ?>" aria-label="Prethodni mesec"><?= icon('back') ?></a>
    <strong><?= e($monthLabel) ?></strong>
    <?php if ($canNext): ?>
        <a class="icon-btn flat" href="<?= e(url('izvestaj', ['m' => $nextM])) ?>" aria-label="Sledeći mesec"><?= icon('chevron') ?></a>
    <?php else: ?><span class="icon-btn flat" aria-hidden="true"></span><?php endif; ?>
</nav>

<div class="tiles">
    <div class="tile accent"><span>Završeno projekata</span><strong><?= count($completed) ?></strong>
        <span><?= e(implode(' · ', array_map(fn($k, $c) => $c . ' ' . mb_strtolower(WORK_TYPES[$k] ?? $k), array_keys($byWork), $byWork)) ?: '—') ?></span></div>
    <div class="tile"><span>Pergola / sistema</span><strong><?= $pergole ?></strong><span>u završenim projektima</span></div>
    <div class="tile"><span>Montirano</span><strong><?= $mounted ?></strong><span><?= $visits ?> izlazaka · <?= $revisits ?> ponovnih</span></div>
    <div class="tile"><span>Naplaćeno</span><strong class="mono" style="font-size:30px"><?= e($money($received)) ?></strong><span>ostalo za naplatu <?= e($money($remaining)) ?></span></div>
</div>

<section class="stack">
    <h2 class="sec-head"><span>PROJEKTI</span></h2>
    <div class="rcard static"><span><strong>Pušteno u realizaciju</strong><span class="muted">u ovom mesecu</span></span><strong class="mono"><?= $released ?></strong></div>
    <div class="rcard static"><span><strong>Prosečno trajanje</strong><span class="muted">od puštanja do završetka (novi)</span></span><strong class="mono"><?= $avgDays !== null ? (int) round((float) $avgDays) . ' d' : '—' ?></strong></div>
    <details class="rcard">
        <summary><span><strong><?= $isCurrent ? 'Prelazi u ' . e($nextLabel) : 'Prešlo u ' . e($nextLabel) ?></strong><span class="muted"><?= count($carry) ?> projekata<?= $carryLate ? ' · <span class="warn">' . $carryLate . ' kasni</span>' : '' ?></span></span><?= icon('chevron') ?></summary>
        <?php if ($carry): ?>
            <ul><?php foreach ($carry as $p): ?><li><a class="row-between" href="<?= e(url('projekat', ['id' => $p['id']])) ?>"><span><?= e($p['customer_name']) ?> <span class="muted small"><?= e($p['city']) ?></span></span><span class="mono small<?= $p['deadline'] && $p['deadline'] < date('Y-m-d') && $p['status'] === 'aktivan' ? ' warn' : ' muted' ?>"><?= $p['deadline'] ? 'ROK ' . e(fmt_dm($p['deadline'])) : '' ?></span></a></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </details>
</section>

<section class="stack">
    <h2 class="sec-head"><span>PROIZVODNJA PO TIPU SISTEMA</span><span><?= $phasesDone ?> faza završeno</span></h2>
    <?php if (!$prod): ?><p class="ok-note">Ovog meseca nije završen nijedan sistem.</p><?php endif; ?>
    <?php foreach ($prod as $type => $x): $slow = $x['phases'][0] ?? null; ?>
        <details class="rcard">
            <summary><span><strong><?= e($type) ?></strong>
                <span class="muted"><?= $x['systems'] ? $x['qty'] . ' kom gotovo (' . $x['systems'] . ' ' . ($x['systems'] === 1 ? 'stavka' : 'stavki') . ')' : 'u izradi · ' . array_sum(array_column($x['phases'], 'n')) . ' faza završeno' ?><?= $x['days'] ? ' · prosek ' . e(fmt_days(array_sum($x['days']) / count($x['days']))) . ' d' : '' ?><?= $slow ? ' · najsporije: ' . e($slow['name']) : '' ?></span></span><?= icon('chevron') ?></summary>
            <?php if ($x['phases']): ?>
                <ul><?php foreach ($x['phases'] as $ph): ?><li class="row-between"><span><?= e($ph['name']) ?> <span class="muted small">(<?= (int) $ph['n'] ?>)</span></span><span class="mono"><?= e(fmt_days($ph['days'])) ?> d</span></li><?php endforeach; ?></ul>
            <?php else: ?><p class="pad small muted">Nema merenih faza (uneto naknadno).</p><?php endif; ?>
        </details>
    <?php endforeach; ?>
</section>

<section class="stack">
    <h2 class="sec-head"><span>MONTAŽA I TEREN</span></h2>
    <div class="rcard static"><span><strong>Izlazaka na teren</strong><span class="muted"><?= e(implode(' · ', array_map(fn($k, $c) => $c . ' ' . mb_strtolower(VISIT_KINDS[$k] ?? $k), array_keys($visitKinds), $visitKinds)) ?: 'nema') ?></span></span><strong class="mono"><?= $visits ?></strong></div>
    <div class="rcard static"><span><strong>Ishod</strong><span class="muted"><?= e(implode(' · ', array_map(fn($k, $c) => $c . ' ' . mb_strtolower(VISIT_RESULTS[$k] ?? $k), array_keys($visitResults), $visitResults)) ?: '—') ?></span></span></div>
    <div class="rcard static"><span><strong>Ponovni dolasci</strong><span class="muted">dorade i povratak na isti posao<?= $openFollowups ? ' · ' . $openFollowups . ' još otvoreno' : '' ?></span></span><strong class="mono<?= $revisits ? ' warn' : '' ?>"><?= $revisits ?></strong></div>
</section>

<section class="stack">
    <h2 class="sec-head"><span>ZASTOJI</span><span class="<?= $stopsOpen ? 'warn' : '' ?>"><?= $stops ?> prijavljeno<?= $stopsOpen ? ' · ' . $stopsOpen . ' otvoreno' : '' ?> · <?= $lostDays ?> d</span></h2>
    <?php if (!$byReason): ?><p class="ok-note"><?= icon('check', 18) ?> Bez zastoja ovog meseca.</p><?php endif; ?>
    <?php foreach ($byReason as $r => $c): ?>
        <div class="bar-row">
            <div class="row-between"><span><?= e(REASON_LABELS[$r] ?? $r) ?></span><span class="mono muted"><?= $c ?></span></div>
            <div class="bar"><i style="width: <?= round($c / $maxReason * 100) ?>%"></i></div>
        </div>
    <?php endforeach; ?>
</section>

<section class="stack">
    <h2 class="sec-head"><span>NAPLATA</span></h2>
    <div class="rcard static"><span><strong>Primljeno od kupaca</strong><span class="muted"><?= e(implode(' · ', array_map(fn($k, $x) => mb_strtolower(METHOD_LABELS[$k] ?? $k) . ' ' . fmt_money((string) $x['s']), array_keys($receivedBy), $receivedBy)) ?: 'nema uplata') ?></span></span><strong class="mono"><?= e($money($received)) ?></strong></div>
    <div class="rcard static"><span><strong>Gotovina predata firmi</strong><span class="muted">potvrđeno u ovom mesecu</span></span><strong class="mono"><?= e($money($handed)) ?></strong></div>
    <a class="rcard static" href="<?= e(url('pregled', ['f' => 'naplata'])) ?>"><span><strong>Gotovina kod radnika</strong><span class="muted">primljeno, a nije predato — danas</span></span><strong class="mono<?= $cashHeld > 0 ? ' warn' : '' ?>"><?= e($money($cashHeld)) ?></strong></a>
    <a class="rcard static" href="<?= e(url('pregled', ['f' => 'naplata'])) ?>"><span><strong>Ostalo za naplatu</strong><span class="muted">dospelo — danas</span></span><strong class="mono<?= $remaining > 0 ? ' warn' : '' ?>"><?= e($money($remaining)) ?></strong></a>
</section>

<section class="stack">
    <h2 class="sec-head"><span>ZADACI</span><a href="<?= e(url('zadaci')) ?>">otvori ›</a></h2>
    <div class="rcard static"><span><strong><?= $n($tasks['done']) ?> završeno od <?= $n($tasks['created']) ?> zadatih</strong>
        <span class="muted"><?= e(implode(' · ', array_filter([
            $n($tasks['done_late']) ? $n($tasks['done_late']) . ' posle roka' : '',
            $n($tasks['reopened']) ? $n($tasks['reopened']) . ' ponovo otvoreno' : '',
            $n($tasks['open_late']) ? $n($tasks['open_late']) . ' kasni' : '',
            $n($tasks['not_accepted']) ? $n($tasks['not_accepted']) . ' nije preuzeto' : '',
        ])) ?: 'sve u roku') ?></span></span></div>
</section>

<a class="btn ghost block" href="<?= e(url('izvestaj', ['m' => $m, 'print' => 1])) ?>" target="_blank" rel="noopener"><?= icon('download', 20) ?>Preuzmi PDF sa fotografijama</a>

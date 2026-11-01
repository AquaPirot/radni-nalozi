<?php /** @var array $user @var array $days @var array $noDate */
$isOwner = $user['role'] === 'vlasnik';
$dayTitle = function (string $key): string {
    if ($key === '0000') {
        return 'KASNI — DATUM JE PROŠAO';
    }
    $d = new DateTimeImmutable($key);
    $diff = (int) today()->diff($d)->format('%r%a');
    $name = mb_strtoupper(DAYS_FULL[(int) $d->format('w')]) . ', ' . $d->format('j') . '. ' . mb_strtoupper(MONTHS_FULL[(int) $d->format('n')]);
    return ($diff === 0 ? 'DANAS · ' : ($diff === 1 ? 'SUTRA · ' : '')) . $name;
};
?>
<?php if (!$days && !$noDate): ?>
    <div class="empty"><?= icon('plan', 32) ?><p>Nema zakazanih montaža.</p></div>
<?php endif; ?>

<?php foreach ($days as $key => $list): ?>
    <section class="stack">
        <h2 class="sec-head"><span class="<?= $key === '0000' ? 'warn' : '' ?>"><?= e($dayTitle($key)) ?></span><span><?= count($list) ?></span></h2>
        <?php foreach ($list as $b): $p = $b['p']; [$line, $cls] = status_line($b); $tel = tel_url($p); $map = maps_url($p); ?>
            <article class="mcard">
                <div class="row-between">
                    <span class="time-chip mono"><?= $key === '0000' ? e(fmt_dm($p['install_date'])) . ' · ' : '' ?><?= $p['install_time'] ? e(substr($p['install_time'], 0, 5)) : 'ceo dan' ?></span>
                    <span class="status <?= e($cls) ?>"><i></i><?= e($line) ?></span>
                </div>
                <a class="mcard-name" href="<?= e(url('projekat', ['id' => $p['id']])) ?>"><strong><?= e($p['customer_name']) ?></strong><?= $p['type'] === 'reklamacija' ? ' <span class="tag warn">Reklamacija</span>' : '' ?></a>
                <div class="mcard-addr"><?= e(implode(', ', array_filter([$p['address'], $p['city']])) ?: 'adresa nije upisana') ?></div>
                <?php if ($p['phone']): ?><div class="mono small muted"><?= e($p['phone']) ?></div><?php endif; ?>
                <div class="btn-row">
                    <?php if ($tel): ?><a class="btn ghost sm grow" href="<?= e($tel) ?>"><?= icon('phone', 18) ?>Pozovi</a><?php endif; ?>
                    <?php if ($map): ?><a class="btn ghost sm grow" href="<?= e($map) ?>" target="_blank" rel="noopener"><?= icon('nav', 18) ?>Navigacija</a><?php endif; ?>
                    <?php if ($b['nalog']): ?><a class="btn ghost sm grow" href="<?= e(file_url($b['nalog'])) ?>" target="_blank" rel="noopener"><?= icon('file', 18) ?>Nalog</a><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>

<?php if ($noDate): ?>
    <section class="stack">
        <h2 class="sec-head"><span>BEZ DATUMA</span><span><?= count($noDate) ?></span></h2>
        <div class="list">
            <?php foreach ($noDate as $b): $p = $b['p']; [$line, $cls] = status_line($b); ?>
                <a class="list-row" href="<?= e(url('projekat', ['id' => $p['id']])) ?>#montaza">
                    <span><strong><?= e($p['customer_name']) ?></strong> <span class="muted"><?= e($p['city']) ?></span><br><span class="small status <?= e($cls) ?>"><i></i><?= e($line) ?></span></span>
                    <?php if ($isOwner): ?><span class="chip">Odredi dan</span><?php else: ?><?= icon('chevron') ?><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php /** @var string $f @var array $lists @var array $items @var array $cash @var float $paySum */ ?>
<nav class="segments scroll">
    <?php foreach ($lists as $k => $label): ?><a class="seg<?= $f === $k ? ' on' : '' ?>" href="<?= e(url('pregled', ['f' => $k])) ?>"><?= e($label) ?></a><?php endforeach; ?>
</nav>
<?php if ($f === 'naplata'): ?><p class="mono">Ukupno dospelo: <strong><?= e(fmt_money((string) $paySum)) ?></strong></p><?php endif; ?>

<?php if ($items): ?>
    <div class="reacts">
        <?php foreach ($items as $x): ?>
            <a class="react<?= !empty($x['hot']) ? ' hot' : '' ?>" href="<?= e($x['url']) ?>"><span class="grow"><strong><?= e($x['title']) ?></strong><span><?= e($x['why']) ?></span></span><?= icon('chevron') ?></a>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="ok-note"><?= icon('check', 18) ?> <?= ['odluka' => 'Ništa ne čeka tvoju odluku.', 'zastoj' => 'Nema otvorenih zastoja.', 'kasni' => 'Ništa ne kasni.', 'naplata' => 'Nema dospelih naplata.'][$f] ?></p>
<?php endif; ?>

<?php if ($f === 'naplata'): ?>
    <section class="stack">
        <h2 class="sec-head"><span>GOTOVINA KOD RADNIKA (NIJE PREDATA)</span></h2>
        <?php if (!$cash): ?><p class="small muted">Sva gotovina je predata firmi.</p><?php endif; ?>
        <?php foreach ($cash as $uid => $rows): $sum = array_sum(array_map(fn($r) => (float) $r['amount'], $rows)); ?>
            <form class="card stack" method="post" action="<?= e(url() . 'api/akcija.php') ?>">
                <?= csrf_field() ?><?= once_field() ?>
                <input type="hidden" name="action" value="cash_handover"><input type="hidden" name="id" value="0"><input type="hidden" name="back" value="<?= e(here()) ?>">
                <div class="row-between"><strong><?= e(user_name($uid)) ?></strong><strong class="mono"><?= e(fmt_money((string) $sum)) ?></strong></div>
                <?php foreach ($rows as $r): ?>
                    <label class="check row"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" checked><span class="box"></span>
                        <span class="grow"><?= e($r['customer_name']) ?> · <?= e(fmt_dm(substr($r['received_at'], 0, 10))) ?></span><span class="mono"><?= e(fmt_money($r['amount'])) ?></span></label>
                <?php endforeach; ?>
                <button class="btn primary block" type="submit">Primio sam označeno</button>
            </form>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

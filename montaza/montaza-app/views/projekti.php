<?php
/** @var array $user @var string $f @var array $filters @var array $counts @var string $search @var array $groups @var array $itemsBy @var array $stopBy */
$labels = ['aktivni' => 'Aktivni', 'reklamacije' => 'Reklamacije', 'zavrseno' => 'Završeno'];
$showPay = can_see_payment($user);
?>
<form id="search-row" class="search-row" method="get" action="<?= e(url()) ?>"<?= $search === '' ? ' hidden' : '' ?>>
    <input type="hidden" name="p" value="projekti">
    <input type="hidden" name="f" value="<?= e($f) ?>">
    <input class="input" type="search" name="q" value="<?= e($search) ?>" placeholder="Kupac, mesto ili šifra" enterkeyhint="search">
    <?php if ($search !== ''): ?>
        <a class="btn ghost" href="<?= e(url('projekti', ['f' => $f])) ?>">Poništi</a>
    <?php endif; ?>
</form>

<nav class="segments">
    <?php foreach ($filters as $key): ?>
        <a class="seg<?= $f === $key ? ' on' : '' ?>" href="<?= e(url('projekti', array_filter(['f' => $key, 'q' => $search]))) ?>">
            <?= e($labels[$key]) ?> · <?= (int) ($counts[$key] ?? 0) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!$groups): ?>
    <div class="empty">
        <?= icon('projekti', 32) ?>
        <p><?= $search !== '' ? 'Nema rezultata za „' . e($search) . '".' : ($f === 'zavrseno' ? 'Još nema završenih projekata.' : 'Nema aktivnih projekata.') ?></p>
    </div>
<?php endif; ?>

<?php foreach ($groups as $g): ?>
    <section class="group">
        <header class="group-head">
            <span><?= e($g['title']) ?><?php if (!empty($g['sub'])): ?><small><?= e($g['sub']) ?></small><?php endif; ?></span>
            <?php if ($g['right'] !== ''): ?><span class="<?= $g['over'] ? 'warn' : '' ?>"><?= e($g['right']) ?></span><?php endif; ?>
        </header>
        <?php foreach ($g['items'] as $p):
            $items = $itemsBy[$p['id']] ?? [];
            [$tag, $tagCls] = type_tag($p);
            [$line, $lineCls] = project_status_line($p, $items, $stopBy[$p['id']] ?? null);
            $sub = implode(' · ', array_filter([$p['city'], short_desc($p['description'])]));
        ?>
            <a class="pcard" href="<?= e(url('projekat', ['id' => $p['id']])) ?>">
                <div class="pcard-top">
                    <div class="pcard-name">
                        <strong><?= e($p['customer_name']) ?></strong>
                        <span class="tag <?= e($tagCls) ?>"><?= e($tag) ?></span>
                    </div>
                    <?php if ($p['deadline']): ?>
                        <span class="mono muted rok<?= days_late($p['deadline']) && $p['status'] === 'aktivan' ? ' warn' : '' ?>">ROK <?= e(fmt_dm($p['deadline'])) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($sub !== ''): ?><div class="pcard-sub"><?= e($sub) ?></div><?php endif; ?>
                <div class="steps" aria-label="<?= items_done($items) ?> od <?= count($items) ?> stavki gotovo">
                    <?php foreach ($items as $it): ?><span class="<?= step_class($it) ?>"></span><?php endforeach; ?>
                </div>
                <div class="pcard-foot">
                    <span class="status <?= e($lineCls) ?>"><i></i><?= e($line) ?></span>
                    <?php if (!empty($taskCount[$p['id']])): $n = $taskCount[$p['id']]; ?>
                        <span class="paytag task-count"><?= icon('zadaci', 18) ?><?= $n ?> <?= $n === 1 ? 'ZADATAK' : 'ZADATKA' ?></span>
                    <?php endif; ?>
                    <?php if ($showPay && $p['payment_when'] !== 'ne' && $p['status'] === 'aktivan'): ?>
                        <span class="paytag<?= $p['payment_confirmed_at'] ? ' ok' : '' ?>"><?= icon('cash', 18) ?><?= $p['payment_when'] === 'pre' ? 'PRE' : 'POSLE' ?></span>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>

<?php if ($user['role'] === 'vlasnik'): ?>
    <a class="fab" href="<?= e(url('novi')) ?>"><?= icon('plus') ?><span>Novi projekat</span></a>
<?php endif; ?>

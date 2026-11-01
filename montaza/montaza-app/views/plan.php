<?php
/** @var int $capacity @var array $weeks @var array $unscheduled @var array $overdue @var array $itemsBy @var array $stopBy */
$sub = function (array $p): string {
    $bits = [$p['city']];
    if ($p['type'] === 'reklamacija') { $bits[] = 'reklamacija'; }
    if ($p['type'] === 'zapocet') { $bits[] = 'započet'; }
    if ($p['has_steel']) { $bits[] = 'čelik'; }
    if ($p['payment_when'] === 'pre' && !$p['payment_confirmed_at']) { $bits[] = 'naplata pre'; }
    return implode(' · ', array_filter($bits));
};
$row = function (array $p) use ($sub, $itemsBy, $stopBy): string {
    [, $cls] = project_status_line($p, $itemsBy[$p['id']] ?? [], $stopBy[$p['id']] ?? null);
    if ($p['type'] === 'reklamacija' && $cls !== 'warn' && $p['status'] === 'aktivan') { $cls = 'info'; }
    $late = $p['status'] === 'aktivan' && days_late($p['deadline']);
    return '<a class="plan-row" href="' . e(url('rasporedi', ['id' => $p['id']])) . '">'
        . '<span class="status ' . e($cls) . '"><i></i></span>'
        . '<span class="plan-name"><strong>' . e($p['customer_name']) . '</strong> <span class="muted">' . e($sub($p)) . '</span></span>'
        . ($p['deadline'] ? '<span class="mono small' . ($late ? ' warn' : ' muted') . '">ROK ' . e(fmt_dm($p['deadline'])) . '</span>' : '')
        . '</a>';
};
?>
<div class="row-between plan-cap"><span></span><span class="eyebrow">KAPACITET <?= $capacity ?> MONTAŽA NEDELJNO</span></div>

<?php if ($unscheduled): ?>
    <section class="unsched">
        <div class="row-between"><strong>Nije raspoređeno</strong><span class="warn mono"><?= count($unscheduled) ?></span></div>
        <div class="unsched-chips">
            <?php foreach ($unscheduled as $p): ?>
                <a class="uchip" href="<?= e(url('rasporedi', ['id' => $p['id']])) ?>"><i class="<?= $p['type'] === 'reklamacija' ? 'info' : '' ?>"></i><?= e($p['customer_name']) ?> · <?= e(mb_strtolower(TYPE_LABELS[$p['type']])) ?></a>
            <?php endforeach; ?>
        </div>
        <p class="small muted">Dodirni projekat i izaberi nedelju</p>
    </section>
<?php endif; ?>

<?php if ($overdue): ?>
    <section class="week">
        <div class="row-between"><div><div class="h2 warn">Prošle nedelje</div><div class="mono small muted">NIJE ZAVRŠENO</div></div><span class="mono warn"><?= count($overdue) ?></span></div>
        <div class="plan-list"><?php foreach ($overdue as $p) { echo $row($p); } ?></div>
    </section>
<?php endif; ?>

<?php foreach ($weeks as $w):
    $n = count($w['projects']);
    $over = $n > $capacity;
?>
    <section class="week">
        <div class="row-between">
            <div>
                <div class="h2"><?= e(week_label($w['mon'])) ?></div>
                <div class="mono small muted"><?= e(mb_strtoupper(week_relative($w['mon']))) ?></div>
            </div>
            <span class="mono<?= $over ? ' warn' : '' ?>"><?= $n ?> / <?= $capacity ?></span>
        </div>
        <div class="cap-bar">
            <?php for ($i = 0; $i < max($capacity, $n); $i++): ?>
                <span class="<?= $i < $n ? ($i >= $capacity ? 'warn' : 'done') : '' ?>"></span>
            <?php endfor; ?>
        </div>
        <?php if ($w['projects']): ?>
            <div class="plan-list"><?php foreach ($w['projects'] as $p) { echo $row($p); } ?></div>
        <?php else: ?>
            <p class="small muted">Slobodno.</p>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

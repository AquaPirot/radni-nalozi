<?php /** @var array $user @var string $f @var array $filters @var array $counts @var array $tasks */ ?>
<nav class="segments">
    <?php foreach ($filters as $key => $label): ?>
        <a class="seg<?= $f === $key ? ' on' : '' ?>" href="<?= e(url('zadaci', ['f' => $key])) ?>"><?= e($label) ?> · <?= (int) $counts[$key] ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$tasks): ?>
    <div class="empty">
        <?= icon('check', 32) ?>
        <p><?= $f === 'zavrseno' ? 'Nema završenih zadataka u poslednjih 60 dana.' : 'Nema otvorenih zadataka.' ?></p>
    </div>
<?php endif; ?>

<div class="stack">
<?php foreach ($tasks as $t):
    [$line, $lineCls] = task_status_line($t);
    $mine = (int) $t['assigned_to'] === (int) $user['id'];
    $who = $mine ? ($t['created_by'] && (int) $t['created_by'] !== (int) $user['id'] ? 'od: ' . $t['created_name'] : 'sebi') : 'za: ' . $t['assigned_name'];
?>
    <article class="pcard tcard<?= $t['status'] === 'zavrseno' ? ' done' : '' ?>">
        <a class="tcard-link" href="<?= e(url('zadatak', ['id' => $t['id']])) ?>" aria-label="<?= e($t['title']) ?>"></a>
        <div class="pcard-top">
            <div class="pcard-name">
                <strong><?= e($t['title']) ?></strong>
                <?php if ($t['priority'] === 'hitno' && $t['status'] !== 'zavrseno'): ?><span class="tag warn">HITNO</span><?php endif; ?>
                <?php if ($t['requires_photo'] && $t['status'] !== 'zavrseno'): ?><span class="tag"><?= icon('camera', 14) ?></span><?php endif; ?>
            </div>
            <?php if ($t['due_date']): ?><span class="mono muted rok<?= $t['status'] !== 'zavrseno' && days_late($t['due_date']) ? ' warn' : '' ?>">ROK <?= e(fmt_dm($t['due_date'])) ?></span><?php endif; ?>
        </div>
        <div class="pcard-sub"><?= e(implode(' · ', array_filter([$who, $t['project_name']]))) ?></div>
        <div class="pcard-foot">
            <span class="status <?= e($lineCls) ?>"><i></i><?= e($line) ?></span>
            <?php if (can_act_task($user, $t)): ?>
                <?php if ($t['requires_photo']): ?>
                    <a class="btn primary sm" href="<?= e(url('zadatak_zavrsi', ['id' => $t['id']])) ?>"><?= icon('camera', 18) ?>Završi</a>
                <?php else: ?>
                    <form method="post" action="<?= e(url() . 'api/zadatak.php') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="done">
                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                        <input type="hidden" name="back" value="<?= e($f) ?>">
                        <button class="btn primary sm" type="submit"><?= icon('check', 18) ?>Završeno</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
</div>

<a class="fab" href="<?= e(url('novi_zadatak')) ?>"><?= icon('plus') ?><span>Zadatak</span></a>

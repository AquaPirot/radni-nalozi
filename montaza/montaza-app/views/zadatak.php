<?php /** @var array $user @var array $task @var array $photos @var ?array $stop */
$t = $task;
[$line, $lineCls] = task_status_line($t);
?>
<section class="stack">
    <h2 class="task-title"><?= e($t['title']) ?></h2>
    <?php if ($t['description']): ?><p class="text-2"><?= nl2br(e($t['description'])) ?></p><?php endif; ?>
    <span class="status <?= e($lineCls) ?>"><i></i><?= e($line) ?></span>
</section>

<div class="facts">
    <div class="fact"><span>Kome</span><strong><?= e($t['assigned_name']) ?></strong></div>
    <div class="fact"><span>Rok</span><strong class="mono<?= $t['status'] !== 'zavrseno' && days_late($t['due_date']) ? ' warn' : '' ?>"><?= $t['due_date'] ? e(fmt_dm($t['due_date'])) : '—' ?></strong></div>
    <div class="fact"><span>Zadao</span><strong><?= e($t['created_name'] ?? '—') ?></strong></div>
</div>

<?php if ($t['project_id']): ?>
    <section class="list">
        <a class="list-row" href="<?= e(url('projekat', ['id' => $t['project_id']])) ?>"><span>Projekat: <strong><?= e($t['project_name']) ?></strong> <span class="mono muted small"><?= e($t['project_code']) ?></span></span><?= icon('chevron') ?></a>
    </section>
<?php endif; ?>

<?php if ($stop): ?>
    <div class="alert warn"><strong>Zastoj:</strong> <?= e(REASON_LABELS[$stop['reason']]) ?><?= $stop['note'] ? ' — ' . e($stop['note']) : '' ?></div>
<?php endif; ?>

<?php if ($t['note']): ?>
    <section class="card"><div class="label">NAPOMENA PRI ZAVRŠETKU</div><p><?= nl2br(e($t['note'])) ?></p></section>
<?php endif; ?>

<?php if ($photos): ?>
    <div class="gallery">
        <?php foreach ($photos as $f): ?>
            <a href="<?= e(file_url((int) $f['id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(file_url((int) $f['id'], true)) ?>" alt="" loading="lazy"></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (can_act_task($user, $t)): ?>
    <div class="stack">
        <?php if ($t['status'] === 'zastoj'): ?>
            <form method="post" action="<?= e(url() . 'api/zadatak.php') ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="start"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <button class="btn ghost block" type="submit">Nastavi rad</button>
            </form>
        <?php elseif ($t['status'] === 'ceka'): ?>
            <form method="post" action="<?= e(url() . 'api/zadatak.php') ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="start"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <button class="btn ghost block" type="submit">Počinjem</button>
            </form>
        <?php endif; ?>
        <div class="btn-row">
            <a class="btn primary grow" href="<?= e(url('zadatak_zavrsi', ['id' => $t['id']])) ?>"><?= icon($t['requires_photo'] ? 'camera' : 'check', 20) ?><?= $t['requires_photo'] ? 'Završi + foto' : 'Završi' ?></a>
            <?php if ($t['status'] !== 'zastoj'): ?><a class="btn warn" href="<?= e(url('zadatak_zastoj', ['id' => $t['id']])) ?>">Zastoj</a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<p class="mono muted small">Zadato <?= e((new DateTimeImmutable($t['created_at']))->format('j.n.Y. H:i')) ?><?= $t['completed_at'] ? ' · završeno ' . e((new DateTimeImmutable($t['completed_at']))->format('j.n.Y. H:i')) : '' ?></p>

<?php
/** @var int $waitingCount @var array $user @var array $myTasks @var int $myTasksTotal @var array $myItems @var array $readyIds @var array $stops @var array $late @var int $unscheduled @var array $given @var array $week @var array $weekItems @var array $weekStops @var DateTimeImmutable $mon */
$isOwner = $user['role'] === 'vlasnik';
$nothing = !$myTasks && !$myItems && !$stops && !$late && !$given;
?>
<div class="counters">
    <a class="counter<?= $myTasksTotal ? ' on' : '' ?>" href="<?= e(url('zadaci')) ?>"><strong><?= $myTasksTotal ?></strong><span>moji zadaci</span></a>
    <a class="counter" href="#na-meni"><strong><?= count($myItems) ?></strong><span><?= $isOwner ? 'nalozi' : 'na redu' ?></span></a>
    <a class="counter<?= $stops ? ' warn' : '' ?>" href="#zastoji"><strong><?= count($stops) ?></strong><span>zastoji</span></a>
    <?php if ($isOwner): ?>
        <a class="counter<?= $late ? ' warn' : '' ?>" href="#kasni"><strong><?= count($late) ?></strong><span>kasni</span></a>
    <?php else: ?>
        <a class="counter" href="#nedelja"><strong><?= count($week) ?></strong><span>montaže</span></a>
    <?php endif; ?>
</div>

<?php if ($nothing): ?>
    <div class="empty"><?= icon('check', 32) ?><p>Sve je čisto — nema ničeg na tebi.</p></div>
<?php endif; ?>

<?php if ($stops): ?>
    <section class="stack" id="zastoji">
        <h2 class="sec-head"><span>ZASTOJI</span><span class="warn"><?= count($stops) ?></span></h2>
        <div class="list">
            <?php foreach ($stops as $s): ?>
                <a class="list-row" href="<?= e($s['task_id'] ? url('zadatak', ['id' => $s['task_id']]) : url('projekat', ['id' => $s['project_id']])) ?>">
                    <span><span class="strong"><?= e($s['task_title'] ?: $s['customer_name']) ?></span>
                        <br><span class="small status warn"><i></i><?= e(($s['item_kind'] ? ITEM_LABELS[$s['item_kind']] . ': ' : '') . ($s['note'] ?: REASON_LABELS[$s['reason']])) ?></span></span><?= icon('chevron') ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($myTasks): ?>
    <section class="stack">
        <h2 class="sec-head"><span>MOJI ZADACI</span><a href="<?= e(url('zadaci')) ?>"><?= $myTasksTotal > count($myTasks) ? 'svi (' . $myTasksTotal . ')' : 'svi' ?> ›</a></h2>
        <div class="list">
            <?php foreach ($myTasks as $t): [$tl, $tc] = task_status_line($t); ?>
                <div class="list-row task-row">
                    <a class="grow" href="<?= e(url('zadatak', ['id' => $t['id']])) ?>">
                        <span class="strong"><?= e($t['title']) ?></span><?php if ($t['priority'] === 'hitno'): ?> <span class="tag warn">HITNO</span><?php endif; ?>
                        <br><span class="small status <?= e($tc) ?>"><i></i><?= e($tl) ?><?= $t['due_date'] ? ' · rok ' . e(fmt_dm($t['due_date'])) : '' ?><?= $t['created_by'] && (int) $t['created_by'] !== (int) $user['id'] ? ' · od ' . e($t['created_name']) : '' ?></span>
                    </a>
                    <?php if ($t['requires_photo']): ?>
                        <a class="btn primary sm" href="<?= e(url('zadatak_zavrsi', ['id' => $t['id']])) ?>" aria-label="Završi uz sliku"><?= icon('camera', 18) ?></a>
                    <?php else: ?>
                        <form method="post" action="<?= e(url() . 'api/zadatak.php') ?>">
                            <?= csrf_field() ?><input type="hidden" name="action" value="done"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="back" value="danas">
                            <button class="btn primary sm" type="submit" aria-label="Završeno"><?= icon('check', 18) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($myItems): ?>
    <section class="stack" id="na-meni">
        <h2 class="sec-head"><span><?= $isOwner ? 'NALOZI ZA IZDAVANJE' : 'NA REDU U PROJEKTIMA' ?></span><span><?= count($myItems) ?></span></h2>
        <div class="list">
            <?php foreach ($myItems as $it):
                $ready = isset($readyIds[$it['id']]);
                $cls = match (true) { $it['status'] === 'zastoj' => 'warn', $it['status'] === 'u_toku' => 'live', $ready => 'ok', default => 'idle' };
                $txt = match (true) {
                    $it['status'] === 'zastoj' => 'zastoj',
                    $it['status'] === 'u_toku' => 'u toku',
                    $ready => 'na redu',
                    default => 'čeka prethodne',
                };
                $when = $it['install_week_start'] ? week_short($it['install_week_start']) : ($it['due_date'] ? 'rok ' . fmt_dm($it['due_date']) : '');
                $pay = $it['kind'] === 'montaza' && $it['payment_when'] === 'pre' && !$it['payment_confirmed_at'] ? ' · naplata PRE' : '';
            ?>
                <a class="list-row" href="<?= e(url('projekat', ['id' => $it['project_id']])) ?>">
                    <span><span class="strong"><?= e(ITEM_LABELS[$it['kind']]) ?></span> <span class="muted">· <?= e($it['customer_name']) ?><?= $it['city'] ? ', ' . e($it['city']) : '' ?></span>
                        <br><span class="small status <?= $cls ?>"><i></i><?= e($txt . ($when ? ' · ' . $when : '') . $pay) ?></span></span><?= icon('chevron') ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if ($waitingCount): ?><p class="small muted">+ još <?= $waitingCount ?> <?= $waitingCount === 1 ? 'stavka čeka' : 'stavki čeka' ?> da se završe prethodni koraci.</p><?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($late): ?>
    <section class="stack" id="kasni">
        <h2 class="sec-head"><span>KASNI</span><span class="warn"><?= count($late) ?></span></h2>
        <div class="list">
            <?php foreach ($late as $p): ?>
                <a class="list-row" href="<?= e(url('projekat', ['id' => $p['id']])) ?>">
                    <span><span class="strong"><?= e($p['customer_name']) ?></span> <span class="muted"><?= e($p['city']) ?></span>
                        <br><span class="small status warn"><i></i>rok <?= e(fmt_dm($p['deadline'])) ?> · kasni <?= days_late($p['deadline']) ?> d</span></span><?= icon('chevron') ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($given): ?>
    <section class="stack">
        <h2 class="sec-head"><span>ZADAO SAM</span><a href="<?= e(url('zadaci', ['f' => $isOwner ? 'otvoreni' : 'zadao'])) ?>">svi ›</a></h2>
        <div class="list">
            <?php foreach ($given as $t): [$tl, $tc] = task_status_line($t); ?>
                <a class="list-row" href="<?= e(url('zadatak', ['id' => $t['id']])) ?>">
                    <span><span class="strong"><?= e($t['title']) ?></span>
                        <br><span class="small status <?= e($tc) ?>"><i></i><?= e($t['assigned_name']) ?> · <?= e($tl) ?></span></span><?= icon('chevron') ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($isOwner && $unscheduled): ?>
    <a class="rcard static" href="<?= e(url('plan')) ?>"><span><strong>Nije raspoređeno</strong><span class="muted"><?= $unscheduled ?> projekata čeka nedelju montaže</span></span><?= icon('chevron') ?></a>
<?php endif; ?>

<section class="stack" id="nedelja">
    <h2 class="sec-head"><span>MONTAŽE OVE NEDELJE · <?= e(mb_strtoupper(week_short($mon))) ?></span><span><?= count($week) ?></span></h2>
    <?php if ($week): ?>
        <div class="list">
            <?php foreach ($week as $p): [$line, $cls] = project_status_line($p, $weekItems[$p['id']] ?? [], $weekStops[$p['id']] ?? null); ?>
                <a class="list-row" href="<?= e(url('projekat', ['id' => $p['id']])) ?>">
                    <span><span class="strong"><?= e($p['customer_name']) ?></span> <span class="muted"><?= e($p['city']) ?></span>
                        <br><span class="small status <?= e($cls) ?>"><i></i><?= e($line) ?></span></span><?= icon('chevron') ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="small muted">Nema montaža ove nedelje.</p>
    <?php endif; ?>
</section>

<?php if ($isOwner): ?>
    <a class="fab" href="<?= e(url('novi')) ?>"><?= icon('plus') ?><span>Novi projekat</span></a>
<?php else: ?>
    <a class="fab" href="<?= e(url('novi_zadatak')) ?>"><?= icon('plus') ?><span>Zadatak</span></a>
<?php endif; ?>

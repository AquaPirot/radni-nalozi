<?php /** @var array $user @var array $task @var array $photos @var ?array $stop @var array $comments */
$t = $task;
[$line, $lineCls] = task_status_line($t);
$mine = (int) $t['assigned_to'] === (int) $user['id'];
$canAct = can_act_task($user, $t);
$canEdit = can_edit_task($user, $t);
$open = $t['status'] !== 'zavrseno';
$late = $open && $t['due_date'] && days_late($t['due_date']);
$commentPhotos = array_filter(array_column($comments, 'file_id'));
$photos = array_values(array_filter($photos, fn($f) => !in_array($f['id'], $commentPhotos)));
?>
<section class="stack">
    <h2 class="task-title"><?= e($t['title']) ?></h2>
    <?php if ($t['description']): ?><p class="text-2"><?= nl2br(e($t['description'])) ?></p><?php endif; ?>
    <span class="status <?= e($lineCls) ?>"><i></i><?= e($line) ?></span>
</section>

<div class="facts">
    <div class="fact"><span>Kome</span><strong><?= e($t['assigned_name']) ?></strong></div>
    <div class="fact"><span>Rok</span><strong class="mono<?= $late ? ' warn' : '' ?>"><?= $t['due_date'] ? e(fmt_dm($t['due_date']) . ($t['due_time'] ? ' ' . substr($t['due_time'], 0, 5) : '')) : 'Bez roka' ?></strong></div>
    <div class="fact"><span>Zadao</span><strong><?= e($t['created_name'] ?? '—') ?></strong></div>
</div>

<?php if ($t['project_id']): ?>
    <section class="list">
        <a class="list-row" href="<?= e(url('projekat', ['id' => $t['project_id']])) ?>"><span>Projekat: <strong><?= e($t['project_name']) ?></strong> <span class="mono muted small"><?= e($t['project_code']) ?></span></span><?= icon('chevron') ?></a>
    </section>
<?php endif; ?>

<?php if ($stop): ?>
    <div class="problem"><strong>Zastoj</strong><span><?= e(stoppage_text($stop)) ?></span>
        <?php if ($canAct || $canEdit): ?><?= action_button('stoppage_resolve', (int) $stop['id'], 'Rešeno', 'btn ghost sm', [], 'Zastoj je rešen?') ?><?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($t['note'] && !$open): ?>
    <section class="card"><div class="label">NAPOMENA PRI ZAVRŠETKU</div><p><?= nl2br(e($t['note'])) ?></p></section>
<?php endif; ?>

<?php if ($photos): ?>
    <div class="gallery">
        <?php foreach ($photos as $f): ?>
            <a href="<?= e(file_url((int) $f['id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(file_url((int) $f['id'], true)) ?>" alt="" loading="lazy"></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($canAct): ?>
    <div class="stack">
        <?php if ($mine && !$t['accepted_at']): ?>
            <?= action_button('task_accept', (int) $t['id'], 'Preuzimam zadatak', 'btn ghost block', [], null, 'check') ?>
        <?php endif; ?>
        <?php if ($t['status'] === 'zastoj'): ?>
            <?= action_button('task_start', (int) $t['id'], 'Nastavi rad (zastoj rešen)', 'btn ghost block', [], null, 'play') ?>
        <?php elseif ($t['status'] === 'ceka'): ?>
            <?= action_button('task_start', (int) $t['id'], 'Počinjem', 'btn ghost block', [], null, 'play') ?>
        <?php endif; ?>
        <div class="btn-row">
            <?php if ($t['requires_photo']): ?>
                <a class="btn primary grow" href="<?= e(url('zadatak_zavrsi', ['id' => $t['id']])) ?>"><?= icon('camera', 20) ?>Završi + foto</a>
            <?php else: ?>
                <a class="btn primary grow" href="<?= e(url('zadatak_zavrsi', ['id' => $t['id']])) ?>"><?= icon('check', 20) ?>Završi</a>
            <?php endif; ?>
            <?php if ($t['status'] !== 'zastoj'): ?><a class="btn warn" href="<?= e(url('zastoj', ['task' => $t['id']])) ?>">Prijavi problem</a><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!$open && $canEdit): ?>
    <details class="card">
        <summary class="row-between"><span>Nije dobro urađeno? Otvori ponovo</span><?= icon('chevron') ?></summary>
        <form class="stack" method="post" action="<?= e(url() . 'api/akcija.php') ?>">
            <?= csrf_field() ?><?= once_field() ?>
            <input type="hidden" name="action" value="task_reopen"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="back" value="<?= e(here()) ?>">
            <label class="field"><span>Razlog (obavezno)</span><textarea class="input" name="reason" rows="2" required placeholder="Npr. račun nije čitljiv"></textarea></label>
            <button class="btn warn block" type="submit">Otvori ponovo</button>
        </form>
    </details>
<?php endif; ?>

<section class="stack">
    <h2 class="sec-head"><span>KOMENTARI I ISTORIJA</span></h2>
    <ol class="hist">
        <li><span class="mono muted small"><?= e((new DateTimeImmutable($t['created_at']))->format('j.n. H:i')) ?></span> <strong><?= e($t['created_name'] ?? '—') ?></strong> zadao zadatak → <?= e($t['assigned_name']) ?></li>
        <?php foreach ($comments as $c): ?>
            <li class="<?= $c['kind'] === 'komentar' ? 'cmt' : ($c['kind'] === 'ponovo_otvoren' ? 'warn' : 'sys') ?>">
                <span class="mono muted small"><?= e((new DateTimeImmutable($c['created_at']))->format('j.n. H:i')) ?></span>
                <strong><?= e($c['user_name'] ?? '—') ?></strong>
                <?= $c['kind'] === 'ponovo_otvoren' ? 'ponovo otvorio: ' : '' ?><?= nl2br(e($c['body'])) ?>
                <?php if ($c['file_id']): ?><a class="cmt-img" href="<?= e(file_url((int) $c['file_id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(file_url((int) $c['file_id'], true)) ?>" alt="" loading="lazy"></a><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
    <form class="stack card" method="post" action="<?= e(url() . 'api/akcija.php') ?>" data-autosave="task-cmt-<?= (int) $t['id'] ?>">
        <?= csrf_field() ?><?= once_field() ?>
        <input type="hidden" name="action" value="task_comment"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="back" value="<?= e(here()) ?>">
        <label class="field"><span>Komentar</span><textarea class="input" name="body" rows="2" placeholder="Pitanje, dopuna, stanje…"></textarea></label>
        <?= uploader('file_id', 'Slika uz komentar', false, 'foto', false, 'opciono', []) ?>
        <button class="btn ghost block" type="submit">Pošalji komentar</button>
    </form>
</section>

<?php if ($canEdit): ?>
    <?= action_button('task_delete', (int) $t['id'], 'Obriši zadatak', 'btn link warn block', [], 'Obrisati zadatak „' . $t['title'] . '"?', 'trash') ?>
<?php endif; ?>

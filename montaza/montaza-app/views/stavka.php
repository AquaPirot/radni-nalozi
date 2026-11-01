<?php /** @var array $user @var array $it @var array $p @var array $photos @var int $min @var ?string $error @var string $note */
$done = $it['status'] === 'zavrseno';
$can = can_act_item($user, $it);
?>
<?php if ($error): ?><div class="alert warn" role="alert"><?= e($error) ?></div><?php endif; ?>

<?php if ($done): ?>
    <p class="status ok big"><i></i>Završeno <?= e(fmt_dm(substr((string) $it['completed_at'], 0, 10))) ?><?= $it['completed_by'] ? ' · ' . e(user_name((int) $it['completed_by'])) : '' ?></p>
    <?php if ($it['note']): ?><section class="card"><div class="label">NAPOMENA</div><p><?= nl2br(e($it['note'])) ?></p></section><?php endif; ?>
<?php endif; ?>

<?php if ($photos): ?>
    <div class="gallery">
        <?php foreach ($photos as $f): ?><a href="<?= e(file_url((int) $f['id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(file_url((int) $f['id'], true)) ?>" alt="" loading="lazy"></a><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!$done && $can): ?>
    <form class="stack" method="post" action="<?= e(url('stavka', ['id' => $it['id']])) ?>" data-gate data-confirm="Potvrđuješ da je <?= e(mb_strtolower(item_label($it))) ?> završen(a)?">
        <?= csrf_field() ?><?= once_field() ?>
        <?php if ($it['kind'] !== 'nalog'): ?>
            <?= uploader('foto_ids', 'Slike', true, 'foto', $min > 0, $min > 0 ? 'najmanje ' . $min : 'opciono') ?>
        <?php endif; ?>
        <label class="field"><span>Napomena <span class="small muted">(opciono)</span></span>
            <textarea class="input" name="note" rows="3" placeholder="<?= item_is_field($it['kind']) ? 'npr. kupac zadovoljan, video poslat' : 'npr. sve po nalogu' ?>"><?= e($note) ?></textarea></label>
        <div class="sticky-foot">
            <p class="small muted center" data-missing></p>
            <button class="btn primary block" type="submit"><?= icon('check', 20) ?>Završeno</button>
        </div>
    </form>
<?php elseif (!$done): ?>
    <p class="muted">Ovu stavku završava <?= e(['vlasnik' => 'vlasnik', 'hala' => 'hala', 'teren' => 'teren'][$it['responsible']]) ?>.</p>
<?php endif; ?>

<?php if ($done && $user['role'] === 'vlasnik'): ?>
    <form method="post" action="<?= e(url() . 'api/akcija.php') ?>" data-confirm="Vratiti stavku na „čeka"? (npr. greškom označeno)">
        <?= csrf_field() ?><?= once_field() ?><input type="hidden" name="action" value="item_reset"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>"><input type="hidden" name="back" value="/?p=projekat&amp;id=<?= (int) $p['id'] ?>">
        <button class="btn link block" type="submit">Vrati na „čeka"</button>
    </form>
<?php endif; ?>

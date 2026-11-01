<?php
/** @var array $user @var array $project @var array $item @var array $files @var ?array $lokacija @var ?string $warning @var ?string $error @var string $note */
$p = $project;
$isMontaza = $item['kind'] === 'montaza';
$hasPosle = (bool) array_filter($files, fn($f) => $f['kind'] === 'posle');
$needPay = is_payment_item($item) && $p['payment_when'] !== 'ne';
$paid = (bool) $p['payment_confirmed_at'];
$minPhotos = match (true) {
    $isMontaza => (int) setting('min_photos_montaza', '3'),
    $item['responsible'] === 'hala' => (int) setting('min_photos_hala', '1'),
    default => 0,
};
$payLabel = $p['payment_when'] === 'pre' ? 'Naplaćeno pre početka' : 'Naplaćeno posle montaže';
?>
<?php if ($error): ?><div class="alert warn" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($warning): ?><div class="alert warn"><?= e($warning) ?> Montaža može, ali proveri da li je sve spakovano.</div><?php endif; ?>

<form class="stack done-form" method="post" enctype="multipart/form-data" action="<?= e(url('zavrsi', ['item' => $item['id']])) ?>"
      data-gate data-min-photos="<?= $minPhotos ?>" data-existing-photos="<?= count($files) ?>"
      data-need-posle="<?= $isMontaza && !$hasPosle ? '1' : '' ?>">
    <?= csrf_field() ?>

    <?php if ($isMontaza): ?>
        <div class="row-between">
            <h2 class="h2">Pre i posle · isti ugao</h2>
            <span class="mono small warn" data-show-if-missing="posle">FALI POSLE</span>
        </div>
        <div class="prepost">
            <div class="pp-tile">
                <span class="pp-label mono">PRE</span>
                <?php if ($lokacija): ?><img src="<?= e(file_url((int) $lokacija['id'], true)) ?>" alt="Mesto montaže pre"><?php else: ?><?= icon('image', 32) ?><?php endif; ?>
            </div>
            <?php if ($hasPosle): ?>
                <div class="pp-tile"><span class="pp-label mono">POSLE</span>
                    <?php foreach ($files as $f): if ($f['kind'] === 'posle'): ?><img src="<?= e(file_url((int) $f['id'], true)) ?>" alt="Posle"><?php break; endif; endforeach; ?>
                </div>
            <?php else: ?>
                <label class="pp-tile pp-shoot" data-preview>
                    <input type="file" name="posle" accept="image/*" capture="environment" data-resize data-count>
                    <span class="dz-empty"><?= icon('camera', 30) ?><strong>Slikaj POSLE</strong><span class="small muted">Stani na isto mesto kao na PRE slici</span></span>
                    <img class="dz-img" alt="" hidden>
                </label>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <h2 class="h2">Fotografije<?= $minPhotos ? ' <span class="muted small">(min. ' . $minPhotos . ')</span>' : ' <span class="muted small">(opciono)</span>' ?></h2>
    <?php endif; ?>

    <div class="thumbs" data-thumbs>
        <?php foreach ($files as $f): if ($f['kind'] === 'foto'): ?>
            <a class="thumb" href="<?= e(file_url((int) $f['id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(file_url((int) $f['id'], true)) ?>" alt=""></a>
        <?php endif; endforeach; ?>
        <label class="thumb add" aria-label="Dodaj fotografije">
            <input type="file" name="fotos[]" accept="image/*" multiple data-resize data-count data-add-photos><?= icon('plus', 26) ?>
        </label>
    </div>

    <?php if ($needPay || $isMontaza): ?>
        <h2 class="h2">Pre zatvaranja</h2>
        <div class="checks">
            <?php if ($needPay): ?>
                <?php if ($paid): ?>
                    <label class="check row"><input type="checkbox" checked disabled><span class="box"></span><span class="grow"><?= e($payLabel) ?></span><span class="mono"><?= e(fmt_money($p['payment_received'])) ?></span></label>
                <?php else: ?>
                    <label class="check row"><input type="checkbox" name="paid" value="1" data-req="naplata"><span class="box"></span><span class="grow"><?= e($payLabel) ?></span></label>
                    <label class="amount"><span>Naplaćen iznos</span>
                        <input class="mono" name="paid_amount" value="<?= e(rtrim(rtrim((string) $p['payment_amount'], '0'), '.')) ?>" inputmode="decimal"><span class="mono">€</span></label>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($isMontaza): ?>
                <label class="check row"><input type="checkbox" name="video" value="1" data-req="video u Viber grupi"><span class="box"></span><span class="grow">Video poslat u Viber grupu</span><?= icon('video', 22) ?></label>
                <label class="check row"><input type="checkbox" name="handover" value="1" data-req="daljinski i uputstvo"><span class="box"></span><span class="grow">Predat daljinski i uputstvo</span></label>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <label class="field"><span>Napomena</span>
        <textarea class="input" name="note" rows="3" placeholder="<?= $isMontaza ? 'Npr. kupac traži dodatnu LED traku' : 'Opciono' ?>"><?= e($note) ?></textarea></label>

    <div class="sticky-foot">
        <p class="small muted center" data-missing></p>
        <button class="btn primary block" type="submit">Potvrdi završetak</button>
    </div>
</form>

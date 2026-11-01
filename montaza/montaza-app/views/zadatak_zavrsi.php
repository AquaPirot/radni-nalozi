<?php /** @var array $task @var int $existing @var ?string $error @var string $note */ ?>
<?php if ($error): ?><div class="alert warn" role="alert"><?= e($error) ?></div><?php endif; ?>
<form class="stack done-form" method="post" enctype="multipart/form-data" action="<?= e(url('zadatak_zavrsi', ['id' => $task['id']])) ?>"
      data-gate data-min-photos="<?= $task['requires_photo'] ? 1 : 0 ?>" data-existing-photos="<?= $existing ?>">
    <?= csrf_field() ?>
    <h2 class="h2">Fotografije <span class="muted small"><?= $task['requires_photo'] ? '(obavezna bar jedna)' : '(opciono)' ?></span></h2>
    <div class="thumbs">
        <label class="thumb add" aria-label="Dodaj fotografije">
            <input type="file" name="fotos[]" accept="image/*" multiple data-resize data-count data-add-photos><?= icon('plus', 26) ?>
        </label>
    </div>
    <label class="field"><span>Napomena</span>
        <textarea class="input" name="note" rows="3" placeholder="Opciono — npr. servis plaćen 12.000 din"><?= e($note) ?></textarea></label>
    <div class="sticky-foot">
        <p class="small muted center" data-missing></p>
        <button class="btn primary block" type="submit">Potvrdi završetak</button>
    </div>
</form>

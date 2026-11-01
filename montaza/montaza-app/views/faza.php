<?php /** @var array $ph @var ?string $error @var string $note @var int $existing @var int $min @var ?string $blocked */ ?>
<?php if ($blocked): ?><div class="alert warn"><?= e($blocked) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert warn" role="alert"><?= e($error) ?></div><?php endif; ?>
<form class="stack" method="post" action="<?= e(url('faza', ['id' => $ph['id']])) ?>" data-gate data-min-photos="<?= $min ?>" data-existing-photos="<?= $existing ?>">
    <?= csrf_field() ?><?= once_field() ?>
    <?= uploader('foto_ids', 'Fotografije', true, 'foto', $min > 0, $min > 0 ? 'min. ' . $min : 'opciono') ?>
    <label class="field"><span>Napomena</span><textarea class="input" name="note" rows="3" placeholder="Opciono — npr. fale 2 zavrtnja, dopunjeno"><?= e($note) ?></textarea></label>
    <div class="sticky-foot">
        <p class="small muted center" data-missing></p>
        <button class="btn primary block" type="submit">Potvrdi završetak</button>
    </div>
</form>

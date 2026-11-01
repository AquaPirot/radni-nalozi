<?php /** @var array $item @var string $reason @var string $note @var ?string $error */ ?>
<?php if ($error): ?><div class="alert warn" role="alert"><?= e($error) ?></div><?php endif; ?>
<form class="stack" method="post" action="<?= e(url('zastoj', ['item' => $item['id']])) ?>">
    <?= csrf_field() ?>
    <div class="field">
        <span>Razlog</span>
        <div class="radios">
            <?php foreach (REASON_LABELS as $k => $label): ?>
                <label class="radio"><input type="radio" name="reason" value="<?= $k ?>"<?= $reason === $k ? ' checked' : '' ?> required><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
    </div>
    <label class="field"><span>Kratka napomena</span>
        <input class="input" name="note" value="<?= e($note) ?>" maxlength="200" placeholder="Npr. fali motor"></label>
    <button class="btn warn block" type="submit"><?= icon('alert', 20) ?>Prijavi zastoj</button>
    <p class="small muted">Vlasnik dobija obaveštenje. Zastoj se zatvara kad nastaviš rad na stavci.</p>
</form>

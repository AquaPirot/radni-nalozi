<?php /** @var array $in @var ?string $error @var array $people @var array $defaults */
$byRole = [];
foreach ($people as $id => $u) {
    $byRole[$u['role']] ??= $id;
}
?>
<?php if ($error): ?><div class="alert warn" role="alert"><?= e($error) ?></div><?php endif; ?>
<form class="stack" method="post" action="<?= e(here()) ?>" data-stoppage>
    <?= csrf_field() ?><?= once_field() ?>
    <div class="field">
        <span>Razlog</span>
        <div class="chips">
            <?php foreach (REASON_LABELS as $k => $label): if ($k === 'ekipa') continue; ?>
                <label class="chip-radio"><input type="radio" name="reason" value="<?= $k ?>" data-owner="<?= (int) ($byRole[$defaults[$k]] ?? 0) ?>"<?= $in['reason'] === $k ? ' checked' : '' ?> required><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
    </div>
    <label class="field"><span>Kratak opis</span><input class="input" name="note" value="<?= e($in['note']) ?>" maxlength="200" placeholder="npr. čeka staklo" required></label>
    <label class="field"><span>Ko rešava</span>
        <select class="input" name="owner_id" required><option value="">— izaberi —</option>
            <?php foreach ($people as $id => $u): ?><option value="<?= (int) $id ?>"<?= $in['owner_id'] === (int) $id ? ' selected' : '' ?>><?= e($u['name']) ?> · <?= e(ROLE_LABELS[$u['role']]) ?></option><?php endforeach; ?>
        </select></label>
    <label class="field"><span>Sledeći korak</span><input class="input" name="next_step" value="<?= e($in['next_step']) ?>" maxlength="200" placeholder="npr. proveriti isporuku kod dobavljača"></label>
    <label class="field"><span>Očekivano rešenje</span><input class="input mono" type="date" name="expected_date" value="<?= e($in['expected_date']) ?>"></label>
    <button class="btn warn block" type="submit"><?= icon('alert', 20) ?>Prijavi zastoj</button>
    <p class="small muted">Vlasnik i odgovorna osoba dobijaju obaveštenje. Ako rešenje kasni posle termina montaže, termin se označava kao ugrožen.</p>
</form>

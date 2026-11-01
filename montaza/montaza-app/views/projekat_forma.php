<?php /** @var bool $isNew @var ?array $project @var array $v @var array $errors @var array $done */
$err = fn(string $k) => isset($errors[$k]) ? '<div class="field-err">' . e($errors[$k]) . '</div>' : '';
?>
<?php if ($errors): ?><div class="alert warn" role="alert">Proveri označena polja.</div><?php endif; ?>
<form class="stack" method="post" action="<?= e($isNew ? url('novi') : url('izmeni', ['id' => $project['id']])) ?>" novalidate<?= $isNew ? ' data-autosave="novi-projekat"' : '' ?>>
    <?= csrf_field() ?><?= once_field() ?>

    <div class="segctl" role="radiogroup" aria-label="Vrsta posla">
        <?php foreach (TYPE_LABELS as $k => $label): ?>
            <label><input type="radio" name="type" value="<?= $k ?>"<?= $v['type'] === $k ? ' checked' : '' ?> data-type><span><?= e(['novi' => 'Novi', 'zapocet' => 'Započet', 'reklamacija' => 'Reklamacija'][$k]) ?></span></label>
        <?php endforeach; ?>
    </div>

    <label class="field"><span>Kupac</span>
        <input class="input" name="customer_name" value="<?= e($v['customer_name']) ?>" placeholder="Ime i prezime ili firma" required autocomplete="off"><?= $err('customer_name') ?></label>
    <div class="grid2">
        <label class="field"><span>Telefon</span><input class="input mono" name="phone" type="tel" value="<?= e((string) $v['phone']) ?>" placeholder="06x xxx xxxx"></label>
        <label class="field"><span>Mesto</span><input class="input" name="city" value="<?= e((string) $v['city']) ?>" placeholder="Grad"></label>
    </div>
    <label class="field"><span>Adresa montaže</span><input class="input" name="address" value="<?= e((string) $v['address']) ?>" placeholder="Ulica i broj"></label>

    <div class="grid2">
        <label class="field"><span>Dan montaže</span><input class="input mono" type="date" name="install_date" value="<?= e((string) $v['install_date']) ?>"><?= $err('install_date') ?></label>
        <label class="field"><span>Vreme</span><input class="input mono" type="time" name="install_time" value="<?= e($v['install_time'] ? substr((string) $v['install_time'], 0, 5) : '') ?>"></label>
    </div>
    <p class="small muted" style="margin-top:-6px">Dan može i kasnije — sa strane projekta.</p>

    <?= uploader('nalog_id', $isNew ? 'Radni nalog (PDF)' : 'Nova verzija naloga (PDF)', false, 'pdf', false, 'u njemu su svi detalji posla') ?>

    <div data-only="novi zapocet" class="toggles">
        <div class="toggle-row">
            <div><div>Čelična konstrukcija</div><div class="small muted">dodaje stavku „Čelik"</div></div>
            <label class="switch"><input type="checkbox" name="has_steel" value="1"<?= !empty($v['has_steel']) ? ' checked' : '' ?> aria-label="Čelična konstrukcija"><i></i></label>
        </div>
    </div>
    <label class="field" data-only="reklamacija"><span>Reklamacija za projekat</span>
        <select class="input" name="parent_project_id">
            <option value="">— nije vezano —</option>
            <?php foreach ($done as $d): ?><option value="<?= (int) $d['id'] ?>"<?= (int) ($v['parent_project_id'] ?? 0) === (int) $d['id'] ? ' selected' : '' ?>><?= e($d['customer_name'] . ($d['city'] ? ' · ' . $d['city'] : '') . ' · ' . $d['code']) ?></option><?php endforeach; ?>
        </select></label>

    <?php if ($isNew): ?><?= uploader('lokacija_id', 'Slika mesta montaže (PRE)', false, 'foto', false, 'opciono') ?><?php endif; ?>

    <label class="field"><span>Napomena <span class="small muted">(opciono)</span></span>
        <textarea class="input" name="description" rows="2" placeholder="npr. pristup kamionom samo pre 9h"><?= e((string) $v['description']) ?></textarea></label>

    <button class="btn primary block" type="submit"><?= $isNew ? 'Otvori projekat' : 'Sačuvaj' ?></button>
</form>

<?php if (!$isNew): ?>
    <form method="post" action="<?= e(url('izmeni', ['id' => $project['id']])) ?>" class="danger-zone" data-confirm="Trajno obrisati projekat <?= e($project['code']) ?> sa svim slikama? Ovo se ne može vratiti.">
        <?= csrf_field() ?><?= once_field() ?><input type="hidden" name="do" value="delete">
        <button class="btn link warn block" type="submit"><?= icon('trash', 18) ?>Obriši projekat</button>
    </form>
<?php endif; ?>

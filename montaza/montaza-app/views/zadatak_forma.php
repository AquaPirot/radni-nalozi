<?php /** @var array $user @var bool $isNew @var ?array $task @var array $v @var array $errors @var array $people @var array $projects */
$err = fn(string $k) => isset($errors[$k]) ? '<div class="field-err">' . e($errors[$k]) . '</div>' : '';
?>
<form class="stack" method="post" action="<?= e($isNew ? url('novi_zadatak') : url('izmeni_zadatak', ['id' => $task['id']])) ?>" novalidate<?= $isNew ? ' data-autosave="novi-zadatak"' : '' ?>>
    <?= csrf_field() ?><?= once_field() ?>
    <label class="field"><span>Šta treba uraditi</span>
        <input class="input" name="title" value="<?= e($v['title']) ?>" maxlength="200" placeholder="Npr. Odvesti beli kombi na servis" required autofocus>
        <?= $err('title') ?></label>

    <div class="field">
        <span>Kome</span>
        <div class="chips">
            <?php foreach ($people as $pp): ?>
                <label class="chip-radio"><input type="radio" name="assigned_to" value="<?= (int) $pp['id'] ?>"<?= (int) $v['assigned_to'] === (int) $pp['id'] ? ' checked' : '' ?>><span><?= e((int) $pp['id'] === (int) $user['id'] ? 'Sebi' : $pp['name']) ?></span></label>
            <?php endforeach; ?>
        </div>
        <?= $err('assigned_to') ?>
    </div>

    <label class="field"><span>Detalji (opciono)</span>
        <textarea class="input" name="description" rows="3" placeholder="Šta tačno, gde, koliko…"><?= e($v['description']) ?></textarea></label>

    <div class="field">
        <span>Rok</span>
        <div class="btn-row" data-deadline>
            <input class="input mono grow" type="date" name="due_date" value="<?= e((string) $v['due_date']) ?>" aria-label="Datum">
            <input class="input mono" type="time" name="due_time" value="<?= e($v['due_time'] ? substr((string) $v['due_time'], 0, 5) : '') ?>" aria-label="Vreme (opciono)" style="max-width:8.5rem">
        </div>
        <label class="check row"><input type="checkbox" name="no_deadline" value="1"<?= !empty($v['no_deadline']) && !$v['due_date'] ? ' checked' : '' ?>><span class="box"></span><span>Bez roka</span></label>
        <span class="small muted">Vreme je opciono. Bez roka označi samo kad zaista nije bitno kada.</span>
        <?= $err('due_date') ?>
    </div>

    <div class="toggles">
        <div class="toggle-row">
            <div><div>Hitno</div><div class="small muted">Ide na vrh liste</div></div>
            <label class="switch"><input type="checkbox" name="priority" value="hitno"<?= $v['priority'] === 'hitno' ? ' checked' : '' ?> aria-label="Hitno"><i></i></label>
        </div>
        <div class="toggle-row">
            <div><div>Traži sliku pri završetku</div><div class="small muted">Npr. račun sa servisa, gotov alat</div></div>
            <label class="switch"><input type="checkbox" name="requires_photo" value="1"<?= $v['requires_photo'] ? ' checked' : '' ?> aria-label="Traži sliku"><i></i></label>
        </div>
    </div>

    <label class="field"><span>Vezano za projekat (opciono)</span>
        <select class="input" name="project_id">
            <option value="">— nije vezano —</option>
            <?php foreach ($projects as $pr): ?>
                <option value="<?= (int) $pr['id'] ?>"<?= (int) ($v['project_id'] ?? 0) === (int) $pr['id'] ? ' selected' : '' ?>><?= e($pr['customer_name'] . ($pr['city'] ? ' · ' . $pr['city'] : '') . ' · ' . $pr['code']) ?></option>
            <?php endforeach; ?>
        </select>
        <?= $err('project_id') ?></label>

    <button class="btn primary block" type="submit"><?= $isNew ? 'Zadaj zadatak' : 'Sačuvaj izmene' ?></button>
</form>


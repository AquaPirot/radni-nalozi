<?php
/** @var bool $isNew @var ?array $project @var array $items @var array $values @var array $errors @var array $parents */
$v = $values;
$err = fn(string $k) => isset($errors[$k]) ? '<div class="field-err">' . e($errors[$k]) . '</div>' : '';
$steelLocked = !$isNew && !rule_can_toggle_steel($items);
$payLocked = !$isNew && !empty($project['payment_confirmed_at']);
$action = $isNew ? url('novi') : url('izmeni', ['id' => $project['id']]);
?>
<?php foreach (['upload', 'reattach'] as $k): if (isset($errors[$k])): ?>
    <div class="alert warn"><?= e($errors[$k]) ?></div>
<?php endif; endforeach; ?>

<form class="pform stack" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>

    <?php if ($isNew): ?>
        <div class="field">
            <span>Tip</span>
            <div class="segctl">
                <?php foreach (TYPE_LABELS as $k => $label): ?>
                    <label><input type="radio" name="type" value="<?= $k ?>" id="t-<?= $k ?>"<?= $v['type'] === $k ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
                <?php endforeach; ?>
            </div>
            <?= $err('type') ?>
        </div>
    <?php else: ?>
        <input type="radio" name="type" value="<?= e($project['type']) ?>" id="t-<?= e($project['type']) ?>" checked hidden>
    <?php endif; ?>

    <label class="field"><span>Kupac</span>
        <input class="input" name="customer_name" value="<?= e($v['customer_name']) ?>" placeholder="Ime i prezime ili firma" maxlength="150" required>
        <?= $err('customer_name') ?>
    </label>

    <div class="grid2">
        <label class="field"><span>Mesto</span>
            <input class="input" name="city" value="<?= e($v['city']) ?>" placeholder="Grad" maxlength="100"></label>
        <label class="field"><span>Telefon</span>
            <input class="input" name="phone" type="tel" value="<?= e($v['phone']) ?>" placeholder="06x xxx xxxx" maxlength="50">
            <?= $err('phone') ?></label>
    </div>
    <label class="field"><span>Adresa</span>
        <input class="input" name="address" value="<?= e($v['address']) ?>" placeholder="Ulica i broj" maxlength="255"></label>

    <?php if ($isNew): ?>
        <div class="field">
            <span><span class="only-novi">Mesto montaže (obavezno)</span><span class="not-novi">Mesto montaže (preporučeno)</span></span>
            <label class="dropzone" data-preview>
                <input type="file" name="lokacija" accept="image/*" data-resize>
                <span class="dz-empty"><?= icon('camera', 28) ?><span>Slikaj ili izaberi iz galerije</span></span>
                <img class="dz-img" alt="" hidden>
            </label>
            <?= $err('lokacija') ?>
        </div>
    <?php endif; ?>

    <label class="field"><span>Opis</span>
        <textarea class="input" name="description" rows="3" placeholder="Tip pergole, dimenzije, boja… npr. Bioklimatska 4 × 5,5 m · RAL 7016"><?= e($v['description']) ?></textarea></label>

    <?php if ($isNew): ?>
        <div class="field only-rekl">
            <span>Reklamacija za projekat (opciono)</span>
            <select class="input" name="parent_project_id">
                <option value="">— bez veze —</option>
                <?php foreach ($parents as $pp): ?>
                    <option value="<?= (int) $pp['id'] ?>"<?= (int) $v['parent_project_id'] === (int) $pp['id'] ? ' selected' : '' ?>><?= e($pp['code'] . ' · ' . $pp['customer_name'] . ($pp['city'] ? ' · ' . $pp['city'] : '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <div class="field">
        <span>Stavke</span>
        <div class="toggles">
            <?php if ($isNew || $project['type'] !== 'reklamacija'): ?>
                <?php foreach (TEMPLATE_STD as [$kind, $resp]):
                    $item = null;
                    foreach ($items as $it) { if ($it['kind'] === $kind) { $item = $it; } }
                ?>
                    <div class="toggle-row not-rekl<?= $kind === 'celik' ? '' : ' fixed' ?>">
                        <div>
                            <div><?= e(ITEM_LABELS[$kind]) ?></div>
                            <div class="small muted"><?= e(ROLE_LABELS[$resp]) ?><?= $kind === 'celik' ? ' · opciono' : '' ?></div>
                        </div>
                        <?php if (!$isNew && $item && $kind !== 'montaza'): ?>
                            <input class="input due" type="date" name="due[<?= (int) $item['id'] ?>]" value="<?= e($item['due_date']) ?>" aria-label="Rok: <?= e(ITEM_LABELS[$kind]) ?>">
                        <?php endif; ?>
                        <?php if ($kind === 'celik'): ?>
                            <label class="switch"><input type="checkbox" name="has_steel" value="1"<?= $v['has_steel'] ? ' checked' : '' ?><?= $steelLocked ? ' disabled' : '' ?> aria-label="Čelična konstrukcija"><i></i></label>
                            <?php if ($steelLocked): ?><input type="hidden" name="has_steel" value="<?= $v['has_steel'] ? '1' : '' ?>"><?php endif; ?>
                        <?php else: ?>
                            <span class="switch on" aria-hidden="true"><i></i></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($isNew || $project['type'] === 'reklamacija'): ?>
                <?php foreach (TEMPLATE_REKLAMACIJA as [$kind, $resp]):
                    $item = null;
                    foreach ($items as $it) { if ($it['kind'] === $kind) { $item = $it; } }
                ?>
                    <div class="toggle-row only-rekl fixed">
                        <div>
                            <div><?= e(ITEM_LABELS[$kind]) ?></div>
                            <div class="small muted"><?= e(ROLE_LABELS[$resp]) ?></div>
                        </div>
                        <?php if (!$isNew && $item && $kind !== 'servis'): ?>
                            <input class="input due" type="date" name="due[<?= (int) $item['id'] ?>]" value="<?= e($item['due_date']) ?>" aria-label="Rok: <?= e(ITEM_LABELS[$kind]) ?>">
                        <?php endif; ?>
                        <span class="switch on" aria-hidden="true"><i></i></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if ($steelLocked): ?><div class="small muted">Čelična konstrukcija je već započeta — ne može se menjati.</div><?php endif; ?>
    </div>

    <?php if ($isNew): ?>
        <label class="field only-zapocet"><span>Već urađeno do (uključujući)</span>
            <select class="input" name="done_until">
                <option value="">— ništa nije urađeno —</option>
                <?php foreach (['nalog', 'celik', 'aluminijum', 'cerada', 'pakovanje'] as $k): ?>
                    <option value="<?= $k ?>"<?= $v['done_until'] === $k ? ' selected' : '' ?>><?= e(ITEM_LABELS[$k]) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="small muted">Te stavke se upisuju kao završene, bez obaveznih slika.</span>
            <?= $err('done_until') ?>
        </label>
    <?php endif; ?>

    <div class="grid2">
        <label class="field"><span><span class="not-rekl">Nedelja montaže</span><span class="only-rekl">Nedelja servisa</span></span>
            <select class="input" name="install_week_start">
                <option value="">Nije raspoređeno</option>
                <?php foreach (week_options($v['install_week_start'] ?? null) as $val => $label): ?>
                    <option value="<?= e($val) ?>"<?= ($v['install_week_start'] ?? null) === $val ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select></label>
        <label class="field"><span>Krajnji rok</span>
            <input class="input mono" type="date" name="deadline" value="<?= e($v['deadline']) ?>">
            <?= $err('deadline') ?></label>
    </div>

    <div class="field">
        <span>Naplata na terenu</span>
        <?php if ($payLocked): ?>
            <div class="card small">Naplata je potvrđena (<?= e(fmt_money($project['payment_received'])) ?>) — ne može se menjati.</div>
            <input type="hidden" name="payment_when" value="<?= e($project['payment_when']) ?>">
            <input type="hidden" name="payment_amount" value="<?= e($v['payment_amount']) ?>">
        <?php else: ?>
            <div class="segctl">
                <?php foreach (PAYMENT_LABELS as $k => $label): ?>
                    <label><input type="radio" name="payment_when" value="<?= $k ?>" id="pay-<?= $k ?>"<?= $v['payment_when'] === $k ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
                <?php endforeach; ?>
            </div>
            <?= $err('payment_when') ?>
            <label class="amount pay-amount">
                <span>Iznos za naplatu</span>
                <input class="mono" name="payment_amount" value="<?= e($v['payment_amount']) ?>" inputmode="decimal" placeholder="0" autocomplete="off">
                <span class="mono">€</span>
            </label>
            <?= $err('payment_amount') ?>
        <?php endif; ?>
    </div>

    <?php if ($isNew): ?>
        <div class="field not-rekl">
            <span>Radni nalog za proizvodnju</span>
            <label class="filebtn" data-filename>
                <input type="file" name="nalog_pdf" accept="application/pdf,.pdf">
                <?= icon('file', 20) ?><span>Zakači PDF nalog</span>
            </label>
            <span class="small muted">PDF odmah zatvara stavku „Izdat nalog". Može i kasnije, iz projekta.</span>
        </div>
    <?php endif; ?>

    <label class="field"><span>Napomena (interno)</span>
        <textarea class="input" name="notes" rows="2"><?= e($v['notes']) ?></textarea></label>

    <button class="btn primary block" type="submit"><?= $isNew ? 'Sačuvaj projekat' : 'Sačuvaj izmene' ?></button>
</form>

<?php if (!$isNew): ?>
    <form method="post" action="<?= e(url() . 'api/projekat_obrisi.php') ?>" class="danger-zone" data-confirm="Obrisati projekat <?= e($project['code']) ?> (<?= e($project['customer_name']) ?>) sa svim slikama? Ovo se ne može vratiti.">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
        <button class="btn warn block" type="submit"><?= icon('trash', 20) ?>Obriši projekat</button>
    </form>
<?php endif; ?>

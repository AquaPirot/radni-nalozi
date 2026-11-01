<?php
/** @var bool $isNew @var ?array $project @var array $v @var array $errors @var array $types @var array $tpl @var array $started @var array $parents @var ?array $lokacija */
$err = fn(string $k) => isset($errors[$k]) ? '<div class="field-err">' . e($errors[$k]) . '</div>' : '';
$isDraft = $isNew || $project['status'] === 'nacrt';
$action = $isNew ? url('novi') : url('izmeni', ['id' => $project['id']]);
$typeOpts = function (?int $sel) use ($types): string {
    $h = '<option value="">— izaberi —</option>';
    foreach ($types as $t) {
        $h .= '<option value="' . (int) $t['id'] . '" data-fields="' . e($t['fields']) . '"' . ((int) $sel === (int) $t['id'] ? ' selected' : '') . '>' . e($t['name']) . '</option>';
    }
    return $h;
};
$systemRow = function (string $i, array $s) use ($typeOpts, $started): string {
    $locked = !empty($s['id']) && !empty($started[$s['id']]);
    $h = '<div class="sys-row" data-sys>';
    $h .= '<input type="hidden" name="systems[' . $i . '][id]" value="' . (int) ($s['id'] ?? 0) . '">';
    $h .= '<input type="hidden" name="systems[' . $i . '][_delete]" value="" data-sys-delete>';
    $h .= '<div class="row-between"><strong class="sys-title">Sistem</strong>'
        . ($locked ? '<span class="small muted">izrada počela</span>' : '<button type="button" class="link warn" data-sys-remove>Ukloni</button>') . '</div>';
    $h .= '<div class="grid-sys">'
        . '<label class="field"><span>Vrsta</span><select class="input" name="systems[' . $i . '][type_id]" data-sys-type' . ($locked ? ' disabled' : '') . '>' . $typeOpts($s['type_id'] ?? null) . '</select>'
        . ($locked ? '<input type="hidden" name="systems[' . $i . '][type_id]" value="' . (int) $s['type_id'] . '">' : '') . '</label>'
        . '<label class="field"><span>Količina</span><input class="input mono" name="systems[' . $i . '][qty]" type="number" min="1" max="99" value="' . (int) ($s['qty'] ?? 1) . '"></label>'
        . '</div>';
    $h .= '<div class="grid3">'
        . '<label class="field"><span>Širina (m)</span><input class="input mono" name="systems[' . $i . '][width]" inputmode="decimal" value="' . e($s['width'] ?? '') . '" placeholder="4,0"></label>'
        . '<label class="field"><span>Dubina (m)</span><input class="input mono" name="systems[' . $i . '][length]" inputmode="decimal" value="' . e($s['length'] ?? '') . '" placeholder="3,0"></label>'
        . '<label class="field"><span>Visina (m)</span><input class="input mono" name="systems[' . $i . '][height]" inputmode="decimal" value="' . e($s['height'] ?? '') . '" placeholder="2,5"></label>'
        . '</div>';
    $h .= '<label class="field"><span>Boja (RAL, završna obrada)</span><input class="input" name="systems[' . $i . '][ral]" value="' . e($s['ral'] ?? '') . '" placeholder="RAL 7016 mat"></label>';
    $h .= '<div class="spec-grid">';
    foreach (SPEC_FIELDS as $k => $label) {
        $h .= '<label class="field" data-spec="' . $k . '" hidden><span>' . e($label) . '</span><input class="input" name="systems[' . $i . '][spec][' . $k . ']" value="' . e($s['spec'][$k] ?? '') . '"></label>';
    }
    $h .= '</div>';
    $h .= '<label class="field"><span>Napomena / posebni zahtevi</span><input class="input" name="systems[' . $i . '][note]" value="' . e($s['note'] ?? '') . '"></label>';
    $h .= '<div class="row-between sys-foot">'
        . '<label class="check"><input type="checkbox" name="systems[' . $i . '][with_steel]" value="1"' . (!empty($s['with_steel']) ? ' checked' : '') . ($locked ? ' disabled' : '') . '><span class="box"></span><span>Čelična konstrukcija</span></label>'
        . '<label class="field only-preuzet done-until"><span>Već urađeno do</span><select class="input" name="systems[' . $i . '][done_until]" data-done-until data-value="' . (int) ($s['done_until'] ?? 0) . '"><option value="0">ništa</option></select></label>'
        . '</div>';
    return $h . '</div>';
};
?>
<?php if ($errors): ?>
    <div class="alert warn"><strong>Proveri:</strong> <?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<form class="pform stack" method="post" action="<?= e($action) ?>" novalidate data-autosave="<?= $isNew ? 'novi-projekat' : 'projekat-' . (int) $project['id'] ?>">
    <?= csrf_field() ?><?= once_field() ?>
    <script type="application/json" id="phase-tpl"><?= json_encode($tpl) ?></script>

    <section class="fsec">
        <h2 class="fsec-h"><span>1</span>Vrsta posla</h2>
        <div class="segctl">
            <?php foreach (WORK_TYPES as $k => $label): ?>
                <label><input type="radio" name="work_type" value="<?= $k ?>" id="wt-<?= $k ?>"<?= $v['work_type'] === $k ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
        <div class="segctl">
            <?php foreach (INTAKE_LABELS as $k => $label): ?>
                <label><input type="radio" name="intake" value="<?= $k ?>" id="in-<?= $k ?>"<?= $v['intake'] === $k ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
        <label class="field only-rekl"><span>Reklamacija za projekat (opciono)</span>
            <select class="input" name="parent_project_id"><option value="">— bez veze —</option>
                <?php foreach ($parents as $pp): if ($project && (int) $pp['id'] === (int) $project['id']) continue; ?>
                    <option value="<?= (int) $pp['id'] ?>"<?= (int) $v['parent_project_id'] === (int) $pp['id'] ? ' selected' : '' ?>><?= e($pp['code'] . ' · ' . $pp['customer_name'] . ($pp['city'] ? ' · ' . $pp['city'] : '')) ?></option>
                <?php endforeach; ?>
            </select></label>
    </section>

    <section class="fsec">
        <h2 class="fsec-h"><span>2</span>Kupac i lokacija</h2>
        <label class="field"><span>Kupac</span><input class="input" name="customer_name" value="<?= e($v['customer_name']) ?>" placeholder="Ime i prezime ili firma" maxlength="150" required><?= $err('customer_name') ?></label>
        <div class="grid2">
            <label class="field"><span>Mesto</span><input class="input" name="city" value="<?= e($v['city']) ?>" placeholder="Grad"><?= $err('city') ?></label>
            <label class="field"><span>Telefon</span><input class="input" name="phone" type="tel" value="<?= e($v['phone']) ?>" placeholder="06x xxx xxxx"></label>
        </div>
        <label class="field"><span>Adresa (za navigaciju)</span><input class="input" name="address" value="<?= e($v['address']) ?>" placeholder="Ulica i broj"></label>
        <?php if ($lokacija): ?>
            <div class="row-between"><span class="small muted">Slika mesta montaže je sačuvana.</span><img class="mini-thumb" src="<?= e(file_url((int) $lokacija['id'], true)) ?>" alt=""></div>
        <?php endif; ?>
        <?= uploader('lokacija_id', $lokacija ? 'Zameni sliku mesta montaže' : 'Mesto montaže (PRE)', false, 'foto', false, 'za novi posao obavezno pre puštanja') ?>
        <?= $err('lokacija') ?>
    </section>

    <section class="fsec">
        <h2 class="fsec-h"><span>3</span>Sistemi i mere</h2>
        <div class="stack" data-sys-list>
            <?php foreach ($v['systems'] as $i => $s) { echo $systemRow((string) $i, $s); } ?>
        </div>
        <template id="sys-template"><?= $systemRow('__i__', []) ?></template>
        <button class="btn ghost block" type="button" data-sys-add><?= icon('plus', 18) ?>Dodaj sistem</button>
        <?= $err('systems') ?>
        <label class="field"><span>Opšta napomena za projekat</span>
            <textarea class="input" name="description" rows="2" placeholder="npr. pristup kamionom samo pre 9h"><?= e($v['description']) ?></textarea></label>
    </section>

    <section class="fsec">
        <h2 class="fsec-h"><span>4</span>Rok, dokumentacija i naplata</h2>
        <label class="field"><span>Dogovoreni krajnji rok</span><input class="input mono" type="date" name="deadline" value="<?= e($v['deadline']) ?>"><?= $err('deadline') ?></label>
        <p class="small muted">Termin montaže i ekipu određuješ posle, u Planu ili na projektu (Montaža).</p>
        <?= uploader('nalog_id', 'Radni nalog (PDF)', false, 'pdf', false, $isNew ? 'može i kasnije' : 'novi PDF = nova verzija naloga') ?>
        <div class="field">
            <span>Naplata</span>
            <div class="segctl">
                <?php foreach (PAYMENT_LABELS as $k => $label): ?>
                    <label><input type="radio" name="payment_when" value="<?= $k ?>" id="pay-<?= $k ?>"<?= $v['payment_when'] === $k ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="grid2 pay-amount">
            <label class="amount"><span>Iznos</span><input class="mono" name="payment_amount" value="<?= e($v['payment_amount']) ?>" inputmode="decimal" placeholder="0"><span class="mono">€</span></label>
            <label class="field"><select class="input" name="payment_collector" aria-label="Ko naplaćuje">
                <?php foreach (COLLECTOR_LABELS as $k => $label): ?><option value="<?= $k ?>"<?= $v['payment_collector'] === $k ? ' selected' : '' ?>>Naplaćuje: <?= e(mb_strtolower($label)) ?></option><?php endforeach; ?>
            </select></label>
        </div>
        <?= $err('payment_amount') ?>
        <label class="field"><span>Interna napomena (vidi samo vlasnik)</span><textarea class="input" name="notes" rows="2"><?= e($v['notes']) ?></textarea></label>
    </section>

    <div class="sticky-foot">
        <p class="small muted center" data-autosave-status></p>
        <?php if ($isDraft): ?>
            <div class="btn-row">
                <button class="btn ghost grow" type="submit" name="mode" value="draft">Sačuvaj nacrt</button>
                <button class="btn primary grow" type="submit" name="mode" value="release">Pusti u realizaciju</button>
            </div>
        <?php else: ?>
            <button class="btn primary block" type="submit" name="mode" value="save">Sačuvaj izmene</button>
        <?php endif; ?>
    </div>
</form>

<?php if (!$isNew): ?>
    <div class="danger-zone">
        <?= action_button('archive', (int) $project['id'], 'Arhiviraj projekat', 'btn warn block', [], 'Arhivirati projekat ' . $project['code'] . '? Može da se vrati iz arhive.', 'trash') ?>
    </div>
<?php endif; ?>

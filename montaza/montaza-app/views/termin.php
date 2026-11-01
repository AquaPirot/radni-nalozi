<?php /** @var array $b @var ?array $visit @var array $v @var ?string $error @var array $missing @var array $overlap @var array $checks @var array $teams */
$p = $b['p'];
$moving = $visit && $visit['start_date'];
?>
<?php if ($error): ?><div class="alert warn" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($b['systems']): ?><p class="muted"><?= e(systems_summary($b['systems'])) ?></p><?php endif; ?>

<form class="stack" method="post" action="<?= e(url('termin', array_filter(['project' => $p['id'], 'id' => $visit['id'] ?? null, 'back' => $_GET['back'] ?? null]))) ?>">
    <?= csrf_field() ?><?= once_field() ?>
    <div class="segctl">
        <?php foreach (VISIT_KINDS as $k => $label): ?>
            <label><input type="radio" name="kind" value="<?= $k ?>"<?= $v['kind'] === $k ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
        <?php endforeach; ?>
    </div>
    <label class="field"><span>Ekipa</span>
        <select class="input" name="team_id"><option value="">— nije određena —</option>
            <?php foreach ($teams as $t): ?><option value="<?= (int) $t['id'] ?>"<?= (int) $v['team_id'] === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
        </select></label>
    <div class="grid3 when">
        <label class="field"><span>Početak</span><input class="input mono" type="date" name="start_date" value="<?= e($v['start_date']) ?>"></label>
        <label class="field"><span>Vreme</span><input class="input mono" type="time" name="start_time" value="<?= e(substr((string) $v['start_time'], 0, 5)) ?>"></label>
        <label class="field"><span>Trajanje u danima</span><input class="input mono" type="number" inputmode="numeric" name="days" min="1" max="30" value="<?= (int) $v['days'] ?>"></label>
    </div>
    <p class="small muted">Višednevna montaža ide redom, uključujući subotu i nedelju ako ih obuhvata.</p>
    <?php foreach ($overlap as $o): ?><div class="alert warn">Preklapanje: ista ekipa je na <strong><?= e($o['customer_name']) ?></strong> (<?= e(visit_when($o)) ?>).</div><?php endforeach; ?>

    <label class="check row"><input type="checkbox" name="confirmed" value="1"<?= !empty($v['confirmed']) ? ' checked' : '' ?>><span class="box"></span><span class="grow">Termin potvrđen sa kupcem</span></label>
    <?php if ($v['kind'] !== 'dijagnoza'): ?>
        <div class="card stack">
            <strong class="small">Provera spremnosti</strong>
            <ul class="checks-list">
                <?php foreach ($checks as [$label, $ok]): if ($label === 'Termin potvrđen sa kupcem') continue; ?>
                    <li class="<?= $ok ? 'ok' : 'no' ?>"><?= icon($ok ? 'check' : 'alert', 14) ?><?= e($label) ?></li>
                <?php endforeach; ?>
            </ul>
            <label class="field"><span>Izuzetak (ako potvrđuješ iako nije sve spremno)</span>
                <input class="input" name="override" value="<?= e($v['override'] ?? '') ?>" placeholder="npr. ZIP-ovi stižu direktno na lokaciju"></label>
        </div>
    <?php endif; ?>
    <?php if ($moving): ?>
        <label class="field"><span>Razlog pomeranja (obavezno ako menjaš datum)</span>
            <input class="input" name="reason" value="<?= e($v['reason'] ?? '') ?>" placeholder="npr. kupac nije spreman, kiša"></label>
    <?php endif; ?>
    <label class="field"><span>Napomena za ekipu</span><textarea class="input" name="note" rows="2"><?= e($v['note'] ?? '') ?></textarea></label>
    <button class="btn primary block" type="submit">Sačuvaj termin</button>
</form>

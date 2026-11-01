<?php /** @var array $b @var array $visit @var array $in @var ?string $error @var ?array $lokacija @var array $existing @var bool $collects @var array $pay */
$isMontaza = $visit['kind'] === 'montaza';
$needCelina = in_array($visit['kind'], ['montaza', 'dorada', 'servis'], true) && empty($existing['celina']);
?>
<?php if ($error): ?><div class="alert warn" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($b['systems']): ?><p class="muted"><?= e(systems_summary($b['systems'], 5)) ?></p><?php endif; ?>

<form class="stack done-form" method="post" action="<?= e(url('teren_zavrsi', ['id' => $visit['id']])) ?>" data-gate data-need-result>
    <?= csrf_field() ?><?= once_field() ?>

    <div class="field">
        <span>Ishod</span>
        <div class="radios">
            <?php foreach (VISIT_RESULTS as $k => $label): ?>
                <label class="radio result-<?= $k ?>"><input type="radio" name="result" value="<?= $k ?>" id="res-<?= $k ?>"<?= $in['result'] === $k ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
    </div>
    <label class="field only-partial"><span>Šta je ostalo da se uradi <span class="warn">*</span></span>
        <textarea class="input" name="remaining" rows="2" placeholder="npr. montirati 2 ZIP-a, fali motor" data-req-partial><?= e($in['remaining']) ?></textarea>
        <span class="small muted">Napraviće se ponovni dolazak koji vlasnik raspoređuje.</span></label>

    <?php if ($needCelina || $isMontaza): ?>
        <div class="prepost">
            <div class="pp-tile"><span class="pp-label mono">PRE</span>
                <?php if ($lokacija): ?><img src="<?= e(file_url((int) $lokacija['id'], true)) ?>" alt="Mesto montaže pre"><?php else: ?><?= icon('image', 32) ?><?php endif; ?></div>
            <div class="pp-tile pp-info"><span class="small">Slikaj <strong>celinu</strong> sa istog mesta kao PRE slika.</span></div>
        </div>
    <?php endif; ?>
    <?= uploader('celina', 'Celina (isti ugao kao PRE)', true, 'foto', $needCelina, !empty($existing['celina']) ? 'već ima ' . $existing['celina'] : '') ?>
    <?= uploader('detalj', 'Važni detalji', true, 'foto', false, 'spojevi, motor, odvod, rasveta') ?>
    <div class="only-partial"><?= uploader('nedostatak', 'Nedostatak / šta je ostalo', true, 'foto', false, 'preporučeno') ?></div>

    <?php if ($collects): ?>
        <div class="card stack">
            <div class="row-between"><strong>Naplata</strong><span class="mono">ostalo <?= e(fmt_money((string) $pay['remaining'])) ?></span></div>
            <div class="grid2">
                <label class="amount"><span>Naplaćeno</span><input class="mono" name="paid" value="<?= e($in['paid'] !== null ? fmt_m((string) $in['paid']) : fmt_m((string) $pay['remaining'])) ?>" inputmode="decimal"><span class="mono">€</span></label>
                <select class="input" name="method" aria-label="Način"><?php foreach (METHOD_LABELS as $k => $l): ?><option value="<?= $k ?>"<?= $in['method'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
            </div>
            <label class="check row"><input type="checkbox" name="debt_ok" value="1"<?= $in['debt_ok'] ? ' checked' : '' ?>><span class="box"></span><span class="grow">Kupac nije platio ceo iznos — vlasnik dobija obaveštenje</span></label>
        </div>
    <?php endif; ?>

    <?php if ($isMontaza): ?>
        <div class="checks only-full">
            <label class="check row"><input type="checkbox" name="video" value="1" data-req-full="video u Viber grupi"<?= $in['video'] ? ' checked' : '' ?>><span class="box"></span><span class="grow">Video poslat u Viber grupu</span><?= icon('video', 22) ?></label>
            <label class="check row"><input type="checkbox" name="handover" value="1" data-req-full="daljinski i uputstvo"<?= $in['handover'] ? ' checked' : '' ?>><span class="box"></span><span class="grow">Predat daljinski i uputstvo</span></label>
        </div>
    <?php endif; ?>

    <label class="field"><span>Napomena</span><textarea class="input" name="note" rows="2" placeholder="npr. kupac traži dodatnu LED traku"><?= e($in['note']) ?></textarea></label>
    <div class="sticky-foot">
        <p class="small muted center" data-missing></p>
        <button class="btn primary block" type="submit">Potvrdi</button>
    </div>
</form>

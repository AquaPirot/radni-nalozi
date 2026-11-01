<?php
/** @var array $user @var DateTimeImmutable $mon @var array $days @var array $bundles @var array $teams @var int $teamF @var array $unVisits @var array $unBundles @var bool $showUn @var int $capacity @var int $weekCount */
$sun = $mon->modify('+6 days');
$todayKey = today()->format('Y-m-d');
$monthName = fn(DateTimeImmutable $d) => mb_strtolower(MONTHS_FULL[(int) $d->format('n')]);
$rangeLabel = $mon->format('j') . '–' . $sun->format('j') . '. ' . $monthName($sun) . ' ' . $sun->format('Y') . '.';
if ($mon->format('n') !== $sun->format('n')) {
    $rangeLabel = $mon->format('j') . '. ' . $monthName($mon) . ' – ' . $sun->format('j') . '. ' . $monthName($sun) . ' ' . $sun->format('Y') . '.';
}
$wurl = fn(DateTimeImmutable $m) => url('plan', array_filter(['w' => $m->format('Y-m-d'), 'team' => $teamF ?: null]));

$visitBadge = function (array $v) use ($bundles): array {
    $b = $bundles[(int) $v['pid']] ?? null;
    if ($v['status'] === 'zavrseno') {
        return [$v['result'] === 'celo' ? 'Završeno' : 'Delimično', $v['result'] === 'celo' ? 'st-zavrseno' : 'st-zastoj'];
    }
    if ($v['status'] === 'u_toku') {
        return ['U radu', 'st-u_toku'];
    }
    if ($b && $b['stops']) {
        $s = $b['stops'][0];
        return [mb_substr(trim((string) $s['note']) ?: REASON_LABELS[$s['reason']], 0, 22), 'st-zastoj'];
    }
    if ($b && $v['kind'] === 'montaza' && $b['phases'] && !phases_done($b['phases']) && !$v['readiness_override']) {
        return ['Nije spremno', 'st-zastoj'];
    }
    return $v['confirmed'] ? ['Potvrđeno', 'st-zavrseno'] : ['Okvirno', 'st-ceka'];
};
$visitCard = function (array $v) use ($bundles, $visitBadge): string {
    $b = $bundles[(int) $v['pid']] ?? null;
    [$badge, $bcls] = $visitBadge($v);
    $time = $v['start_time'] ? substr($v['start_time'], 0, 5) : 'ceo dan';
    if ((int) $v['days'] > 1) {
        $time .= ' · dan ' . $v['day_n'] . '/' . (int) $v['days'];
    }
    $sys = $b ? systems_summary($b['systems'], 2) : '';
    return '<a class="vcard" href="' . e(url('projekat', ['id' => $v['pid'], 't' => 'montaza'])) . '">'
        . '<div class="row-between"><span class="time-chip mono">' . e($time) . '</span><span class="pill ' . $bcls . '">' . e($badge) . '</span></div>'
        . '<strong>' . e($v['customer_name']) . ($v['city'] ? ' · ' . e($v['city']) : '') . '</strong>'
        . '<span class="muted">' . e(VISIT_KINDS[$v['kind']]) . ($sys !== '' ? ' · ' . e($sys) : '') . ((int) $v['days'] > 1 ? ' · planirano ' . (int) $v['days'] . ' dana' : '') . '</span>'
        . '<span class="small muted">' . icon('users', 14) . ' ' . e($v['team_name'] ?? 'ekipa nije određena') . '</span>'
        . '</a>';
};
?>
<div class="plan-bar">
    <div class="week-nav">
        <a class="icon-btn flat" href="<?= e($wurl($mon->modify('-1 week'))) ?>" aria-label="Prethodna nedelja"><?= icon('back') ?></a>
        <strong><?= e($rangeLabel) ?></strong>
        <a class="icon-btn flat" href="<?= e($wurl($mon->modify('+1 week'))) ?>" aria-label="Sledeća nedelja"><?= icon('chevron') ?></a>
    </div>
    <form method="get" action="<?= e(url()) ?>">
        <input type="hidden" name="p" value="plan"><input type="hidden" name="w" value="<?= e($mon->format('Y-m-d')) ?>">
        <select class="input team-filter" name="team" onchange="this.form.submit()" aria-label="Ekipa">
            <option value="0">Sve ekipe</option>
            <?php foreach ($teams as $t): ?><option value="<?= (int) $t['id'] ?>"<?= $teamF === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
        </select>
    </form>
</div>

<div class="day-chips">
    <?php foreach ($days as $k => $d): $w = (int) $d['date']->format('N'); ?>
        <a href="#d-<?= e($k) ?>" class="day-chip<?= $k === $todayKey ? ' today' : '' ?><?= $w >= 6 ? ' weekend' : '' ?><?= $d['items'] ? ' busy' : '' ?>">
            <span><?= e(DAYS_SHORT[(int) $d['date']->format('w')]) ?></span><strong><?= e($d['date']->format('j')) ?></strong>
        </a>
    <?php endforeach; ?>
</div>
<p class="small muted"><?= $weekCount ?> <?= $weekCount === 1 ? 'termin' : 'termina' ?> ove nedelje · okvirni kapacitet <?= $capacity ?> montaža nedeljno</p>

<?php $planOwner = (current_user()['role'] ?? '') === 'vlasnik'; ?>
<?php if ($unVisits || $unBundles): ?>
    <a class="rcard static unsched-link" href="<?= e(url('plan', ['w' => $mon->format('Y-m-d'), 'f' => $showUn ? null : 'neraspored'])) ?>#nerasporedjeno">
        <span><strong><?= icon('zadaci', 18) ?> Neraspoređeno · <?= count($unVisits) + count($unBundles) ?></strong><span class="muted small">projekti i ponovni dolasci bez termina</span></span><?= icon('chevron') ?></a>
<?php endif; ?>
<?php if ($showUn): ?>
    <section class="stack" id="nerasporedjeno">
        <?php foreach ($unVisits as $v): ?>
            <a class="list-row card" href="<?= e($planOwner ? url('termin', ['project' => $v['project_id'], 'id' => $v['id'], 'back' => 'plan']) : url('projekat', ['id' => $v['project_id'], 't' => 'montaza'])) ?>">
                <span><strong><?= e($v['customer_name']) ?></strong> <span class="muted"><?= e($v['city']) ?></span><br><span class="small warn"><?= e(VISIT_KINDS[$v['kind']]) ?><?= $v['note'] ? ' · ' . e(trim($v['note'])) : '' ?></span></span><span class="chip"><?= $planOwner ? 'Rasporedi' : 'Otvori' ?></span></a>
        <?php endforeach; ?>
        <?php foreach ($unBundles as $b): [$st, $stl] = project_stage($b); ?>
            <a class="list-row card" href="<?= e($planOwner ? url('termin', ['project' => $b['p']['id'], 'back' => 'plan']) : url('projekat', ['id' => $b['p']['id']])) ?>">
                <span><strong><?= e($b['p']['customer_name']) ?></strong> <span class="muted"><?= e($b['p']['city']) ?></span><br>
                    <span class="small muted"><?= e($stl) ?><?= $b['systems'] ? ' · ' . e(systems_summary($b['systems'], 2)) : '' ?><?= $b['p']['deadline'] ? ' · rok ' . e(fmt_dm($b['p']['deadline'])) : '' ?></span></span><span class="chip"><?= $planOwner ? 'Rasporedi' : 'Otvori' ?></span></a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<!-- Telefon: spisak po danima -->
<div class="plan-days">
    <?php foreach ($days as $k => $d):
        $w = (int) $d['date']->format('N');
        if ($w >= 6 && !$d['items']) continue; ?>
        <section class="plan-day<?= $k === $todayKey ? ' today' : '' ?>" id="d-<?= e($k) ?>">
            <h2 class="h2<?= $w >= 6 ? ' weekend' : '' ?>"><?= e(mb_convert_case(DAYS_FULL[(int) $d['date']->format('w')], MB_CASE_TITLE)) ?>, <?= e($d['date']->format('j')) ?>. <?= e($monthName($d['date'])) ?><?= $k === $todayKey ? ' · danas' : '' ?></h2>
            <?php if ($d['overlap']): ?><div class="small warn">Preklapanje ekipe: <?= e(implode(', ', array_map(fn($tid) => current(array_filter($teams, fn($t) => (int) $t['id'] === (int) $tid))['name'] ?? '?', $d['overlap']))) ?> ima više termina istog dana.</div><?php endif; ?>
            <?php if ($d['items']): ?>
                <?php foreach ($d['items'] as $v) { echo $visitCard($v); } ?>
            <?php else: ?>
                <a class="small muted add-day" href="<?= e(url('plan', ['w' => $mon->format('Y-m-d'), 'f' => 'neraspored'])) ?>#nerasporedjeno"><?= $planOwner ? 'Slobodno — rasporedi' : 'Slobodno' ?></a>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<!-- Računar: nedeljni kalendar ekipe × dani -->
<div class="plan-grid" style="--cols: <?= count($days) ?>">
    <div class="pg-head"></div>
    <?php foreach ($days as $k => $d): ?><div class="pg-head<?= (int) $d['date']->format('N') >= 6 ? ' weekend' : '' ?><?= $k === $todayKey ? ' today' : '' ?>"><?= e(DAYS_SHORT[(int) $d['date']->format('w')]) ?> <?= e($d['date']->format('j.n.')) ?></div><?php endforeach; ?>
    <?php foreach (array_merge($teams, [['id' => 0, 'name' => 'Bez ekipe']]) as $t):
        if ($teamF && (int) $t['id'] !== $teamF) continue; ?>
        <div class="pg-team"><?= e($t['name']) ?></div>
        <?php foreach ($days as $k => $d): $cell = array_filter($d['items'], fn($v) => (int) $v['team_id'] === (int) $t['id']); ?>
            <div class="pg-cell<?= count($cell) > 1 ? ' over' : '' ?><?= (int) $d['date']->format('N') >= 6 ? ' weekend' : '' ?>">
                <?php foreach ($cell as $v) { echo $visitCard($v); } ?>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>

<?php if ($planOwner): ?><a class="fab" href="<?= e(url('plan', ['w' => $mon->format('Y-m-d'), 'f' => 'neraspored'])) ?>#nerasporedjeno"><?= icon('plus') ?><span>Rasporedi montažu</span></a><?php endif; ?>

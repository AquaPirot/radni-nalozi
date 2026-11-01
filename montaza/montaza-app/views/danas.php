<?php
/** @var array $user @var string $role @var array $myTasks @var array $given */
$item = function (array $x): string {
    return '<a class="react' . (!empty($x['hot']) ? ' hot' : '') . '" href="' . e($x['url']) . '"><span class="grow"><strong>' . e($x['title']) . '</strong><span>' . e($x['why']) . '</span></span>' . icon('chevron') . '</a>';
};
$taskRow = function (array $t, bool $given = false) use ($user): string {
    [$tl, $tc] = task_status_line($t);
    $sub = $given ? $t['assigned_name'] . ' · ' . $tl : $tl . ($t['created_by'] && (int) $t['created_by'] !== (int) $user['id'] ? ' · od ' . $t['created_name'] : '');
    $h = '<div class="list-row task-row"><a class="grow" href="' . e(url('zadatak', ['id' => $t['id']])) . '"><span class="strong">' . e($t['title']) . '</span>'
        . ($t['priority'] === 'hitno' ? ' <span class="pill st-zastoj">Hitno</span>' : '')
        . '<br><span class="small status ' . e($tc) . '"><i></i>' . e($sub) . ($t['due_date'] ? ' · rok ' . e(fmt_dm($t['due_date'])) . ($t['due_time'] ? ' ' . e(substr($t['due_time'], 0, 5)) : '') : ' · bez roka') . '</span></a>';
    if (!$given && empty($t['accepted_at'])) {
        $h .= action_button('task_accept', (int) $t['id'], 'Preuzimam', 'btn ghost sm');
    }
    return $h . '</div>';
};
$visitBlock = function (array $v, bool $big) use ($user): string {
    $b = bundle_one((int) $v['pid']);
    $p = $b['p'];
    $pay = payment_info($b);
    $nav = trim(implode(', ', array_filter([$p['address'], $p['city']])));
    $order = current_order($b['orders']);
    $future = $v['start_date'] > today()->format('Y-m-d');
    $multi = (int) $v['days'] > 1 ? ($future ? ' · ' . (int) $v['days'] . ' dana' : ' · dan ' . ((int) (new DateTimeImmutable($v['start_date']))->diff(today())->days + 1) . '/' . (int) $v['days']) : '';
    $dayLbl = ['ned', 'pon', 'uto', 'sre', 'čet', 'pet', 'sub'][(int) date('w', strtotime($v['start_date']))] . ' ' . fmt_dm($v['start_date']) . ' · ';
    $h = '<section class="job' . ($big ? ' big' : '') . '">';
    $h .= '<div class="row-between"><span class="time-chip mono">' . e(($future ? $dayLbl : '') . ($v['start_time'] ? substr($v['start_time'], 0, 5) : 'ceo dan')) . e($multi) . '</span>'
        . '<span class="pill ' . ($v['status'] === 'u_toku' ? 'st-u_toku">U radu' : ($v['confirmed'] ? 'st-zavrseno">Potvrđeno' : 'st-ceka">Okvirno')) . '</span></div>';
    $h .= '<a href="' . e(url('projekat', ['id' => $p['id'], 't' => 'montaza'])) . '"><h3>' . e($p['customer_name']) . ($p['city'] ? ' · ' . e($p['city']) : '') . '</h3></a>';
    if ($p['address']) {
        $h .= '<div class="muted">' . e($p['address']) . '</div>';
    }
    $h .= '<div>' . e(VISIT_KINDS[$v['kind']]) . ($b['systems'] ? ': ' . e(systems_summary($b['systems'], 5)) : '') . '</div>';
    $h .= '<div class="small muted">' . icon('users', 14) . ' ' . e($v['team_name'] ?? 'ekipa') . '</div>';
    if ($big) {
        if ($b['stops']) {
            $h .= '<div class="small warn">' . e(stoppage_text($b['stops'][0], $v)) . '</div>';
        }
        if (trim((string) $v['note']) !== '') {
            $h .= '<div class="note">' . nl2br(e(trim($v['note']))) . '</div>';
        }
        if (can_see_payment($user) && $p['payment_collector'] === 'teren' && $pay['remaining'] > 0 && $p['payment_when'] !== 'ne') {
            $h .= '<div class="paynote">' . icon('cash', 18) . ' Naplatiti ' . e(fmt_money((string) $pay['remaining'])) . ' — ' . e(mb_strtolower(PAYMENT_LABELS[$p['payment_when']])) . '</div>';
        }
        $h .= '<div class="btn-row">';
        if ($p['phone']) {
            $h .= '<a class="btn ghost grow" href="tel:' . e(preg_replace('/[^\d+]/', '', $p['phone'])) . '">' . icon('phone', 18) . 'Pozovi</a>';
        }
        if ($nav !== '') {
            $h .= '<a class="btn ghost grow" href="https://www.google.com/maps/search/?api=1&amp;query=' . e(rawurlencode($nav)) . '" target="_blank" rel="noopener">' . icon('nav', 18) . 'Navigacija</a>';
        }
        $h .= '</div><div class="btn-row">';
        if ($order && $order['file_id']) {
            $h .= '<a class="btn ghost sm grow" href="' . e(file_url((int) $order['file_id'])) . '" target="_blank" rel="noopener">' . icon('file', 16) . 'Nalog / crteži</a>';
        }
        $h .= '<a class="btn ghost sm grow" href="' . e(url('galerija', ['id' => $p['id']])) . '">' . icon('image', 16) . 'Fotografije</a></div>';
        if (can_act_visit($user, $p, $v)) {
            $h .= '<div class="btn-row">';
            if ($v['status'] === 'planiran') {
                $h .= '<a class="btn primary grow" href="' . e(url('projekat', ['id' => $p['id'], 't' => 'montaza'])) . '">' . icon('play', 18) . 'Počinjem</a>';
            } else {
                $h .= '<a class="btn primary grow" href="' . e(url('teren_zavrsi', ['id' => $v['id']])) . '">' . icon('check', 18) . 'Završi</a>';
            }
            $h .= '<a class="btn warn" href="' . e(url('zastoj', ['visit' => $v['id']])) . '">Problem</a></div>';
        }
    }
    return $h . '</section>';
};
?>

<?php /** @var array $decisions @var array $stops @var array $late @var array $payList @var float $paySum @var array $today @var array $next @var array $myStops @var array $comments */ ?>
<?php if ($role === 'teren'): ?>
    <h2 class="sec-head"><span>MOJ DANAŠNJI POSAO</span><span><?= count($today) ?></span></h2>
    <?php if ($today): foreach ($today as $v) { echo $visitBlock($v, true); } else: ?>
        <div class="empty"><?= icon('check', 28) ?><p>Danas nema montaže.</p><?php if ($next): ?><p class="small muted">Sledeće: <?= e($next[0]['customer_name']) ?> — <?= e(visit_when($next[0])) ?></p><?php endif; ?></div>
    <?php endif; ?>
    <?php if ($myStops): ?><section class="stack"><h2 class="sec-head"><span>PROBLEMI NA MENI</span></h2><div class="reacts"><?php foreach ($myStops as $x) { echo $item($x); } ?></div></section><?php endif; ?>
    <?php if ($myTasks): ?><section class="stack"><h2 class="sec-head"><span>MOJI ZADACI</span><a href="<?= e(url('zadaci')) ?>">svi ›</a></h2><div class="list"><?php foreach ($myTasks as $t) { echo $taskRow($t); } ?></div></section><?php endif; ?>
<?php elseif ($role === 'hala'): ?>
    <?php if ($orders): ?>
        <section class="stack">
            <h2 class="sec-head"><span>NALOZI ZA PREUZIMANJE</span><span class="warn"><?= count($orders) ?></span></h2>
            <?php foreach ($orders as $o): ?>
                <div class="card row-between">
                    <a href="<?= e(url('projekat', ['id' => $o['project_id'], 't' => 'proizvodnja'])) ?>"><strong><?= e($o['customer_name']) ?></strong> <span class="muted"><?= e($o['city']) ?></span><br><span class="small <?= $o['version'] > 1 ? 'warn' : 'muted' ?>"><?= $o['version'] > 1 ? 'IZMENJEN nalog v' . (int) $o['version'] : 'Nov nalog' ?><?= $o['note'] ? ' · ' . e($o['note']) : '' ?></span></a>
                    <?= action_button('order_accept', (int) $o['id'], 'Preuzeo', 'btn primary sm', [], null, 'check') ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <?php if ($myStops): ?><section class="stack"><h2 class="sec-head"><span>PROBLEMI NA MENI</span></h2><div class="reacts"><?php foreach ($myStops as $x) { echo $item($x); } ?></div></section><?php endif; ?>
    <section class="stack">
        <h2 class="sec-head"><span>NA REDU U PROIZVODNJI</span><span><?= count($phases) ?></span></h2>
        <?php if ($phases): ?>
            <div class="list">
                <?php foreach (array_slice($phases, 0, 6) as $ph): ?>
                    <a class="list-row" href="<?= e(url('projekat', ['id' => $ph['project_id'], 't' => 'proizvodnja'])) ?>">
                        <span><strong><?= e($ph['name']) ?></strong> <span class="muted">· <?= e($ph['customer_name']) ?><?= $ph['city'] ? ', ' . e($ph['city']) : '' ?></span><br>
                            <span class="small muted"><?= e(system_label(['type_name' => $ph['type_name'], 'qty' => $ph['qty'], 'width' => $ph['width'], 'length' => $ph['length'], 'height' => $ph['height']])) ?><?= $ph['next_date'] ? ' · montaža ' . e(fmt_dm($ph['next_date'])) : '' ?><?= $ph['due_date'] ? ' · rok ' . e(fmt_dm($ph['due_date'])) : '' ?></span></span>
                        <span class="pill st-<?= e($ph['status']) ?>"><?= e(STATUS_LABELS[$ph['status']]) ?></span></a>
                <?php endforeach; ?>
                <?php if (count($phases) > 6): ?><a class="list-row" href="<?= e(url('projekti')) ?>"><span class="muted">još <?= count($phases) - 6 ?> na redu — svi projekti</span><?= icon('chevron') ?></a><?php endif; ?>
            </div>
        <?php else: ?><p class="ok-note"><?= icon('check', 18) ?> Nema faza na redu.</p><?php endif; ?>
    </section>
    <?php if ($myTasks): ?><section class="stack"><h2 class="sec-head"><span>MOJI ZADACI</span><a href="<?= e(url('zadaci')) ?>">svi ›</a></h2><div class="list"><?php foreach ($myTasks as $t) { echo $taskRow($t); } ?></div></section><?php endif; ?>
<?php endif; ?>

<?php if ($role !== 'vlasnik'): ?><h2 class="sec-title">Zajednički pregled</h2><?php endif; ?>
    <div class="kpis">
        <a class="kpi<?= $decisions ? ' on' : '' ?>" href="<?= e(url('pregled', ['f' => 'odluka'])) ?>"><?= icon('file', 22) ?><strong><?= count($decisions) ?></strong><span>Traži odluku</span></a>
        <a class="kpi<?= $stops ? ' warn' : '' ?>" href="<?= e(url('pregled', ['f' => 'zastoj'])) ?>"><?= icon('alert', 22) ?><strong><?= count($stops) ?></strong><span>U zastoju</span></a>
        <a class="kpi<?= $late ? ' warn' : '' ?>" href="<?= e(url('pregled', ['f' => 'kasni'])) ?>"><?= icon('plan', 22) ?><strong><?= count($late) ?></strong><span>Kasni</span></a>
        <a class="kpi" href="<?= e(url('pregled', ['f' => 'naplata'])) ?>"><?= icon('cash', 22) ?><strong class="mono"><?= e(fmt_money((string) $paySum)) ?></strong><span>Za naplatu</span></a>
    </div>

    <div class="dash-cols"><div class="stack">
    <section class="stack">
        <h2 class="sec-head"><span>POTREBNA REAKCIJA</span><?php if (count($decisions) > 4): ?><a href="<?= e(url('pregled', ['f' => 'odluka'])) ?>">sve (<?= count($decisions) ?>) ›</a><?php endif; ?></h2>
        <?php if ($decisions): ?>
            <div class="reacts"><?php foreach (array_slice($decisions, 0, 4) as $x) { echo $item($x); } ?></div>
        <?php else: ?>
            <p class="ok-note"><?= icon('check', 18) ?> Ništa ne čeka tvoju odluku.</p>
        <?php endif; ?>
    </section>

    <section class="stack">
        <h2 class="sec-head"><span>ZASTOJI I KAŠNJENJA</span></h2>
        <?php $sl = array_merge($stops, array_filter($late, fn($x) => !str_contains($x['why'], ' — '))); ?>
        <?php if ($sl): ?>
            <div class="reacts"><?php foreach (array_slice($sl, 0, 5) as $x) { echo $item($x); } ?></div>
        <?php else: ?>
            <p class="ok-note"><?= icon('check', 18) ?> Nema otvorenih zastoja ni kašnjenja.</p>
        <?php endif; ?>
    </section>

    </div><div class="stack">
    <section class="stack">
        <h2 class="sec-head"><span>DANAS NA TERENU</span><a href="<?= e(url('plan')) ?>">plan ›</a></h2>
        <?php if ($today): foreach ($today as $v) { echo $visitBlock($v, false); } else: ?>
            <p class="small muted">Danas nema termina na terenu.</p>
        <?php endif; ?>
    </section>

    <section class="stack">
        <h2 class="sec-head"><span>SLEDEĆIH 7 DANA</span><span><?= count($next) ?></span></h2>
        <?php if ($next): ?>
            <div class="list">
                <?php foreach ($next as $v): ?>
                    <a class="list-row" href="<?= e(url('projekat', ['id' => $v['pid'], 't' => 'montaza'])) ?>">
                        <span><span class="mono small"><?= e(visit_when($v)) ?></span><br><strong><?= e($v['customer_name']) ?></strong> <span class="muted"><?= e($v['city']) ?> · <?= e(VISIT_KINDS[$v['kind']]) ?> · <?= e($v['team_name'] ?? 'bez ekipe') ?></span></span>
                        <span class="pill <?= $v['confirmed'] ? 'st-zavrseno' : 'st-ceka' ?>"><?= $v['confirmed'] ? 'Potvrđeno' : 'Okvirno' ?></span></a>
                <?php endforeach; ?>
            </div>
        <?php else: ?><p class="small muted">Nema zakazanih termina — <a class="accent" href="<?= e(url('plan', ['f' => 'neraspored'])) ?>">rasporedi</a>.</p><?php endif; ?>
    </section>

    <section class="stack">
        <h2 class="sec-head"><span>ZADACI KOJE SI ZADAO</span><a href="<?= e(url('zadaci', ['f' => 'otvoreni'])) ?>">svi ›</a></h2>
        <?php if ($given): ?><div class="list"><?php foreach ($given as $t) { echo $taskRow($t, true); } ?></div>
        <?php else: ?><p class="small muted">Nema otvorenih zadataka koje si zadao.</p><?php endif; ?>
    </section>
    </div></div>


<section class="stack">
    <h2 class="sec-head"><span>NOVI KOMENTARI</span><span><?= count($comments) ?></span></h2>
    <?php if ($comments): ?>
        <div class="reacts">
            <?php foreach ($comments as $c): ?>
                <a class="react cmt" href="<?= e($c['url']) ?>"><span class="grow"><strong><?= e($c['user_name']) ?> · <?= e($c['where']) ?></strong><span><?= e(mb_strimwidth((string) $c['body'], 0, 140, '…')) ?><?= $c['file_id'] ? ' 📷' : '' ?></span><span class="small muted"><?= e(fmt_ago($c['created_at'])) ?></span></span><?= icon('chevron') ?></a>
            <?php endforeach; ?>
        </div>
    <?php else: ?><p class="small muted">Nema novih komentara u poslednja 3 dana.</p><?php endif; ?>
</section>

<?php if ($role === 'vlasnik'): ?>
    <a class="fab" href="<?= e(url('novi')) ?>"><?= icon('plus') ?><span>Novi projekat</span></a>
<?php else: ?>
    <a class="fab" href="<?= e(url('novi_zadatak')) ?>"><?= icon('plus') ?><span>Zadatak</span></a>
<?php endif; ?>

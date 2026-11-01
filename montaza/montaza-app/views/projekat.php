<?php
/** @var array $user @var array $b @var string $t @var array $tabs @var array $files @var ?array $lokacija @var array $tasks @var array $log @var ?array $parent @var array $children @var array $allStops @var array $teams @var array $halaUsers */
$p = $b['p'];
$pid = (int) $p['id'];
$isOwner = $user['role'] === 'vlasnik';
$canPay = can_see_payment($user);
[$stageKey, $stageLabel] = project_stage($b);
[$naText, $naWho] = next_action($b);
$pay = payment_info($b);
$nv = next_visit($b['visits']);
$late = project_late($p);
$order = current_order($b['orders']);
[$wtag, $wcls] = work_tag($p);
$navQ = trim(implode(', ', array_filter([$p['address'], $p['city']])));
$filesBy = ['phase' => [], 'visit' => []];
foreach ($files as $f) {
    if ($f['phase_id']) { $filesBy['phase'][(int) $f['phase_id']][] = $f; }
    if ($f['visit_id']) { $filesBy['visit'][(int) $f['visit_id']][] = $f; }
}
$stopByPhase = [];
$stopByVisit = [];
foreach ($b['stops'] as $s) {
    if ($s['phase_id']) { $stopByPhase[(int) $s['phase_id']] = $s; }
    if ($s['visit_id']) { $stopByVisit[(int) $s['visit_id']] = $s; }
}
$statusPill = fn(string $st) => '<span class="pill st-' . e($st) . '">' . e(STATUS_LABELS[$st] ?? $st) . '</span>';
$vStatus = ['planiran' => ['ceka', 'Planirano'], 'u_toku' => ['u_toku', 'U radu'], 'zavrseno' => ['zavrseno', 'Završeno']];
?>
<header class="ph-top">
    <div class="row-between">
        <a class="icon-btn" href="<?= e(url('projekti')) ?>" aria-label="Nazad"><?= icon('back') ?></a>
        <?php if ($isOwner): ?><a class="icon-btn" href="<?= e(url('izmeni', ['id' => $pid])) ?>" aria-label="Izmeni projekat"><?= icon('edit') ?></a><?php endif; ?>
    </div>
    <div class="eyebrow"><?= e($p['code']) ?> · <span class="<?= $wcls ?>"><?= e($wtag) ?></span><?= $p['archived_at'] ? ' · ARHIVA' : '' ?></div>
    <h1 class="h1"><?= e($p['customer_name']) ?></h1>
    <p class="ph-sub"><?= e(implode(' · ', array_filter([$p['city'], $p['address']]))) ?></p>
    <?php if ($b['systems']): ?><p class="ph-sys"><?= e(systems_summary($b['systems'], 4)) ?></p><?php endif; ?>

    <div class="btn-row">
        <?php if ($p['phone']): ?><a class="btn ghost grow" href="tel:<?= e(preg_replace('/[^\d+]/', '', $p['phone'])) ?>"><?= icon('phone', 18) ?>Pozovi</a><?php endif; ?>
        <?php if ($navQ !== ''): ?><a class="btn ghost grow" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($navQ)) ?>" target="_blank" rel="noopener"><?= icon('nav', 18) ?>Navigacija</a><?php endif; ?>
    </div>

    <div class="facts">
        <div class="fact"><span>Faza</span><strong><?= e($stageLabel) ?></strong></div>
        <div class="fact"><span>Rok</span><strong class="mono<?= $late ? ' warn' : '' ?>"><?= $p['deadline'] ? e(fmt_dm($p['deadline'])) : '—' ?></strong></div>
        <div class="fact"><span><?= $nv ? e(VISIT_KINDS[$nv['kind']]) : 'Montaža' ?></span><strong class="mono"><?= $nv && $nv['start_date'] ? e(fmt_dm($nv['start_date'])) : '—' ?></strong></div>
    </div>

    <?php if ($naText !== ''): ?>
        <div class="next-action">
            <span class="eyebrow">SLEDEĆA RADNJA</span>
            <strong><?= e($naText) ?></strong>
            <?php if ($naWho !== ''): ?><span class="muted">odgovoran: <?= e($naWho) ?></span><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($late): ?><div class="alert warn">Kasni <?= $late ?> <?= $late === 1 ? 'dan' : 'dana' ?> — dogovoreni rok je bio <?= e(fmt_date($p['deadline'])) ?>.</div><?php endif; ?>
    <?php foreach ($b['stops'] as $s): ?>
        <div class="problem">
            <div><span class="pill st-zastoj">Zastoj · <?= e(REASON_LABELS[$s['reason']]) ?></span>
                <?php if ($s['phase_name']): ?><span class="small muted"> <?= e($s['phase_name']) ?></span><?php endif; ?>
                <?php if ($s['visit_kind']): ?><span class="small muted"> <?= e(VISIT_KINDS[$s['visit_kind']]) ?></span><?php endif; ?></div>
            <p><?= e(stoppage_text($s, $nv)) ?><?= $s['expected_date'] ? ' · rešenje do ' . e(fmt_dm($s['expected_date'])) : '' ?></p>
            <?php if ($isOwner || (int) $s['owner_id'] === (int) $user['id'] || (int) $s['created_by'] === (int) $user['id']): ?>
                <?= action_button('stoppage_resolve', (int) $s['id'], 'Rešeno', 'btn ghost sm', [], null, 'check') ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</header>

<nav class="tabs" aria-label="Delovi projekta">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="<?= $t === $k ? 'on' : '' ?>" href="<?= e(url('projekat', ['id' => $pid, 't' => $k])) ?>"<?= $t === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($t === 'pregled'): ?>
    <?php if ($p['status'] === 'nacrt'): ?>
        <div class="card stack">
            <strong>Ovo je nacrt</strong>
            <span class="muted">Hala i teren ga ne vide dok ga ne pustiš u realizaciju.</span>
            <div class="btn-row">
                <a class="btn ghost grow" href="<?= e(url('izmeni', ['id' => $pid])) ?>">Dopuni</a>
                <?= action_button('release', $pid, 'Pusti u realizaciju', 'btn primary grow') ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="stepper">
        <?php foreach (stage_steps($b) as $i => [$name, $st]): ?>
            <div class="step <?= $st ?>"><i><?= $st === 'done' ? icon('check', 14) : $i + 1 ?></i><span><?= e($name) ?></span></div>
        <?php endforeach; ?>
    </div>

    <?php if ($b['systems']): ?>
        <section class="stack">
            <h2 class="sec-head"><span>SISTEMI</span><span><?= total_qty($b['systems']) ?> kom</span></h2>
            <div class="list">
                <?php foreach ($b['systems'] as $s):
                    $specTxt = array_map(fn($k) => SPEC_FIELDS[$k] . ': ' . $s['spec'][$k], array_values(array_intersect(array_keys(SPEC_FIELDS), array_keys($s['spec']))));
                    $line2 = implode(' · ', array_filter(array_merge([$s['ral'], $s['with_steel'] ? 'čelična konstrukcija' : ''], $specTxt)));
                ?>
                    <div class="list-row sys-line">
                        <div class="grow">
                            <strong><?= e($s['type_name'] ?? 'Sistem') ?></strong><?= dims_label($s) ? ' <span class="muted">· ' . e(dims_label($s)) . '</span>' : '' ?>
                            <?php if ($line2 !== ''): ?><div class="small muted"><?= e($line2) ?></div><?php endif; ?>
                            <?php if ($s['note']): ?><div class="small"><?= e($s['note']) ?></div><?php endif; ?>
                        </div>
                        <span class="mono"><?= (int) $s['qty'] ?> kom</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($canPay && $p['payment_when'] !== 'ne'): ?>
        <section class="card stack paycard">
            <div class="row-between">
                <div><div class="eyebrow">NAPLATA · <?= e(mb_strtoupper(PAYMENT_LABELS[$p['payment_when']])) ?></div>
                    <strong class="amount-big mono"><?= e(fmt_money((string) $pay['amount'])) ?></strong></div>
                <span class="pill pay-<?= e($pay['status']) ?>"><?= e($pay['label']) ?></span>
            </div>
            <div class="grid3 small">
                <div><span class="muted">Primljeno</span><br><strong class="mono"><?= e(fmt_money((string) $pay['received'])) ?></strong></div>
                <div><span class="muted">Preostalo</span><br><strong class="mono<?= $pay['remaining'] > 0 && $pay['due'] ? ' warn' : '' ?>"><?= e(fmt_money((string) $pay['remaining'])) ?></strong></div>
                <div><span class="muted">Naplaćuje</span><br><strong><?= e(COLLECTOR_LABELS[$p['payment_collector']]) ?></strong></div>
            </div>
            <?php if ($b['payments']): ?>
                <ul class="plain small">
                    <?php foreach ($b['payments'] as $x): ?>
                        <li class="row-between"><span><?= e(fmt_date(substr($x['received_at'], 0, 10))) ?> · <?= e(METHOD_LABELS[$x['method']]) ?> · <?= e(user_name($x['received_by'] ? (int) $x['received_by'] : null)) ?>
                            <?php if ($x['method'] === 'gotovina'): ?><span class="<?= $x['handed_at'] ? 'muted' : 'warn' ?>"> · <?= $x['handed_at'] ? 'predato firmi ' . e(fmt_dm(substr($x['handed_at'], 0, 10))) : 'kod radnika' ?></span><?php endif; ?></span>
                            <strong class="mono"><?= e(fmt_money($x['amount'])) ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($pay['remaining'] > 0 && $p['status'] !== 'nacrt'): ?>
                <details class="mini-form">
                    <summary class="btn ghost block"><?= icon('cash', 18) ?>Evidentiraj uplatu</summary>
                    <form method="post" action="<?= e(url() . 'api/akcija.php') ?>" class="stack">
                        <?= csrf_field() ?><?= once_field() ?>
                        <input type="hidden" name="action" value="payment_add"><input type="hidden" name="id" value="<?= $pid ?>"><input type="hidden" name="back" value="<?= e(here()) ?>">
                        <div class="grid2">
                            <label class="amount"><span>Iznos</span><input class="mono" name="amount" value="<?= e(fmt_m((string) $pay['remaining'])) ?>" inputmode="decimal"><span class="mono">€</span></label>
                            <select class="input" name="method" aria-label="Način"><?php foreach (METHOD_LABELS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select>
                        </div>
                        <input class="input" name="note" placeholder="Napomena (opciono)">
                        <button class="btn primary block" type="submit">Sačuvaj uplatu</button>
                    </form>
                </details>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="stack">
        <header class="row-between"><h2 class="sec-head"><span>ZADACI</span></h2>
            <?php if (in_array($p['status'], ['aktivan', 'nacrt'], true)): ?><a class="chip" href="<?= e(url('novi_zadatak', ['project' => $pid])) ?>"><?= icon('plus', 16) ?>&nbsp;Zadatak</a><?php endif; ?></header>
        <?php if ($tasks): ?>
            <div class="list">
                <?php foreach ($tasks as $tk): [$tl, $tc] = task_status_line($tk); ?>
                    <a class="list-row" href="<?= e(url('zadatak', ['id' => $tk['id']])) ?>"><span><span class="strong"><?= e($tk['title']) ?></span><br><span class="small status <?= e($tc) ?>"><i></i><?= e($tk['assigned_name']) ?> · <?= e($tl) ?></span></span><?= icon('chevron') ?></a>
                <?php endforeach; ?>
            </div>
        <?php else: ?><p class="small muted">Nema zadataka.</p><?php endif; ?>
    </section>

    <?php if ($p['status'] === 'aktivan'): ?>
        <a class="btn warn block" href="<?= e(url('zastoj', ['project' => $pid])) ?>"><?= icon('alert', 18) ?>Prijavi problem na projektu</a>
    <?php endif; ?>
    <?php if ($p['description']): ?><section class="card"><div class="label">NAPOMENA</div><p><?= nl2br(e($p['description'])) ?></p></section><?php endif; ?>
    <?php if ($p['notes'] && $isOwner): ?><section class="card"><div class="label">INTERNA NAPOMENA</div><p><?= nl2br(e($p['notes'])) ?></p></section><?php endif; ?>
    <?php if ($parent || $children): ?>
        <section class="list">
            <?php if ($parent): ?><a class="list-row" href="<?= e(url('projekat', ['id' => $parent['id']])) ?>"><span>Reklamacija za: <strong><?= e($parent['customer_name']) ?></strong> <span class="mono muted small"><?= e($parent['code']) ?></span></span><?= icon('chevron') ?></a><?php endif; ?>
            <?php foreach ($children as $c): ?><a class="list-row" href="<?= e(url('projekat', ['id' => $c['id']])) ?>"><span>Reklamacija <span class="mono muted small"><?= e($c['code']) ?></span> · <span class="<?= $c['status'] === 'zavrsen' ? 'muted' : 'warn' ?>"><?= $c['status'] === 'zavrsen' ? 'rešena' : 'otvorena' ?></span></span><?= icon('chevron') ?></a><?php endforeach; ?>
        </section>
    <?php endif; ?>
    <?php if ($isOwner && $p['archived_at']): ?>
        <div class="btn-row"><?= action_button('restore', $pid, 'Vrati iz arhive', 'btn ghost grow') ?><?= action_button('delete', $pid, 'Obriši trajno', 'btn warn grow', [], 'Trajno obrisati projekat i sve slike? Ovo se ne može vratiti.') ?></div>
    <?php endif; ?>

<?php elseif ($t === 'proizvodnja'): ?>
    <section class="card stack">
        <div class="row-between"><strong>Radni nalog</strong><?php if ($order): ?><span class="pill <?= $order['accepted_at'] ? 'st-zavrseno' : 'st-ceka' ?>"><?= $order['accepted_at'] ? 'Preuzet' : 'Izdat · nije preuzet' ?></span><?php endif; ?></div>
        <?php if ($order): ?>
            <div class="small">
                Verzija <strong>v<?= (int) $order['version'] ?></strong> · izdao <?= e(user_name($order['issued_by'] ? (int) $order['issued_by'] : null)) ?> <?= e(fmt_dm(substr($order['issued_at'], 0, 10))) ?>
                <?php if ($order['accepted_at']): ?> · preuzeo <?= e(user_name((int) $order['accepted_by'])) ?> <?= e(fmt_dm(substr($order['accepted_at'], 0, 10))) ?><?php endif; ?>
                <?php if ($order['note']): ?><br><span class="muted"><?= e($order['note']) ?></span><?php endif; ?>
            </div>
            <div class="btn-row">
                <?php if ($order['file_id']): ?><a class="btn ghost grow" href="<?= e(file_url((int) $order['file_id'])) ?>" target="_blank" rel="noopener"><?= icon('file', 18) ?>Otvori nalog</a><?php endif; ?>
                <?php if (!$order['accepted_at'] && in_array($user['role'], ['hala', 'vlasnik'], true) && $p['status'] === 'aktivan'): ?>
                    <?= action_button('order_accept', (int) $order['id'], 'Preuzeo nalog', 'btn primary grow', [], null, 'check') ?>
                <?php endif; ?>
            </div>
            <?php if (count($b['orders']) > 1): ?>
                <details><summary class="small muted">Ranije verzije (<?= count($b['orders']) - 1 ?>)</summary>
                    <ul class="plain small"><?php foreach (array_slice(array_reverse($b['orders']), 1) as $o): ?>
                        <li>v<?= (int) $o['version'] ?> · <?= e(fmt_date(substr($o['issued_at'], 0, 10))) ?><?= $o['file_id'] ? ' · <a class="accent" href="' . e(file_url((int) $o['file_id'])) . '" target="_blank" rel="noopener">PDF</a>' : '' ?><?= $o['accepted_at'] ? ' · preuzeto' : '' ?></li>
                    <?php endforeach; ?></ul></details>
            <?php endif; ?>
        <?php else: ?>
            <p class="muted">Nalog još nije izdat — hala ne počinje izradu bez naloga.</p>
        <?php endif; ?>
        <?php if ($isOwner && in_array($p['status'], ['aktivan', 'nacrt'], true)): ?>
            <details class="mini-form"><summary class="btn ghost block"><?= icon('file', 18) ?><?= $order ? 'Izdaj novu verziju naloga' : 'Izdaj nalog' ?></summary>
                <form method="post" action="<?= e(url() . 'api/akcija.php') ?>" class="stack">
                    <?= csrf_field() ?><?= once_field() ?>
                    <input type="hidden" name="action" value="order_issue"><input type="hidden" name="id" value="<?= $pid ?>"><input type="hidden" name="back" value="<?= e(here()) ?>">
                    <?= uploader('file_id', 'PDF naloga', false, 'pdf', true, '', []) ?>
                    <input class="input" name="note" placeholder="<?= $order ? 'Šta je izmenjeno (vidi hala)' : 'Napomena (opciono)' ?>">
                    <button class="btn primary block" type="submit">Izdaj</button>
                </form></details>
        <?php endif; ?>
    </section>

    <?php if (!$b['systems']): ?><p class="muted">Ovaj posao nema proizvodnju.</p><?php endif; ?>
    <?php foreach ($b['systems'] as $s):
        $phs = array_values(array_filter($b['phases'], fn($ph) => (int) $ph['system_id'] === (int) $s['id']));
        $done = count(array_filter($phs, fn($ph) => $ph['status'] === 'zavrseno'));
    ?>
        <section class="stack">
            <h2 class="sec-head"><span><?= e(mb_strtoupper(system_label($s))) ?></span><span><?= $done ?> / <?= count($phs) ?></span></h2>
            <ol class="timeline">
                <?php foreach ($phs as $ph):
                    $phId = (int) $ph['id'];
                    $can = can_act_phase($user, $p, $ph);
                    $phFiles = $filesBy['phase'][$phId] ?? [];
                    $dot = match ($ph['status']) { 'zavrseno' => 'done', 'zastoj' => 'warn', 'u_toku' => 'cur', default => '' };
                ?>
                    <li class="tl <?= $dot ?>">
                        <span class="tl-dot"><?= $ph['status'] === 'zavrseno' ? icon('check', 16) : '' ?></span>
                        <div class="tl-card<?= $ph['status'] === 'zavrseno' ? ' quiet' : '' ?>">
                            <div class="row-between"><strong><?= e($ph['name']) ?></strong><?= $statusPill($ph['status']) ?></div>
                            <div class="small muted">
                                <?= e(user_name($ph['assignee_id'] ? (int) $ph['assignee_id'] : null)) ?>
                                <?= $ph['due_date'] ? ' · rok ' . e(fmt_dm($ph['due_date'])) : '' ?>
                                <?= $ph['completed_at'] ? ' · završeno ' . e(fmt_dm(substr($ph['completed_at'], 0, 10))) . ($ph['imported'] ? ' (uneto)' : '') : '' ?>
                                <?= $phFiles ? ' · <a class="accent" href="' . e(url('galerija', ['id' => $pid, 'phase' => $phId])) . '">' . count($phFiles) . ' foto</a>' : '' ?>
                            </div>
                            <?php if ($ph['note']): ?><div class="small"><?= e($ph['note']) ?></div><?php endif; ?>
                            <?php if (isset($stopByPhase[$phId])): ?><div class="small warn"><?= e(stoppage_text($stopByPhase[$phId], $nv)) ?></div><?php endif; ?>
                            <?php if ($can): ?>
                                <div class="btn-row">
                                    <?php if ($ph['status'] === 'ceka'): ?><?= action_button('phase_start', $phId, 'Počni', 'btn ghost sm') ?><?php endif; ?>
                                    <a class="btn primary sm grow" href="<?= e(url('faza', ['id' => $phId])) ?>"><?= icon('camera', 16) ?>Završi</a>
                                    <?php if ($ph['status'] !== 'zastoj'): ?><a class="btn warn sm" href="<?= e(url('zastoj', ['phase' => $phId])) ?>">Zastoj</a><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($isOwner && $ph['status'] !== 'zavrseno'): ?>
                                <details class="mini-form"><summary class="small muted">Odgovoran i rok</summary>
                                    <form method="post" action="<?= e(url() . 'api/akcija.php') ?>" class="grid2">
                                        <?= csrf_field() ?><?= once_field() ?>
                                        <input type="hidden" name="action" value="phase_assign"><input type="hidden" name="id" value="<?= $phId ?>"><input type="hidden" name="back" value="<?= e(here()) ?>">
                                        <select class="input" name="assignee_id"><?php foreach ($halaUsers as $uid => $u): ?><option value="<?= $uid ?>"<?= (int) $ph['assignee_id'] === $uid ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
                                        <input class="input mono" type="date" name="due_date" value="<?= e($ph['due_date']) ?>">
                                        <button class="btn ghost sm" type="submit">Sačuvaj</button>
                                    </form></details>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endforeach; ?>

<?php elseif ($t === 'montaza'): ?>
    <?php if ($isOwner && in_array($p['status'], ['aktivan', 'nacrt'], true)): ?>
        <a class="btn primary block" href="<?= e(url('termin', ['project' => $pid])) ?>"><?= icon('plus', 18) ?>Novi termin</a>
    <?php endif; ?>
    <?php if (!$b['visits']): ?><p class="muted">Još nema termina.</p><?php endif; ?>
    <?php foreach ($b['visits'] as $v):
        $vid = (int) $v['id'];
        $checks = readiness($b, $v);
        $vFiles = $filesBy['visit'][$vid] ?? [];
        $overlap = $v['status'] === 'planiran' ? visit_overlaps($v['team_id'] ? (int) $v['team_id'] : null, $v['start_date'], (int) $v['days'], $vid) : [];
        $canV = can_act_visit($user, $p, $v);
        [$vsCls, $vsLabel] = $vStatus[$v['status']] ?? ['ceka', $v['status']];
        $needPre = $v['status'] === 'planiran' && $p['payment_when'] === 'pre' && visit_collects($b, $v) && $v['kind'] !== 'dijagnoza' && !$isOwner;
    ?>
        <section class="card stack visit">
            <div class="row-between">
                <div><strong><?= e(VISIT_KINDS[$v['kind']]) ?></strong>
                    <span class="pill <?= $v['confirmed'] ? 'st-zavrseno' : 'st-ceka' ?>"><?= $v['confirmed'] ? 'Potvrđeno' : 'Okvirno' ?></span></div>
                <span class="pill st-<?= $vsCls ?>"><?= e($vsLabel) ?></span>
            </div>
            <div class="mono"><?= e(visit_when($v)) ?><?= (int) $v['days'] > 1 ? ' · ' . (int) $v['days'] . ' dana' : '' ?></div>
            <div class="small muted"><?= icon('users', 14) ?> <?= e($v['team_name'] ?? 'ekipa nije određena') ?></div>
            <?php if ($v['note']): ?><div class="small"><?= nl2br(e(trim($v['note']))) ?></div><?php endif; ?>
            <?php if ($v['status'] === 'planiran' && $v['kind'] !== 'dijagnoza'): ?>
                <ul class="checks-list">
                    <?php foreach ($checks as [$label, $ok]): ?><li class="<?= $ok ? 'ok' : 'no' ?>"><?= icon($ok ? 'check' : 'alert', 14) ?><?= e($label) ?></li><?php endforeach; ?>
                </ul>
                <?php if ($v['readiness_override']): ?><div class="small muted">Izuzetak vlasnika: <?= e($v['readiness_override']) ?></div><?php endif; ?>
            <?php endif; ?>
            <?php foreach ($overlap as $o): ?><div class="small warn">Preklapanje: ista ekipa je na <?= e($o['customer_name']) ?> (<?= e(visit_when($o, false)) ?>)</div><?php endforeach; ?>
            <?php if (isset($stopByVisit[$vid])): ?><div class="small warn"><?= e(stoppage_text($stopByVisit[$vid], $v)) ?></div><?php endif; ?>
            <?php if ($v['status'] === 'zavrseno'): ?>
                <div class="small"><strong><?= e(VISIT_RESULTS[$v['result']] ?? '') ?></strong> · <?= e(user_name($v['completed_by'] ? (int) $v['completed_by'] : null)) ?> <?= e(fmt_dm(substr((string) $v['completed_at'], 0, 10))) ?>
                    <?= $vFiles ? ' · <a class="accent" href="' . e(url('galerija', ['id' => $pid, 'visit' => $vid])) . '">' . count($vFiles) . ' foto</a>' : '' ?></div>
                <?php if ($v['remaining']): ?><div class="small warn">Ostalo: <?= e($v['remaining']) ?></div><?php endif; ?>
            <?php endif; ?>
            <?php if ($canV): ?>
                <?php if ($needPre && $v['start_date']): ?>
                    <form method="post" action="<?= e(url() . 'api/akcija.php') ?>" class="stack">
                        <?= csrf_field() ?><?= once_field() ?>
                        <input type="hidden" name="action" value="visit_start"><input type="hidden" name="id" value="<?= $vid ?>"><input type="hidden" name="back" value="<?= e(here()) ?>">
                        <div class="grid2"><label class="amount"><span>Naplaćeno</span><input class="mono" name="paid" value="<?= e(fmt_m((string) $pay['remaining'])) ?>" inputmode="decimal"><span class="mono">€</span></label>
                            <select class="input" name="method" aria-label="Način"><?php foreach (METHOD_LABELS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
                        <button class="btn primary block" type="submit"><?= icon('cash', 18) ?>Naplaćeno — počinjem</button>
                    </form>
                <?php endif; ?>
                <div class="btn-row">
                    <?php if ($v['status'] === 'planiran' && $v['start_date'] && !$needPre): ?><?= action_button('visit_start', $vid, 'Počinjem', 'btn ghost grow', [], null, 'play') ?><?php endif; ?>
                    <?php if ($v['status'] === 'u_toku' || !$needPre): ?><a class="btn primary grow" href="<?= e(url('teren_zavrsi', ['id' => $vid])) ?>"><?= icon('check', 18) ?>Završi</a><?php endif; ?>
                    <a class="btn warn" href="<?= e(url('zastoj', ['visit' => $vid])) ?>">Problem</a>
                </div>
            <?php endif; ?>
            <?php if ($isOwner && $v['status'] === 'planiran'): ?>
                <div class="btn-row">
                    <a class="btn ghost sm grow" href="<?= e(url('termin', ['project' => $pid, 'id' => $vid])) ?>"><?= icon('edit', 16) ?>Izmeni / pomeri</a>
                    <details class="mini-form grow"><summary class="btn ghost sm block">Otkaži</summary>
                        <form method="post" action="<?= e(url() . 'api/akcija.php') ?>" class="stack">
                            <?= csrf_field() ?><?= once_field() ?>
                            <input type="hidden" name="action" value="visit_cancel"><input type="hidden" name="id" value="<?= $vid ?>"><input type="hidden" name="back" value="<?= e(here()) ?>">
                            <input class="input" name="reason" placeholder="Razlog otkazivanja" required>
                            <button class="btn warn block" type="submit">Otkaži termin</button>
                        </form></details>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

<?php else: /* dokumenti i istorija */ ?>
    <section class="stack">
        <h2 class="sec-head"><span>MESTO MONTAŽE · PRE</span></h2>
        <?php if ($lokacija): ?><a href="<?= e(file_url((int) $lokacija['id'])) ?>" target="_blank" rel="noopener"><img class="doc-photo" src="<?= e(file_url((int) $lokacija['id'], true)) ?>" alt="Mesto montaže"></a>
        <?php else: ?><p class="small muted">Nema slike.</p><?php endif; ?>
        <?php if ($isOwner): ?>
            <form method="post" action="<?= e(url() . 'api/akcija.php') ?>" class="stack">
                <?= csrf_field() ?><?= once_field() ?>
                <input type="hidden" name="action" value="lokacija"><input type="hidden" name="id" value="<?= $pid ?>"><input type="hidden" name="back" value="<?= e(here()) ?>">
                <?= uploader('file_id', $lokacija ? 'Zameni sliku' : 'Dodaj sliku', false, 'foto', true, '', []) ?>
                <button class="btn ghost block" type="submit">Sačuvaj sliku</button>
            </form>
        <?php endif; ?>
    </section>
    <section class="stack">
        <h2 class="sec-head"><span>NALOZI</span><span><?= count($b['orders']) ?></span></h2>
        <?php foreach (array_reverse($b['orders']) as $o): ?>
            <div class="list-row"><span>v<?= (int) $o['version'] ?> · <?= e(fmt_date(substr($o['issued_at'], 0, 10))) ?> · <?= $o['accepted_at'] ? 'preuzet' : 'nije preuzet' ?></span>
                <?php if ($o['file_id']): ?><a class="chip" href="<?= e(file_url((int) $o['file_id'])) ?>" target="_blank" rel="noopener">PDF</a><?php endif; ?></div>
        <?php endforeach; ?>
    </section>
    <section class="stack">
        <h2 class="sec-head"><span>FOTOGRAFIJE</span><a href="<?= e(url('galerija', ['id' => $pid])) ?>">sve ›</a></h2>
        <div class="gallery">
            <?php foreach (array_slice(array_values(array_filter($files, fn($f) => str_starts_with($f['mime'], 'image/'))), -9) as $f): ?>
                <a href="<?= e(file_url((int) $f['id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(file_url((int) $f['id'], true)) ?>" alt="" loading="lazy"></a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php if ($allStops): ?>
        <section class="stack">
            <h2 class="sec-head"><span>ZASTOJI (SVI)</span><span><?= count($allStops) ?></span></h2>
            <ul class="plain small">
                <?php foreach ($allStops as $s): ?>
                    <li><?= e(fmt_dm(substr($s['created_at'], 0, 10))) ?> · <?= e(REASON_LABELS[$s['reason']]) ?>: <?= e($s['note']) ?> · <?= $s['resolved_at'] ? 'rešeno ' . e(fmt_dm(substr($s['resolved_at'], 0, 10))) : '<span class="warn">otvoren</span>' ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
    <section class="stack">
        <h2 class="sec-head"><span>ISTORIJA</span><span><?= count($log) ?></span></h2>
        <ul class="history-list">
            <?php foreach ($log as $l): ?>
                <li><span class="mono muted small"><?= e((new DateTimeImmutable($l['created_at']))->format('j.n. H:i')) ?></span>
                    <span><?= e(activity_label($l)) ?><?= $l['user_name'] ? ' · ' . e($l['user_name']) : '' ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

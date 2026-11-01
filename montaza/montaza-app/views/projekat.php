<?php
/** @var array $tasks @var array $user @var array $project @var array $items @var array $filesByItem @var ?array $lokacija @var ?array $stoppage @var ?array $parent @var array $children @var array $log */
$p = $project;
$isOwner = $user['role'] === 'vlasnik';
[$tag, $tagCls] = type_tag($p);
$sub = implode(' · ', array_filter([$p['city'], $p['description'] ? trim(str_replace("\n", ' · ', $p['description'])) : null]));
$week = $p['install_week_start'] ? new DateTimeImmutable($p['install_week_start']) : null;
$late = $p['status'] === 'aktivan' ? days_late($p['deadline']) : 0;
$cur = current_item($items);
$done = items_done($items);

$logLabels = [
    'project_created' => 'Projekat otvoren', 'project_updated' => 'Projekat izmenjen', 'project_completed' => 'Projekat završen',
    'item_completed' => 'Stavka završena', 'file_uploaded' => 'Dodat fajl', 'payment_confirmed' => 'Naplata potvrđena',
    'stoppage' => 'Prijavljen zastoj', 'stoppage_resolved' => 'Zastoj rešen', 'item_started' => 'Stavka započeta',
];
?>
<div class="hero<?= $lokacija ? '' : ' empty-hero' ?>">
    <a class="icon-btn hero-back" href="<?= e(url('projekti')) ?>" aria-label="Nazad"><?= icon('back') ?></a>
    <?php if ($isOwner): ?>
        <a class="icon-btn hero-edit" href="<?= e(url('izmeni', ['id' => $p['id']])) ?>" aria-label="Izmeni projekat"><?= icon('edit') ?></a>
    <?php endif; ?>
    <?php if ($lokacija): ?>
        <a href="<?= e(file_url((int) $lokacija['id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(file_url((int) $lokacija['id'])) ?>" alt="Mesto montaže pre radova"></a>
    <?php else: ?>
        <?= icon('image', 44) ?>
    <?php endif; ?>
    <span class="hero-label mono">MESTO MONTAŽE · PRE</span>
    <?php if ($isOwner && $p['status'] === 'aktivan'): ?>
        <form method="post" action="<?= e(url() . 'api/lokacija.php') ?>" enctype="multipart/form-data" class="hero-upload" data-autosubmit>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <label class="icon-btn" aria-label="<?= $lokacija ? 'Promeni sliku lokacije' : 'Dodaj sliku lokacije' ?>">
                <input type="file" name="lokacija" accept="image/*" capture="environment" data-resize hidden><?= icon('camera') ?>
            </label>
        </form>
    <?php endif; ?>
</div>

<section class="phead">
    <div class="pcard-name">
        <h1 class="h1"><?= e($p['customer_name']) ?></h1>
        <span class="tag <?= e($tagCls) ?>"><?= e($tag) ?></span>
    </div>
    <?php if ($sub !== ''): ?><p class="pcard-sub"><?= e($sub) ?></p><?php endif; ?>
    <p class="mono muted small"><?= e($p['code']) ?><?= $p['address'] ? ' · ' . e($p['address']) : '' ?></p>
</section>

<div class="facts">
    <div class="fact">
        <span><?= $p['type'] === 'reklamacija' ? 'Nedelja servisa' : 'Nedelja' ?></span>
        <strong class="mono"><?= $week ? (int) $week->format('W') . ' · ' . e($week->format('j') . '–' . $week->modify('+6 days')->format('j')) : '—' ?></strong>
    </div>
    <div class="fact">
        <span>Krajnji rok</span>
        <strong class="mono<?= $late ? ' warn' : '' ?>"><?= $p['deadline'] ? e(fmt_dm($p['deadline'])) : '—' ?></strong>
    </div>
    <?php if ($p['phone']): ?>
        <a class="fact" href="tel:<?= e(preg_replace('/[^\d+]/', '', $p['phone'])) ?>">
            <span>Kupac</span><strong class="accent">Pozovi</strong>
        </a>
    <?php else: ?>
        <div class="fact"><span>Kupac</span><strong class="muted">—</strong></div>
    <?php endif; ?>
</div>

<?php if ($late): ?>
    <div class="alert warn">Kasni <?= $late ?> <?= $late === 1 ? 'dan' : 'dana' ?> — krajnji rok je bio <?= e(fmt_date($p['deadline'])) ?>.</div>
<?php endif; ?>
<?php if ($stoppage): ?>
    <div class="alert warn"><strong>Zastoj:</strong> <?= e(REASON_LABELS[$stoppage['reason']]) ?><?= $stoppage['note'] ? ' — ' . e($stoppage['note']) : '' ?></div>
<?php endif; ?>

<?php if (can_see_payment($user) && $p['payment_when'] !== 'ne'): ?>
    <div class="paybox<?= $p['payment_confirmed_at'] ? ' done' : '' ?>">
        <?= icon('cash', 26) ?>
        <div>
            <strong><?= $p['payment_confirmed_at'] ? 'Naplaćeno' : ($p['payment_when'] === 'pre' ? 'Naplatiti PRE početka montaže' : 'Naplatiti POSLE montaže') ?></strong>
            <span><?php
                if ($p['payment_confirmed_at']) {
                    echo e(fmt_date(substr($p['payment_confirmed_at'], 0, 10)));
                    if ($p['payment_received'] !== null && $p['payment_received'] !== $p['payment_amount']) {
                        echo ' · primljeno ' . e(fmt_money($p['payment_received']));
                    }
                } else {
                    echo $p['payment_when'] === 'pre' ? 'Bez naplate ekipa ne počinje' : 'Montaža se ne zatvara bez naplate';
                }
            ?></span>
        </div>
        <strong class="mono amount-big"><?= e(fmt_money($p['payment_amount'])) ?></strong>
    </div>
<?php endif; ?>

<section class="items">
    <header class="row-between">
        <h2 class="h2">Stavke</h2>
        <span class="mono muted small"><?= $done ?> / <?= count($items) ?> GOTOVO</span>
    </header>
    <ol class="timeline">
        <?php foreach ($items as $it):
            $canAct = can_act_item($user, $p, $it);
            // Kartica sa dugmadima: trenutna stavka, stavka u toku ili u zastoju
            $isCur = $p['status'] === 'aktivan' && $it['status'] !== 'zavrseno'
                && (($cur && $cur['id'] === $it['id']) || in_array($it['status'], ['u_toku', 'zastoj'], true));
            $itFiles = $filesByItem[$it['id']] ?? [];
            $pdfs = array_values(array_filter($itFiles, fn($f) => $f['kind'] === 'nalog_pdf'));
            $photos = array_values(array_filter($itFiles, fn($f) => $f['kind'] !== 'nalog_pdf'));
            $resp = mb_strtoupper(ROLE_LABELS[$it['responsible']]);
            $dot = match ($it['status']) { 'zavrseno' => 'done', 'zastoj' => 'warn', default => $isCur ? 'cur' : '' };
            if ($it['status'] === 'zavrseno') {
                $meta = fmt_dm(substr((string) $it['completed_at'], 0, 10)) . ' · ' . $resp . ($it['imported'] ? ' · UNETO' : '');
            } elseif ($it['kind'] === 'montaza' && $week) {
                $meta = 'NED. ' . (int) $week->format('W') . ' · ' . $resp;
            } else {
                $meta = ($it['due_date'] ? 'ROK ' . fmt_dm($it['due_date']) . ' · ' : '') . $resp;
            }
            $itStop = $it['status'] === 'zastoj' ? q_one('SELECT * FROM stoppages WHERE item_id = ? AND resolved_at IS NULL ORDER BY id DESC LIMIT 1', [$it['id']]) : null;
            $payFirst = $canAct && $it['status'] === 'ceka' && is_payment_item($it) && rule_start_blocked($p, $it);
            $warnMontaza = $isCur && $canAct ? rule_montaza_warning($items, $it) : null;
        ?>
            <li class="tl <?= $dot ?>">
                <span class="tl-dot"><?= $it['status'] === 'zavrseno' ? icon('check', 18) : '' ?></span>
                <?php if ($isCur): ?>
                    <div class="tl-card<?= $it['status'] === 'zastoj' ? ' warn' : '' ?>">
                        <div class="row-between">
                            <strong><?= e(ITEM_LABELS[$it['kind']]) ?></strong>
                            <span class="mono small"><?= $it['status'] === 'u_toku' ? 'U TOKU · ' : '' ?><?= e($meta) ?></span>
                        </div>
                        <?php if ($itStop): ?>
                            <div class="small warn">Zastoj: <?= e(REASON_LABELS[$itStop['reason']]) ?><?= $itStop['note'] ? ' — ' . e($itStop['note']) : '' ?></div>
                        <?php endif; ?>
                        <?php if ($warnMontaza): ?><div class="small warn"><?= e($warnMontaza) ?></div><?php endif; ?>
                        <?php if ($photos): ?>
                            <a class="small muted" href="<?= e(url('galerija', ['id' => $p['id'], 'item' => $it['id']])) ?>"><?= count($photos) ?> foto do sada</a>
                        <?php endif; ?>

                        <?php if ($it['kind'] === 'nalog' && $isOwner): ?>
                            <form method="post" action="<?= e(url() . 'api/nalog_pdf.php') ?>" enctype="multipart/form-data" data-autosubmit>
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <label class="btn primary block"><input type="file" name="nalog_pdf" accept="application/pdf,.pdf" hidden><?= icon('file', 20) ?>Zakači PDF i završi</label>
                            </form>
                        <?php elseif ($payFirst): ?>
                            <form method="post" action="<?= e(url() . 'api/stavka.php') ?>" class="stack">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="start">
                                <input type="hidden" name="item" value="<?= (int) $it['id'] ?>">
                                <label class="amount"><span>Naplaćeno</span>
                                    <input class="mono" name="paid_amount" value="<?= e(rtrim(rtrim((string) $p['payment_amount'], '0'), '.')) ?>" inputmode="decimal"><span class="mono">€</span></label>
                                <div class="btn-row">
                                    <button class="btn primary grow" type="submit"><?= icon('cash', 20) ?>Naplaćeno — počinjem</button>
                                    <a class="btn warn" href="<?= e(url('zastoj', ['item' => $it['id']])) ?>">Zastoj</a>
                                </div>
                            </form>
                        <?php elseif ($canAct && $it['status'] === 'zastoj'): ?>
                            <div class="btn-row">
                                <form method="post" action="<?= e(url() . 'api/stavka.php') ?>" class="grow">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="resume">
                                    <input type="hidden" name="item" value="<?= (int) $it['id'] ?>">
                                    <button class="btn ghost block" type="submit">Nastavi rad</button>
                                </form>
                                <a class="btn primary" href="<?= e(url('zavrsi', ['item' => $it['id']])) ?>"><?= icon('camera', 20) ?>Završi</a>
                            </div>
                        <?php elseif ($canAct): ?>
                            <div class="btn-row">
                                <a class="btn primary grow" href="<?= e(url('zavrsi', ['item' => $it['id']])) ?>"><?= icon('camera', 20) ?>Završi + foto</a>
                                <a class="btn warn" href="<?= e(url('zastoj', ['item' => $it['id']])) ?>">Zastoj</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="tl-body">
                        <div>
                            <div class="tl-title"><?= e(ITEM_LABELS[$it['kind']]) ?></div>
                            <div class="mono muted small"><?= e($meta) ?></div>
                            <?php if ($it['note'] && $it['status'] === 'zavrseno'): ?><div class="small muted tl-note"><?= e($it['note']) ?></div><?php endif; ?>
                        </div>
                        <div class="tl-files">
                            <?php foreach ($pdfs as $pdf): ?>
                                <a class="chip" href="<?= e(file_url((int) $pdf['id'])) ?>" target="_blank" rel="noopener">Nalog <?= e(substr($p['code'], 5)) ?>.pdf</a>
                            <?php endforeach; ?>
                            <?php if ($photos): ?>
                                <a class="chip" href="<?= e(url('galerija', ['id' => $p['id'], 'item' => $it['id']])) ?>"><?= count($photos) ?> foto</a>
                            <?php endif; ?>
                            <?php if ($canAct && $it['kind'] !== 'nalog'): ?>
                                <a class="chip" href="<?= e(url('zavrsi', ['item' => $it['id']])) ?>">Završi</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<?php if ($tasks || $p['status'] === 'aktivan'): ?>
    <section class="stack">
        <header class="row-between">
            <h2 class="h2">Zadaci</h2>
            <?php if ($p['status'] === 'aktivan'): ?><a class="chip" href="<?= e(url('novi_zadatak', ['project' => $p['id']])) ?>"><?= icon('plus', 16) ?>&nbsp;Zadatak</a><?php endif; ?>
        </header>
        <?php if ($tasks): ?>
            <div class="list">
                <?php foreach ($tasks as $t): [$tl, $tc] = task_status_line($t); ?>
                    <a class="list-row" href="<?= e(url('zadatak', ['id' => $t['id']])) ?>">
                        <span><span class="strong"><?= e($t['title']) ?></span><br><span class="small status <?= e($tc) ?>"><i></i><?= e($t['assigned_name']) ?> · <?= e($tl) ?></span></span><?= icon('chevron') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($p['notes'] && $isOwner): ?>
    <section class="card"><div class="label">NAPOMENA</div><p><?= nl2br(e($p['notes'])) ?></p></section>
<?php endif; ?>

<?php if ($parent || $children): ?>
    <section class="list">
        <?php if ($parent): ?>
            <a class="list-row" href="<?= e(url('projekat', ['id' => $parent['id']])) ?>"><span>Reklamacija za: <strong><?= e($parent['customer_name']) ?></strong> <span class="mono muted small"><?= e($parent['code']) ?></span></span><?= icon('chevron') ?></a>
        <?php endif; ?>
        <?php foreach ($children as $c): ?>
            <a class="list-row" href="<?= e(url('projekat', ['id' => $c['id']])) ?>"><span>Reklamacija <span class="mono muted small"><?= e($c['code']) ?></span></span><?= icon('chevron') ?></a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($log): ?>
    <details class="history">
        <summary>Istorija · <?= count($log) ?></summary>
        <ul>
            <?php foreach ($log as $l): ?>
                <li>
                    <span class="mono muted small"><?= e((new DateTimeImmutable($l['created_at']))->format('j.n. H:i')) ?></span>
                    <span><?= e($logLabels[$l['action']] ?? $l['action']) ?><?= $l['user_name'] ? ' · ' . e($l['user_name']) : '' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </details>
<?php endif; ?>

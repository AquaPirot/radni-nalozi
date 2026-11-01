<?php
/** @var array $user @var array $project @var array $items @var array $filesByItem @var ?array $lokacija @var ?array $stoppage @var ?array $parent @var array $children @var array $log */
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
            $isCur = $cur && $cur['id'] === $it['id'] && $p['status'] === 'aktivan';
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
        ?>
            <li class="tl <?= $dot ?>">
                <span class="tl-dot"><?= $it['status'] === 'zavrseno' ? icon('check', 18) : '' ?></span>
                <?php if ($isCur): ?>
                    <div class="tl-card">
                        <div class="row-between">
                            <strong><?= e(ITEM_LABELS[$it['kind']]) ?></strong>
                            <span class="mono small"><?= e($meta) ?></span>
                        </div>
                        <?php if ($it['kind'] === 'nalog' && $isOwner): ?>
                            <form method="post" action="<?= e(url() . 'api/nalog_pdf.php') ?>" enctype="multipart/form-data" data-autosubmit>
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <label class="btn primary block"><input type="file" name="nalog_pdf" accept="application/pdf,.pdf" hidden><?= icon('file', 20) ?>Zakači PDF i završi</label>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="tl-body">
                        <div>
                            <div class="tl-title"><?= e(ITEM_LABELS[$it['kind']]) ?></div>
                            <div class="mono muted small"><?= e($meta) ?></div>
                        </div>
                        <div class="tl-files">
                            <?php foreach ($pdfs as $pdf): ?>
                                <a class="chip" href="<?= e(file_url((int) $pdf['id'])) ?>" target="_blank" rel="noopener">Nalog <?= e(substr($p['code'], 5)) ?>.pdf</a>
                            <?php endforeach; ?>
                            <?php if ($photos): ?>
                                <a class="chip" href="<?= e(file_url((int) $photos[0]['id'])) ?>" target="_blank" rel="noopener"><?= count($photos) ?> foto</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

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

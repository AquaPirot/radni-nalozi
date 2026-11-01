<?php /** @var array $user @var array $b @var array $photos @var array $photoCount @var array $comments @var array $log @var ?array $parent @var array $children */
$p = $b['p'];
$pid = (int) $p['id'];
$isOwner = $user['role'] === 'vlasnik';
$tel = tel_url($p);
$map = maps_url($p);
$late = install_late($b);
[$line, $cls] = status_line($b);
$form = fn(string $action, string $inner, string $extra = '') => '<form method="post" action="' . e(url() . 'api/akcija.php') . '" ' . $extra . '>'
    . csrf_field() . once_field() . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $pid . '"><input type="hidden" name="back" value="' . e(here()) . '">' . $inner . '</form>';
?>
<section class="stack">
    <div class="p-addr"><?= icon('nav', 18) ?><span><?= e(implode(', ', array_filter([$p['address'], $p['city']])) ?: 'Adresa nije upisana') ?></span></div>
    <?php if ($p['phone']): ?><div class="p-addr"><?= icon('phone', 18) ?><span class="mono"><?= e($p['phone']) ?></span></div><?php endif; ?>
    <div class="btn-row">
        <?php if ($tel): ?><a class="btn ghost grow" href="<?= e($tel) ?>"><?= icon('phone', 20) ?>Pozovi</a><?php endif; ?>
        <?php if ($map): ?><a class="btn ghost grow" href="<?= e($map) ?>" target="_blank" rel="noopener"><?= icon('nav', 20) ?>Navigacija</a><?php endif; ?>
    </div>
    <?php if (!$b['stop']): ?><span class="status <?= e($cls) ?> big"><i></i><?= e($line) ?></span><?php endif; ?>
</section>

<?php if ($b['stop']): $s = $b['stop']; ?>
    <div class="problem">
        <strong>Zastoj · <?= e(REASON_LABELS[$s['reason']] ?? $s['reason']) ?></strong>
        <p><?= e($s['note']) ?></p>
        <span class="small muted"><?= e($s['created_name'] ?? '—') ?> · <?= e(fmt_ago($s['created_at'])) ?></span>
        <?= $form('stop_resolve', '<button class="btn ghost sm" type="submit">' . icon('check', 18) . 'Rešeno</button>', 'data-confirm="Zastoj je rešen?"') ?>
    </div>
<?php endif; ?>

<?php if ($p['description']): ?><section class="card"><div class="label">NAPOMENA</div><p><?= nl2br(e($p['description'])) ?></p></section><?php endif; ?>

<!-- Montaža -->
<section class="card stack" id="montaza">
    <div class="label">MONTAŽA</div>
    <?php if ($p['install_date']): $d = new DateTimeImmutable($p['install_date']); ?>
        <div class="when-big<?= $late ? ' warn' : '' ?>">
            <strong><?= e(ucfirst(DAYS_FULL[(int) $d->format('w')])) ?>, <?= e($d->format('j')) ?>. <?= e(mb_strtolower(MONTHS_FULL[(int) $d->format('n')])) ?></strong>
            <span><?= $p['install_time'] ? 'u ' . e(substr($p['install_time'], 0, 5)) : 'vreme nije određeno' ?><?= $late ? ' · kasni ' . $late . ' d' : '' ?></span>
        </div>
    <?php else: ?>
        <p class="muted">Datum još nije određen.</p>
    <?php endif; ?>
    <?php if ($isOwner && $p['status'] === 'aktivan'): ?>
        <details class="inline"<?= $p['install_date'] ? '' : ' open' ?>>
            <summary class="link"><?= $p['install_date'] ? 'Promeni dan ili vreme' : 'Odredi dan montaže' ?></summary>
            <?= $form('install', '<div class="grid2"><label class="field"><span>Dan</span><input class="input mono" type="date" name="install_date" value="' . e((string) $p['install_date']) . '"></label>'
                . '<label class="field"><span>Vreme (opciono)</span><input class="input mono" type="time" name="install_time" value="' . e($p['install_time'] ? substr($p['install_time'], 0, 5) : '') . '"></label></div>'
                . '<button class="btn primary block" type="submit">Sačuvaj — teren i hala dobijaju obaveštenje</button>', 'class="stack"') ?>
        </details>
    <?php endif; ?>
</section>

<!-- Nalog -->
<section class="card stack">
    <div class="label">RADNI NALOG</div>
    <?php if ($b['nalog']): ?>
        <a class="btn primary block" href="<?= e(file_url($b['nalog'])) ?>" target="_blank" rel="noopener"><?= icon('file', 20) ?>Otvori nalog (PDF)</a>
    <?php else: ?>
        <p class="muted">Nalog još nije dodat.</p>
    <?php endif; ?>
    <?php if ($isOwner): ?>
        <details class="inline"<?= $b['nalog'] ? '' : ' open' ?>>
            <summary class="link"><?= $b['nalog'] ? 'Zameni nalog novom verzijom' : 'Dodaj PDF naloga' ?></summary>
            <?= $form('nalog', uploader('file_id', 'PDF naloga', false, 'pdf', true) . '<button class="btn ghost block" type="submit">Sačuvaj nalog</button>', 'class="stack" data-gate') ?>
        </details>
    <?php endif; ?>
</section>

<!-- Stavke -->
<section class="stack">
    <h2 class="sec-head"><span>STAVKE</span><span><?= count(array_filter($b['items'], fn($i) => $i['status'] === 'zavrseno')) ?> / <?= count($b['items']) ?></span></h2>
    <div class="items-list">
        <?php foreach ($b['items'] as $it): $can = can_act_item($user, $it) && $p['status'] !== 'otkazan'; $iid = (int) $it['id']; ?>
            <div class="item-row st-<?= e($it['status']) ?>">
                <span class="item-dot"><?= $it['status'] === 'zavrseno' ? icon('check', 16) : '' ?></span>
                <div class="grow">
                    <strong><?= e(ITEM_LABELS[$it['kind']]) ?></strong>
                    <div class="small muted">
                        <?php if ($it['status'] === 'zavrseno'): ?>
                            <?= e(fmt_dm(substr((string) $it['completed_at'], 0, 10))) ?><?= $it['completed_by'] ? ' · ' . e(user_name((int) $it['completed_by'])) : '' ?><?= !empty($photoCount[$iid]) ? ' · ' . $photoCount[$iid] . ' sl.' : '' ?><?= $it['note'] ? ' · ' . e(short_desc($it['note'], 50)) : '' ?>
                        <?php elseif ($it['status'] === 'u_toku'): ?>U radu od <?= e(fmt_dm(substr((string) $it['started_at'], 0, 10))) ?>
                        <?php else: ?><?= e(['vlasnik' => 'vlasnik', 'hala' => 'hala', 'teren' => 'teren'][$it['responsible']]) ?><?php endif; ?>
                    </div>
                </div>
                <?php if ($can && $it['status'] !== 'zavrseno'): ?>
                    <?php if ($it['status'] === 'ceka' && $it['kind'] !== 'nalog'): ?>
                        <form method="post" action="<?= e(url() . 'api/akcija.php') ?>"><?= csrf_field() ?><?= once_field() ?><input type="hidden" name="action" value="item_start"><input type="hidden" name="id" value="<?= $iid ?>"><input type="hidden" name="back" value="<?= e(here()) ?>"><button class="btn ghost sm" type="submit">Počinjem</button></form>
                    <?php endif; ?>
                    <?php if (item_min_photos($it) > 0): ?>
                        <a class="btn primary sm" href="<?= e(url('stavka', ['id' => $iid])) ?>"><?= icon('camera', 16) ?>Završi</a>
                    <?php else: ?>
                        <form method="post" action="<?= e(url() . 'api/akcija.php') ?>" data-confirm="<?= e(ITEM_LABELS[$it['kind']]) ?> — završeno?"><?= csrf_field() ?><?= once_field() ?><input type="hidden" name="action" value="item_done"><input type="hidden" name="id" value="<?= $iid ?>"><input type="hidden" name="back" value="<?= e(here()) ?>"><button class="btn primary sm" type="submit"><?= icon('check', 16) ?>Završeno</button></form>
                    <?php endif; ?>
                <?php elseif ($it['status'] === 'zavrseno' && !empty($photoCount[$iid])): ?>
                    <a class="btn link sm" href="<?= e(url('stavka', ['id' => $iid])) ?>">Detalji</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Slike -->
<section class="stack" id="slike">
    <h2 class="sec-head"><span>SLIKE</span><span><?= count($photos) ?></span></h2>
    <?php if ($photos): ?>
        <div class="gallery">
            <?php foreach ($photos as $f): ?>
                <a href="<?= e(file_url((int) $f['id'])) ?>" target="_blank" rel="noopener">
                    <img src="<?= e(file_url((int) $f['id'], true)) ?>" alt="" loading="lazy">
                    <?php if ($f['kind'] === 'lokacija_pre'): ?><span class="ph-tag">PRE</span><?php elseif ($f['item_kind']): ?><span class="ph-tag"><?= e(mb_strtoupper(ITEM_LABELS[$f['item_kind']])) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?= $form('photos', uploader('foto_ids', 'Dodaj slike', true, 'foto', true) . '<button class="btn ghost block" type="submit">Sačuvaj slike</button>', 'class="stack card" data-gate') ?>
</section>

<!-- Komentari -->
<section class="stack" id="komentari">
    <h2 class="sec-head"><span>KOMENTARI</span><span><?= count($comments) ?></span></h2>
    <?php if ($comments): ?>
        <ol class="hist">
            <?php foreach ($comments as $c): ?>
                <li class="cmt<?= (int) $c['user_id'] === (int) $user['id'] ? ' mine' : '' ?>">
                    <div class="cmt-head"><strong><?= e($c['user_name'] ?? '—') ?></strong><span class="mono muted small"><?= e(fmt_ago($c['created_at'])) ?></span></div>
                    <?php if ($c['body']): ?><div><?= nl2br(e($c['body'])) ?></div><?php endif; ?>
                    <?php if ($c['file_id']): ?><a class="cmt-img" href="<?= e(file_url((int) $c['file_id'])) ?>" target="_blank" rel="noopener"><img src="<?= e(file_url((int) $c['file_id'], true)) ?>" alt="" loading="lazy"></a><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <?= $form('project_comment', '<label class="field"><span>Novi komentar</span><textarea class="input" name="body" rows="2" placeholder="Svi dobijaju obaveštenje"></textarea></label>'
        . uploader('file_id', 'Slika uz komentar', false, 'foto', false, 'opciono', [])
        . '<button class="btn primary block" type="submit">Pošalji</button>', 'class="stack card" data-autosave="pcmt-' . $pid . '"') ?>
</section>

<?php if (!$b['stop'] && $p['status'] === 'aktivan'): ?>
    <details class="card">
        <summary class="row-between"><span class="warn"><?= icon('alert', 18) ?> Prijavi zastoj</span><?= icon('chevron') ?></summary>
        <?php $chips = ''; foreach (REASONS_NEW as $r) { $chips .= '<label class="chip-radio"><input type="radio" name="reason" value="' . $r . '" required><span>' . e(REASON_LABELS[$r]) . '</span></label>'; } ?>
        <?= $form('stop_open', '<div class="chips">' . $chips . '</div><label class="field"><span>Šta je problem</span><textarea class="input" name="note" rows="2" required placeholder="npr. čeka se platno, stiže u sredu"></textarea></label><button class="btn warn block" type="submit">Prijavi — svi dobijaju obaveštenje</button>', 'class="stack" style="margin-top:12px"') ?>
    </details>
<?php endif; ?>

<?php if ($parent || $children): ?>
    <section class="list">
        <?php if ($parent): ?><a class="list-row" href="<?= e(url('projekat', ['id' => $parent['id']])) ?>"><span>Reklamacija za: <strong><?= e($parent['customer_name']) ?></strong> <span class="mono muted small"><?= e($parent['code']) ?></span></span><?= icon('chevron') ?></a><?php endif; ?>
        <?php foreach ($children as $c): ?><a class="list-row" href="<?= e(url('projekat', ['id' => $c['id']])) ?>"><span>Reklamacija <span class="mono muted small"><?= e($c['code']) ?></span> · <span class="<?= $c['status'] === 'zavrsen' ? 'muted' : 'warn' ?>"><?= $c['status'] === 'zavrsen' ? 'rešena' : 'otvorena' ?></span></span><?= icon('chevron') ?></a><?php endforeach; ?>
    </section>
<?php endif; ?>

<details class="card">
    <summary class="row-between"><span>Istorija</span><?= icon('chevron') ?></summary>
    <ol class="hist" style="margin-top:12px">
        <?php foreach ($log as $l): ?>
            <li class="sys"><span class="mono muted small"><?= e((new DateTimeImmutable($l['created_at']))->format('j.n. H:i')) ?></span> <strong><?= e($l['user_name'] ?? '—') ?></strong> <?= e(activity_label($l)) ?></li>
        <?php endforeach; ?>
    </ol>
</details>

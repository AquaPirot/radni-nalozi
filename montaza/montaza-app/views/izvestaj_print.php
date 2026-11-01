<?php /** @var string $monthLabel @var array $completed @var int $pergole @var int $reklamacije @var mixed $avgDays @var array $stops @var int $lostDays @var array $byReason @var array $itemSpeed @var array $carry @var int $carryLate @var array $paid @var array $tasks @var array $photos @var string $nextLabel @var bool $isCurrent */ ?>
<!doctype html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Izveštaj — <?= e($monthLabel) ?> — AG GROUP</title>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;600;800&family=Geist+Mono:wght@500&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font: 13px/1.45 'Geist', system-ui, sans-serif; color: #111; background: #fff; padding: 28px; max-width: 900px; margin: 0 auto; }
        .mono { font-family: 'Geist Mono', ui-monospace, monospace; }
        header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 18px; }
        h1 { font-size: 26px; font-weight: 800; } h2 { font-size: 15px; margin: 22px 0 8px; }
        .eyebrow { font: 500 11px 'Geist Mono', monospace; letter-spacing: .14em; color: #666; }
        .tiles { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .tile { border: 1px solid #ddd; border-radius: 10px; padding: 10px 12px; }
        .tile b { display: block; font-size: 26px; font-weight: 800; }
        .tile span { color: #555; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; } td, th { text-align: left; padding: 6px 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        th { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: .06em; }
        .proj { display: grid; grid-template-columns: 1fr 150px 150px; gap: 10px; padding: 10px 0; border-bottom: 1px solid #eee; break-inside: avoid; }
        .proj img { width: 150px; height: 110px; object-fit: cover; border-radius: 6px; background: #f2f2f2; }
        .ph { width: 150px; height: 110px; border-radius: 6px; background: #f2f2f2; display: grid; place-items: center; color: #999; font-size: 11px; }
        .lbl { font: 500 10px 'Geist Mono', monospace; color: #666; margin-bottom: 2px; }
        .noprint { margin-bottom: 18px; } .noprint button { font: 600 15px 'Geist', sans-serif; padding: 10px 18px; border-radius: 10px; border: 0; background: #111; color: #fff; cursor: pointer; }
        @media print { body { padding: 0; } .noprint { display: none; } @page { margin: 14mm; } }
    </style>
</head>
<body>
<div class="noprint"><button onclick="window.print()">Sačuvaj kao PDF / Štampaj</button></div>
<header>
    <div><div class="eyebrow">AG GROUP · MONTAŽA</div><h1>Izveštaj — <?= e($monthLabel) ?></h1></div>
    <div class="mono" style="color:#666">Izrađeno <?= e(date('j.n.Y. H:i')) ?></div>
</header>

<div class="tiles">
    <div class="tile"><span>Završeno projekata</span><b><?= count($completed) ?></b><span><?= $reklamacije ?> reklamacija</span></div>
    <div class="tile"><span>Napravljeno pergola</span><b><?= $pergole ?></b></div>
    <div class="tile"><span>Otvaranje → kraj (novi)</span><b><?= $avgDays !== null ? (int) round((float) $avgDays) : '—' ?></b><span>dana prosek</span></div>
    <div class="tile"><span>Zastoji</span><b><?= count($stops) ?></b><span><?= $lostDays ?> izgubljenih dana</span></div>
</div>

<h2>Naplaćeno: <?= e(fmt_money((string) $paid['total'])) ?> <span style="font-weight:400;color:#666">(<?= (int) $paid['n'] ?> naplata<?= (int) $paid['diffs'] ? ', ' . (int) $paid['diffs'] . ' sa razlikom' : '' ?>)</span></h2>
<h2>Zadaci: <?= (int) $tasks['done'] ?> završeno<?= (int) $tasks['done_late'] ? ', ' . (int) $tasks['done_late'] . ' posle roka' : '' ?><?= (int) $tasks['open_late'] ? ', ' . (int) $tasks['open_late'] . ' kasni' : '' ?></h2>

<?php if ($byReason): ?>
    <h2>Zastoji po razlogu</h2>
    <table><?php foreach ($byReason as $r => $n): ?><tr><td><?= e(REASON_LABELS[$r]) ?></td><td class="mono"><?= $n ?></td></tr><?php endforeach; ?></table>
<?php endif; ?>

<?php if ($itemSpeed): ?>
    <h2>Trajanje stavki (prosek dana)</h2>
    <table><?php foreach ($itemSpeed as $s): ?><tr><td><?= e(ITEM_LABELS[$s['kind']]) ?></td><td class="mono"><?= e(number_format((float) $s['days'], 1, ',', '')) ?></td><td style="color:#666"><?= (int) $s['n'] ?> stavki</td></tr><?php endforeach; ?></table>
<?php endif; ?>

<h2>Završeni projekti (<?= count($completed) ?>)</h2>
<?php foreach ($completed as $p): $ph = $photos[$p['id']] ?? []; ?>
    <div class="proj">
        <div>
            <strong style="font-size:15px"><?= e($p['customer_name']) ?></strong> <span class="mono" style="color:#666"><?= e($p['code']) ?></span><br>
            <?= e(implode(' · ', array_filter([$p['city'], TYPE_LABELS[$p['type']] . ($p['has_steel'] ? ' + čelik' : '')]))) ?><br>
            <span style="color:#555"><?= e(short_desc($p['description'], 90)) ?></span><br>
            <span class="mono" style="color:#666">Završeno <?= e(fmt_date(substr($p['completed_at'], 0, 10))) ?><?= $p['payment_received'] !== null ? ' · naplaćeno ' . e(fmt_money($p['payment_received'])) : '' ?></span>
        </div>
        <div><div class="lbl">PRE</div><?php if (isset($ph['lokacija_pre'])): ?><img src="<?= e(file_url((int) $ph['lokacija_pre']['id'], true)) ?>" alt=""><?php else: ?><div class="ph">nema slike</div><?php endif; ?></div>
        <div><div class="lbl">POSLE</div><?php if (isset($ph['posle'])): ?><img src="<?= e(file_url((int) $ph['posle']['id'], true)) ?>" alt=""><?php else: ?><div class="ph">nema slike</div><?php endif; ?></div>
    </div>
<?php endforeach; ?>

<?php if ($carry): ?>
    <h2><?= $isCurrent ? 'Prelazi' : 'Prešlo' ?> u <?= e($nextLabel) ?> (<?= count($carry) ?><?= $carryLate ? ', ' . $carryLate . ' kasni' : '' ?>)</h2>
    <table>
        <tr><th>Kupac</th><th>Mesto</th><th>Tip</th><th>Rok</th></tr>
        <?php foreach ($carry as $p): ?><tr><td><?= e($p['customer_name']) ?></td><td><?= e($p['city']) ?></td><td><?= e(TYPE_LABELS[$p['type']]) ?></td><td class="mono"><?= e(fmt_date($p['deadline'])) ?></td></tr><?php endforeach; ?>
    </table>
<?php endif; ?>
</body>
</html>

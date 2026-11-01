<?php /** @var array $completed @var array $photos (vidi report_data) */ ?>
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
    <div class="tile"><span>Završeno projekata</span><b><?= count($completed) ?></b></div>
    <div class="tile"><span>Montaža urađeno</span><b><?= $montaze ?></b></div>
    <div class="tile"><span>Prosečno trajanje</span><b><?= $avgDays !== null ? (int) round((float) $avgDays) : '—' ?></b><span>dana</span></div>
    <div class="tile"><span>Zastoji</span><b><?= $stops ?></b><span><?= $lostDays ?> izgubljenih dana</span></div>
</div>

<?php if ($byReason): ?>
    <h2>Zastoji po razlogu</h2>
    <table><?php foreach ($byReason as $r => $n): ?><tr><td><?= e(REASON_LABELS[$r] ?? $r) ?></td><td class="mono"><?= $n ?></td></tr><?php endforeach; ?></table>
<?php endif; ?>

<h2>Završeni projekti (<?= count($completed) ?>)</h2>
<?php foreach ($completed as $p): $ph = $photos[$p['id']] ?? []; ?>
    <div class="proj">
        <div>
            <strong style="font-size:15px"><?= e($p['customer_name']) ?></strong> <span class="mono" style="color:#666"><?= e($p['code']) ?></span><br>
            <?= e(implode(' · ', array_filter([$p['address'], $p['city'], TYPE_LABELS[$p['type']] ?? '']))) ?><br>
            <span class="mono" style="color:#666">Završeno <?= e(fmt_date(substr($p['completed_at'], 0, 10))) ?></span>
        </div>
        <div><div class="lbl">PRE</div><?php if (isset($ph['pre'])): ?><img src="<?= e(file_url((int) $ph['pre']['id'], true)) ?>" alt=""><?php else: ?><div class="ph">nema slike</div><?php endif; ?></div>
        <div><div class="lbl">POSLE</div><?php if (isset($ph['posle'])): ?><img src="<?= e(file_url((int) $ph['posle']['id'], true)) ?>" alt=""><?php else: ?><div class="ph">nema slike</div><?php endif; ?></div>
    </div>
<?php endforeach; ?>
</body>
</html>

<?php /** @var array $completed @var array $photos @var array $systemsBy … (vidi report_data) */ $money = fn($v) => fmt_money((string) $v); ?>
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
    <div class="tile"><span>Završeno projekata</span><b><?= count($completed) ?></b><span><?= e(implode(' · ', array_map(fn($k, $c) => $c . ' ' . mb_strtolower(WORK_TYPES[$k] ?? $k), array_keys($byWork), $byWork))) ?></span></div>
    <div class="tile"><span>Pergola / sistema</span><b><?= $pergole ?></b><span>montirano <?= $mounted ?></span></div>
    <div class="tile"><span>Izlazaka na teren</span><b><?= $visits ?></b><span><?= $revisits ?> ponovnih</span></div>
    <div class="tile"><span>Zastoji</span><b><?= $stops ?></b><span><?= $lostDays ?> izgubljenih dana</span></div>
</div>

<h2>Projekti</h2>
<table>
    <tr><td>Pušteno u realizaciju</td><td class="mono"><?= $released ?></td></tr>
    <tr><td>Prosečno trajanje (puštanje → kraj, novi)</td><td class="mono"><?= $avgDays !== null ? (int) round((float) $avgDays) . ' d' : '—' ?></td></tr>
    <tr><td><?= $isCurrent ? 'Prelazi' : 'Prešlo' ?> u <?= e($nextLabel) ?></td><td class="mono"><?= count($carry) ?><?= $carryLate ? ' (' . $carryLate . ' kasni)' : '' ?></td></tr>
</table>

<?php if ($prod): ?>
    <h2>Proizvodnja po tipu sistema</h2>
    <table>
        <tr><th>Tip</th><th>Komada</th><th>Prosek dana</th><th>Najsporija faza</th></tr>
        <?php foreach ($prod as $type => $x): $slow = $x['phases'][0] ?? null; ?>
            <tr><td><?= e($type) ?></td><td class="mono"><?= $x['qty'] ?></td><td class="mono"><?= $x['days'] ? e(fmt_days(array_sum($x['days']) / count($x['days']))) : '—' ?></td><td><?= $slow ? e($slow['name']) . ' · ' . e(fmt_days($slow['days'])) . ' d' : '—' ?></td></tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<h2>Montaža</h2>
<table>
    <?php foreach ($visitKinds as $k => $c): ?><tr><td><?= e(VISIT_KINDS[$k] ?? $k) ?></td><td class="mono"><?= $c ?></td></tr><?php endforeach; ?>
    <?php foreach ($visitResults as $k => $c): ?><tr><td>Ishod: <?= e(mb_strtolower(VISIT_RESULTS[$k] ?? $k)) ?></td><td class="mono"><?= $c ?></td></tr><?php endforeach; ?>
    <tr><td>Ponovni dolasci</td><td class="mono"><?= $revisits ?></td></tr>
</table>

<?php if ($byReason): ?>
    <h2>Zastoji po razlogu</h2>
    <table><?php foreach ($byReason as $r => $c): ?><tr><td><?= e(REASON_LABELS[$r] ?? $r) ?></td><td class="mono"><?= $c ?></td></tr><?php endforeach; ?></table>
<?php endif; ?>

<h2>Naplata</h2>
<table>
    <tr><td>Primljeno od kupaca</td><td class="mono"><?= e($money($received)) ?></td></tr>
    <?php foreach ($receivedBy as $k => $x): ?><tr><td style="padding-left:20px;color:#555"><?= e(METHOD_LABELS[$k] ?? $k) ?> (<?= (int) $x['n'] ?>)</td><td class="mono"><?= e($money($x['s'])) ?></td></tr><?php endforeach; ?>
    <tr><td>Gotovina predata firmi</td><td class="mono"><?= e($money($handed)) ?></td></tr>
    <tr><td>Gotovina kod radnika (danas)</td><td class="mono"><?= e($money($cashHeld)) ?></td></tr>
    <tr><td>Ostalo za naplatu (danas)</td><td class="mono"><?= e($money($remaining)) ?></td></tr>
</table>

<h2>Zadaci</h2>
<table>
    <tr><td>Zadato / završeno</td><td class="mono"><?= (int) $tasks['created'] ?> / <?= (int) $tasks['done'] ?></td></tr>
    <tr><td>Završeno posle roka</td><td class="mono"><?= (int) $tasks['done_late'] ?></td></tr>
    <tr><td>Ponovo otvoreno</td><td class="mono"><?= (int) $tasks['reopened'] ?></td></tr>
    <tr><td>Kasni (otvoreno)</td><td class="mono"><?= (int) $tasks['open_late'] ?></td></tr>
</table>

<h2>Završeni projekti (<?= count($completed) ?>)</h2>
<?php foreach ($completed as $p): $ph = $photos[$p['id']] ?? []; ?>
    <div class="proj">
        <div>
            <strong style="font-size:15px"><?= e($p['customer_name']) ?></strong> <span class="mono" style="color:#666"><?= e($p['code']) ?></span><br>
            <?= e(implode(' · ', array_filter([$p['city'], WORK_TYPES[$p['work_type']] ?? '']))) ?><br>
            <span style="color:#555"><?= e(systems_summary($systemsBy[$p['id']] ?? [], 4) ?: short_desc($p['description'], 90)) ?></span><br>
            <span class="mono" style="color:#666">Završeno <?= e(fmt_date(substr($p['completed_at'], 0, 10))) ?></span>
        </div>
        <div><div class="lbl">PRE</div><?php if (isset($ph['pre'])): ?><img src="<?= e(file_url((int) $ph['pre']['id'], true)) ?>" alt=""><?php else: ?><div class="ph">nema slike</div><?php endif; ?></div>
        <div><div class="lbl">POSLE</div><?php if (isset($ph['posle'])): ?><img src="<?= e(file_url((int) $ph['posle']['id'], true)) ?>" alt=""><?php else: ?><div class="ph">nema slike</div><?php endif; ?></div>
    </div>
<?php endforeach; ?>
</body>
</html>

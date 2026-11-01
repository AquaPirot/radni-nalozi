<?php
declare(strict_types=1);

// Spisak projekata (?p=projekti&f=aktivni|zavrseni|reklamacije&q=…). Aktivni su grupisani po danu montaže.

$filters = ['aktivni' => 'Aktivni', 'reklamacije' => 'Reklamacije', 'zavrseni' => 'Završeni'];
$f = isset($filters[$_GET['f'] ?? '']) ? $_GET['f'] : 'aktivni';
$search = trim((string) ($_GET['q'] ?? ''));

$conds = [
    'aktivni'     => "p.status = 'aktivan'",
    'reklamacije' => "p.status = 'aktivan' AND p.type = 'reklamacija'",
    'zavrseni'    => "p.status = 'zavrsen'",
];
$counts = [];
foreach ($conds as $k => $c) {
    $counts[$k] = (int) q_val("SELECT COUNT(*) FROM projects p WHERE $c");
}
$where = $conds[$f];
$params = [];
if ($search !== '') {
    $where = "p.status <> 'otkazan' AND (p.customer_name LIKE ? OR p.city LIKE ? OR p.code LIKE ? OR p.address LIKE ? OR p.phone LIKE ?)";
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $params = array_fill(0, 5, $like);
}
$order = $f === 'zavrseni' && $search === '' ? 'p.completed_at DESC' : 'p.install_date IS NULL, p.install_date, p.install_time, p.id';
$bundles = bundle(q_all("SELECT p.* FROM projects p WHERE $where ORDER BY $order LIMIT 300", $params));

$groups = [];
$todayS = today()->format('Y-m-d');
$sun = week_monday(today())->modify('+6 days')->format('Y-m-d');
$nextSun = week_monday(today())->modify('+13 days')->format('Y-m-d');
foreach ($bundles as $b) {
    $p = $b['p'];
    if ($p['status'] === 'zavrsen') {
        $d = new DateTimeImmutable((string) $p['completed_at']);
        [$key, $title] = [$d->format('Y-m'), mb_strtoupper(MONTHS_FULL[(int) $d->format('n')]) . ' ' . $d->format('Y')];
    } elseif (install_late($b)) {
        [$key, $title] = ['0', 'MONTAŽA KASNI'];
    } elseif (!$p['install_date']) {
        [$key, $title] = ['4', 'BEZ DATUMA MONTAŽE'];
    } elseif ($p['install_date'] <= $sun) {
        [$key, $title] = ['1', 'OVE NEDELJE'];
    } elseif ($p['install_date'] <= $nextSun) {
        [$key, $title] = ['2', 'SLEDEĆE NEDELJE'];
    } else {
        [$key, $title] = ['3', 'KASNIJE'];
    }
    $groups[$key] ??= ['title' => $title, 'items' => []];
    $groups[$key]['items'][] = $b;
}
$f === 'zavrseni' && $search === '' ? krsort($groups) : ksort($groups);

view('projekti', [
    'title'    => 'Projekti',
    'tab'      => 'projekti',
    'user'     => $user,
    'f'        => $f,
    'filters'  => $filters,
    'counts'   => $counts,
    'search'   => $search,
    'groups'   => $groups,
    'topExtra' => '<button class="icon-btn" type="button" data-toggle="#search-row" aria-label="Pretraga">' . icon('search') . '</button>',
]);

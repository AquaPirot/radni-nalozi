<?php
declare(strict_types=1);

$isOwner = $user['role'] === 'vlasnik';
$filters = $isOwner ? ['aktivni', 'reklamacije', 'zavrseno'] : ['aktivni', 'reklamacije'];
$f = in_array($_GET['f'] ?? '', $filters, true) ? $_GET['f'] : 'aktivni';
$search = trim((string) ($_GET['q'] ?? ''));

[$vis, $vp] = visibility_sql($user);

$counts = q_one(
    "SELECT SUM(p.status = 'aktivan') AS aktivni,
            SUM(p.status = 'aktivan' AND p.type = 'reklamacija') AS reklamacije,
            SUM(p.status = 'zavrsen') AS zavrseno
     FROM projects p WHERE $vis",
    $vp
);

$where = [$vis];
$params = $vp;
$where[] = match ($f) {
    'reklamacije' => "p.status = 'aktivan' AND p.type = 'reklamacija'",
    'zavrseno'    => "p.status = 'zavrsen'",
    default       => "p.status = 'aktivan'",
};
if ($search !== '') {
    $where[] = '(p.customer_name LIKE ? OR p.city LIKE ? OR p.code LIKE ?)';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
$order = $f === 'zavrseno'
    ? 'p.completed_at DESC'
    : 'p.install_week_start IS NULL, p.install_week_start, p.deadline IS NULL, p.deadline, p.id';

$projects = q_all(
    'SELECT p.* FROM projects p WHERE ' . implode(' AND ', $where) . " ORDER BY $order LIMIT 300",
    $params
);
$ids = array_column($projects, 'id');
$itemsBy = load_items_for($ids);
$stopBy = open_stoppages_for($ids);

// Zauzetost nedelja (sve montaže te nedelje osim otkazanih)
$weekLoad = [];
foreach (q_all("SELECT install_week_start w, COUNT(*) n FROM projects WHERE install_week_start IS NOT NULL AND status <> 'otkazan' GROUP BY install_week_start") as $r) {
    $weekLoad[$r['w']] = (int) $r['n'];
}
$capacity = (int) setting('weekly_capacity', '5');

// Grupisanje: aktivni po nedelji, završeni po mesecu
$groups = [];
foreach ($projects as $p) {
    if ($f === 'zavrseno') {
        $d = new DateTimeImmutable((string) $p['completed_at']);
        $key = $d->format('Y-m');
        $groups[$key] ??= ['title' => mb_strtoupper(MONTHS_FULL[(int) $d->format('n')]) . ' ' . $d->format('Y'), 'right' => '', 'over' => false, 'items' => []];
    } elseif ($p['install_week_start']) {
        $key = $p['install_week_start'];
        $mon = new DateTimeImmutable($key);
        $n = $weekLoad[$key] ?? 0;
        $groups[$key] ??= [
            'title' => week_label($mon, true),
            'sub'   => mb_strtoupper(week_relative($mon)),
            'right' => $n . ' / ' . $capacity,
            'over'  => $n > $capacity,
            'items' => [],
        ];
    } else {
        $key = 'none';
        $groups[$key] ??= ['title' => 'NIJE RASPOREĐENO', 'right' => '', 'over' => false, 'items' => []];
    }
    $groups[$key]['items'][] = $p;
}
if (isset($groups['none'])) { // "nije raspoređeno" na kraj
    $none = $groups['none'];
    unset($groups['none']);
    $groups['none'] = $none;
}

view('projekti', [
    'title'    => 'Projekti',
    'tab'      => 'projekti',
    'user'     => $user,
    'f'        => $f,
    'filters'  => $filters,
    'counts'   => $counts,
    'search'   => $search,
    'groups'   => $groups,
    'itemsBy'  => $itemsBy,
    'stopBy'   => $stopBy,
    'topExtra' => '<button class="icon-btn" type="button" data-toggle="#search-row" aria-label="Pretraga">' . icon('search') . '</button>',
]);

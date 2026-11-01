<?php
declare(strict_types=1);

// Mesečni izveštaj (vlasnik). ?m=YYYY-MM, &print=1 za štampu / PDF.

$m = (string) ($_GET['m'] ?? date('Y-m'));
$start = DateTimeImmutable::createFromFormat('!Y-m-d', $m . '-01') ?: new DateTimeImmutable('first day of this month');
$start = $start->setTime(0, 0);
$end = $start->modify('+1 month');

$data = report_data($start, $end) + [
    'monthLabel' => MONTHS_FULL[(int) $start->format('n')] . ' ' . $start->format('Y'),
    'nextLabel'  => mb_strtolower(MONTHS_FULL[(int) $end->format('n')]),
    'isCurrent'  => $start->format('Y-m') === date('Y-m'),
];

if (!empty($_GET['print'])) {
    $data['systemsBy'] = $data['completed'] ? systems_for(array_column($data['completed'], 'id')) : [];
    view('izvestaj_print', $data + ['title' => 'Izveštaj — ' . $data['monthLabel'], 'photos' => report_photos(array_column($data['completed'], 'id'))], false);
    return;
}

view('izvestaj', $data + [
    'title' => 'Izveštaj',
    'tab'   => 'izvestaj',
    'user'  => $user,
    'prevM' => $start->modify('-1 month')->format('Y-m'),
    'nextM' => $end->format('Y-m'),
    'm'     => $start->format('Y-m'),
]);

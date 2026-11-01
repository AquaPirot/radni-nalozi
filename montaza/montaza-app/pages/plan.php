<?php
declare(strict_types=1);

// Plan montaža po danima i ekipama (?p=plan&w=YYYY-MM-DD&team=…&f=neraspored)

$today = today();
$dow = (int) $today->format('N');
$defaultMon = week_monday($today);
if ($dow >= 6) {
    $defaultMon = $defaultMon->modify('+1 week'); // vikend: naredna radna nedelja
}
$mon = isset($_GET['w']) && parse_date($_GET['w']) ? week_monday($_GET['w']) : $defaultMon;
$sun = $mon->modify('+6 days');
$teamF = (int) ($_GET['team'] ?? 0);
$teams = teams_active();

// Termini koji se preklapaju sa nedeljom
$params = [$sun->format('Y-m-d'), $mon->format('Y-m-d')];
$tw = '';
if ($teamF) {
    $tw = ' AND v.team_id = ?';
    $params[] = $teamF;
}
$visits = q_all(
    "SELECT v.*, t.name AS team_name, p.customer_name, p.city, p.id AS pid FROM visits v
     JOIN projects p ON p.id = v.project_id LEFT JOIN teams t ON t.id = v.team_id
     WHERE v.status <> 'otkazan' AND v.start_date IS NOT NULL AND p.archived_at IS NULL
       AND v.start_date <= ? AND DATE_ADD(v.start_date, INTERVAL (v.days - 1) DAY) >= ? $tw
     ORDER BY v.start_date, v.start_time IS NULL, v.start_time",
    $params
);
$pids = array_values(array_unique(array_column($visits, 'pid')));
$bundles = $pids ? bundle(q_all('SELECT * FROM projects WHERE id IN (' . in_list($pids) . ')', $pids)) : [];

$days = [];
for ($i = 0; $i < 7; $i++) {
    $d = $mon->modify("+$i days");
    $days[$d->format('Y-m-d')] = ['date' => $d, 'items' => []];
}
foreach ($visits as $v) {
    $start = new DateTimeImmutable($v['start_date']);
    for ($i = 0; $i < (int) $v['days']; $i++) {
        $key = $start->modify("+$i days")->format('Y-m-d');
        if (isset($days[$key])) {
            $days[$key]['items'][] = $v + ['day_n' => $i + 1];
        }
    }
}
// Preklapanje: ista ekipa više puta istog dana
foreach ($days as $k => $d) {
    $byTeam = [];
    foreach ($d['items'] as $v) {
        if ($v['team_id'] && $v['status'] !== 'zavrseno') {
            $byTeam[$v['team_id']] = ($byTeam[$v['team_id']] ?? 0) + 1;
        }
    }
    $days[$k]['overlap'] = array_keys(array_filter($byTeam, fn($n) => $n > 1));
}

// Neraspoređeno: termini bez datuma + aktivni projekti bez otvorenog termina
$unVisits = q_all(
    "SELECT v.*, p.customer_name, p.city FROM visits v JOIN projects p ON p.id = v.project_id
     WHERE v.status = 'planiran' AND v.start_date IS NULL AND p.status = 'aktivan' AND p.archived_at IS NULL ORDER BY v.created_at"
);
$unProjects = q_all(
    "SELECT p.* FROM projects p WHERE p.status = 'aktivan' AND p.archived_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM visits v WHERE v.project_id = p.id AND v.status IN ('planiran','u_toku'))
       AND NOT EXISTS (SELECT 1 FROM visits v WHERE v.project_id = p.id AND v.status = 'zavrseno' AND v.result = 'celo' AND v.kind IN ('montaza','servis'))
     ORDER BY p.deadline IS NULL, p.deadline"
);
$unBundles = bundle($unProjects);

view('plan', [
    'title'      => 'Plan montaža',
    'eyebrow'    => 'AG GROUP · PLAN',
    'tab'        => 'plan',
    'user'       => $user,
    'mon'        => $mon,
    'days'       => $days,
    'bundles'    => $bundles,
    'teams'      => $teams,
    'teamF'      => $teamF,
    'unVisits'   => $unVisits,
    'unBundles'  => $unBundles,
    'showUn'     => ($_GET['f'] ?? '') === 'neraspored',
    'capacity'   => (int) setting('weekly_capacity', '5'),
    'weekCount'  => count(array_unique(array_column(array_filter($visits, fn($v) => $v['kind'] !== 'dijagnoza'), 'id'))),
]);

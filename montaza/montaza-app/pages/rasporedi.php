<?php
declare(strict_types=1);

// Raspoređivanje projekta u nedelju (vlasnik) — "bottom sheet" stranica.

$project = load_project((int) ($_GET['id'] ?? 0));
if (!$project) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'plan']);
    return;
}
if (is_post()) {
    csrf_check();
    $raw = (string) ($_POST['week'] ?? '');
    $week = null;
    if ($raw !== '') {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        $week = $d ? week_monday($d)->format('Y-m-d') : null;
    }
    set_project_week($project, $week, $user);
    flash('ok', $week ? $project['customer_name'] . ' → ' . week_option_label(new DateTimeImmutable($week)) : $project['customer_name'] . ' — nije raspoređeno');
    redirect(url('plan'));
}

$capacity = (int) setting('weekly_capacity', '5');
$load = [];
foreach (q_all("SELECT install_week_start w, COUNT(*) n FROM projects WHERE install_week_start IS NOT NULL AND status <> 'otkazan' GROUP BY install_week_start") as $r) {
    $load[$r['w']] = (int) $r['n'];
}

view('rasporedi', [
    'title'    => $project['customer_name'],
    'eyebrow'  => 'IZABERI NEDELJU MONTAŽE',
    'tab'      => 'plan',
    'focus'    => true,
    'user'     => $user,
    'project'  => $project,
    'options'  => week_options($project['install_week_start'], 12),
    'load'     => $load,
    'capacity' => $capacity,
    'topExtra' => '<a class="icon-btn" href="' . e(url('plan')) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

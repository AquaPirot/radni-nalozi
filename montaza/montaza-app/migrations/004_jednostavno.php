<?php
declare(strict_types=1);

// v3 „jednostavno": projekat = kupac + adresa + telefon + datum montaže + PDF nalog + slike + stavke (kao v1).
// - projects.install_date / install_time (dan i vreme montaže)
// - svaki projekat ponovo ima v1 stavke (nalog, čelik, aluminijum, cerada, pakovanje, montaža / dijagnoza, materijal, servis)
//   sa stanjem prenetim iz v2 (faze, nalozi, termini) — stanje se samo podiže, nikad ne vraća unazad
// - nacrti postaju aktivni projekti
// Tabele v2 (sistemi, faze, termini, uplate, zadaci) ostaju u bazi netaknute.

return function (PDO $pdo): void {
    $q = function (string $sql, array $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s; };
    $has = fn(string $t, string $c) => (bool) $q('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$t, $c])->fetchColumn();
    $table = fn(string $t) => (bool) $q('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$t])->fetchColumn();

    if (!$has('projects', 'install_date')) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN install_date DATE NULL AFTER install_week_start, ADD COLUMN install_time TIME NULL AFTER install_date, ADD KEY ix_projects_install (status, install_date)");
    }

    // Nacrti → aktivni; tip iz v2 (work_type / intake) ako nedostaje
    $pdo->exec("UPDATE projects SET status = 'aktivan' WHERE status = 'nacrt'");
    if ($has('projects', 'work_type')) {
        $pdo->exec("UPDATE projects SET type = CASE WHEN work_type IN ('reklamacija','servis') THEN 'reklamacija' WHEN intake = 'preuzet' THEN 'zapocet' ELSE 'novi' END WHERE type IS NULL");
    }
    $pdo->exec("UPDATE projects SET type = 'novi' WHERE type IS NULL");

    // Datum montaže: otvoren termin iz v2 → inače poslednji završeni → inače ponedeljak nedelje iz v1
    if ($table('visits')) {
        $pdo->exec("UPDATE projects p SET
              install_date = COALESCE(
                (SELECT MIN(v.start_date) FROM visits v WHERE v.project_id = p.id AND v.status IN ('planiran','u_toku') AND v.start_date IS NOT NULL),
                (SELECT MAX(v.start_date) FROM visits v WHERE v.project_id = p.id AND v.status = 'zavrseno' AND v.start_date IS NOT NULL)),
              install_time = (SELECT v.start_time FROM visits v WHERE v.project_id = p.id AND v.status IN ('planiran','u_toku') AND v.start_date IS NOT NULL ORDER BY v.start_date LIMIT 1)
            WHERE p.install_date IS NULL");
    }
    $pdo->exec("UPDATE projects SET install_date = install_week_start WHERE install_date IS NULL AND install_week_start IS NOT NULL");

    // Stavke za projekte koji ih nemaju (napravljeni u v2)
    $std = [['nalog', 'vlasnik', 10], ['celik', 'hala', 20], ['aluminijum', 'hala', 30], ['cerada', 'hala', 40], ['pakovanje', 'hala', 50], ['montaza', 'teren', 60]];
    $rek = [['dijagnoza', 'teren', 10], ['materijal', 'hala', 20], ['servis', 'teren', 30]];
    $steelSql = $table('project_systems') ? '(p.has_steel = 1 OR EXISTS (SELECT 1 FROM project_systems s WHERE s.project_id = p.id AND s.with_steel = 1))' : 'p.has_steel = 1';
    foreach ($q("SELECT p.id, p.type, $steelSql AS steel FROM projects p WHERE NOT EXISTS (SELECT 1 FROM project_items i WHERE i.project_id = p.id)")->fetchAll() as $p) {
        $tpl = $p['type'] === 'reklamacija' ? $rek : array_values(array_filter($std, fn($t) => $t[0] !== 'celik' || $p['steel']));
        foreach ($tpl as [$kind, $resp, $sort]) {
            $q('INSERT INTO project_items (project_id, kind, sort, responsible) VALUES (?, ?, ?, ?)', [$p['id'], $kind, $sort, $resp]);
        }
    }

    // Stanje stavki iz v2 (samo naviše)
    $rank = ['ceka' => 0, 'zastoj' => 0, 'u_toku' => 1, 'zavrseno' => 2];
    $hasPhases = $table('production_phases');
    $hasVisits = $table('visits');
    $hasOrders = $table('project_orders');
    foreach ($q('SELECT p.id, p.status, p.completed_at FROM projects p')->fetchAll() as $p) {
        $pid = (int) $p['id'];
        $phases = $hasPhases ? $q('SELECT name, status, completed_at FROM production_phases WHERE project_id = ?', [$pid])->fetchAll() : [];
        $visits = $hasVisits ? $q('SELECT kind, status, result, completed_at FROM visits WHERE project_id = ?', [$pid])->fetchAll() : [];
        $allPhasesDone = $phases && !array_filter($phases, fn($f) => $f['status'] !== 'zavrseno');
        $phaseState = function (array $patterns) use ($phases, $allPhasesDone): ?array {
            if ($allPhasesDone) {
                return ['zavrseno', max(array_column($phases, 'completed_at'))];
            }
            $m = array_filter($phases, function ($f) use ($patterns) {
                foreach ($patterns as $pat) {
                    if (mb_stripos($f['name'], $pat) !== false) {
                        return true;
                    }
                }
                return false;
            });
            if (!$m) {
                return null;
            }
            if (!array_filter($m, fn($f) => $f['status'] !== 'zavrseno')) {
                return ['zavrseno', max(array_column($m, 'completed_at'))];
            }
            return array_filter($m, fn($f) => $f['status'] !== 'ceka') ? ['u_toku', null] : null;
        };
        $visitState = function (array $kinds) use ($visits, $p): ?array {
            if ($p['status'] === 'zavrsen') {
                return ['zavrseno', $p['completed_at']];
            }
            foreach ($visits as $v) {
                if (in_array($v['kind'], $kinds, true) && $v['status'] === 'zavrseno' && $v['result'] === 'celo') {
                    return ['zavrseno', $v['completed_at']];
                }
            }
            foreach ($visits as $v) {
                if (in_array($v['kind'], $kinds, true) && in_array($v['status'], ['u_toku', 'zavrseno'], true)) {
                    return ['u_toku', null];
                }
            }
            return null;
        };
        $hasOrder = (bool) $q("SELECT 1 FROM files WHERE project_id = ? AND kind = 'nalog_pdf'", [$pid])->fetchColumn()
            || ($hasOrders && (bool) $q('SELECT 1 FROM project_orders WHERE project_id = ?', [$pid])->fetchColumn());

        foreach ($q('SELECT id, kind, status FROM project_items WHERE project_id = ?', [$pid])->fetchAll() as $it) {
            $new = match ($it['kind']) {
                'nalog'      => $hasOrder ? ['zavrseno', null] : null,
                'celik'      => $phaseState(['konstrukc', 'čelik', 'celik']),
                'aluminijum' => $phaseState(['alumin', 'profil']),
                'cerada'     => $phaseState(['cerad', 'platno', 'lamel', 'panel', 'staklo']),
                'pakovanje'  => $phaseState(['pakovanj']),
                'materijal'  => $phaseState(['']),
                'montaza'    => $visitState(['montaza', 'dorada']),
                'servis'     => $visitState(['servis', 'montaza', 'dorada']),
                'dijagnoza'  => $visitState(['dijagnoza', 'servis']),
                default      => null,
            };
            if (!$new && $p['status'] === 'zavrsen') {
                $new = ['zavrseno', $p['completed_at']];
            }
            if ($new && $rank[$new[0]] > $rank[$it['status']]) {
                $q("UPDATE project_items SET status = ?, started_at = COALESCE(started_at, ?, NOW()),
                       completed_at = IF(? = 'zavrseno', COALESCE(?, NOW()), completed_at), imported = 1 WHERE id = ?",
                    [$new[0], $new[1], $new[0], $new[1], $it['id']]);
            }
        }
    }
};

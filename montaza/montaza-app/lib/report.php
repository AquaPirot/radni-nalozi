<?php
declare(strict_types=1);

// Mesečni izveštaj v2: projekti · proizvodnja po tipu sistema · montaža · zastoji · naplata · zadaci.

function report_data(DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $S = $start->format('Y-m-d H:i:s');
    $E = $end->format('Y-m-d H:i:s');
    $r = [];

    // ── Projekti ──
    $completed = q_all("SELECT * FROM projects WHERE status = 'zavrsen' AND completed_at >= ? AND completed_at < ? ORDER BY completed_at", [$S, $E]);
    $r['completed'] = $completed;
    $r['byWork'] = array_count_values(array_map(fn($p) => $p['work_type'] ?: 'novi', $completed));
    $ids = array_map('intval', array_column($completed, 'id'));
    $r['pergole'] = $ids
        ? (int) q_val('SELECT COALESCE(SUM(qty), 0) FROM project_systems WHERE project_id IN (' . implode(',', $ids) . ')')
        : 0;
    $r['released'] = (int) q_val("SELECT COUNT(*) FROM projects WHERE released_at >= ? AND released_at < ?", [$S, $E]);
    $r['avgDays'] = q_val(
        "SELECT AVG(TIMESTAMPDIFF(HOUR, COALESCE(released_at, created_at), completed_at)) / 24 FROM projects
         WHERE status = 'zavrsen' AND intake = 'novi' AND migrated_v1 = 0 AND completed_at >= ? AND completed_at < ?",
        [$S, $E]
    );
    $carry = q_all(
        "SELECT * FROM projects WHERE status IN ('aktivan','zavrsen') AND COALESCE(released_at, created_at) < ?
           AND (completed_at IS NULL OR completed_at >= ?) AND archived_at IS NULL
         ORDER BY deadline IS NULL, deadline",
        [$E, $E]
    );
    $endDay = min($end->format('Y-m-d'), date('Y-m-d'));
    $r['carry'] = $carry;
    $r['carryLate'] = count(array_filter($carry, fn($p) => $p['deadline'] && $p['deadline'] < $endDay));

    // ── Proizvodnja po tipu sistema (sistem je gotov kad mu je završena poslednja faza) ──
    $systems = q_all(
        "SELECT s.id, s.qty, COALESCE(t.name, 'Bez tipa') AS type_name, MIN(ph.started_at) AS st, MAX(ph.completed_at) AS en
         FROM project_systems s
         LEFT JOIN system_types t ON t.id = s.system_type_id
         JOIN production_phases ph ON ph.system_id = s.id
         GROUP BY s.id, s.qty, t.name
         HAVING SUM(ph.status <> 'zavrseno') = 0 AND en >= ? AND en < ?",
        [$S, $E]
    );
    $prod = [];
    foreach ($systems as $s) {
        $k = $s['type_name'];
        $prod[$k] ??= ['systems' => 0, 'qty' => 0, 'days' => [], 'phases' => []];
        $prod[$k]['systems']++;
        $prod[$k]['qty'] += (int) $s['qty'];
        if ($s['st']) {
            $prod[$k]['days'][] = (strtotime($s['en']) - strtotime($s['st'])) / 86400;
        }
    }
    foreach (q_all(
        "SELECT COALESCE(t.name, 'Bez tipa') AS type_name, ph.name, AVG(TIMESTAMPDIFF(HOUR, ph.started_at, ph.completed_at)) / 24 AS days, COUNT(*) AS n
         FROM production_phases ph JOIN project_systems s ON s.id = ph.system_id LEFT JOIN system_types t ON t.id = s.system_type_id
         WHERE ph.status = 'zavrseno' AND ph.imported = 0 AND ph.started_at IS NOT NULL AND ph.completed_at >= ? AND ph.completed_at < ?
         GROUP BY t.name, ph.name ORDER BY days DESC",
        [$S, $E]
    ) as $ph) {
        $prod[$ph['type_name']] ??= ['systems' => 0, 'qty' => 0, 'days' => [], 'phases' => []];
        $prod[$ph['type_name']]['phases'][] = $ph;
    }
    uasort($prod, fn($a, $b) => $b['qty'] <=> $a['qty']);
    $r['prod'] = $prod;
    $r['phasesDone'] = (int) q_val("SELECT COUNT(*) FROM production_phases WHERE status = 'zavrseno' AND imported = 0 AND completed_at >= ? AND completed_at < ?", [$S, $E]);

    // ── Montaža (termini završeni u mesecu) ──
    $visits = q_all("SELECT * FROM visits WHERE status = 'zavrseno' AND completed_at >= ? AND completed_at < ?", [$S, $E]);
    $r['visits'] = count($visits);
    $r['visitKinds'] = array_count_values(array_column($visits, 'kind'));
    $r['visitResults'] = array_count_values(array_filter(array_column($visits, 'result')));
    $r['revisits'] = count(array_filter($visits, fn($v) => $v['parent_visit_id'] || $v['kind'] === 'dorada'));
    $fullIds = array_map('intval', array_unique(array_column(array_filter($visits, fn($v) => $v['kind'] === 'montaza' && $v['result'] === 'celo'), 'project_id')));
    $r['mounted'] = $fullIds ? (int) q_val('SELECT COALESCE(SUM(qty), 0) FROM project_systems WHERE project_id IN (' . implode(',', $fullIds) . ')') : 0;
    $r['openFollowups'] = (int) q_val("SELECT COUNT(*) FROM visits WHERE parent_visit_id IS NOT NULL AND status IN ('planiran','u_toku')");

    // ── Zastoji ──
    $stops = q_all(
        "SELECT reason, resolved_at, TIMESTAMPDIFF(HOUR, created_at, LEAST(COALESCE(resolved_at, NOW()), ?)) / 24 AS days
         FROM stoppages WHERE created_at >= ? AND created_at < ?",
        [$E, $S, $E]
    );
    $r['stops'] = count($stops);
    $r['stopsOpen'] = count(array_filter($stops, fn($s) => !$s['resolved_at']));
    $r['lostDays'] = (int) round(array_sum(array_column($stops, 'days')));
    $byReason = [];
    foreach ($stops as $s) {
        $byReason[$s['reason']] = ($byReason[$s['reason']] ?? 0) + 1;
    }
    arsort($byReason);
    $r['byReason'] = $byReason;

    // ── Naplata ──
    $r['received'] = (float) q_val('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE received_at >= ? AND received_at < ?', [$S, $E]);
    $r['receivedBy'] = [];
    foreach (q_all('SELECT method, SUM(amount) AS s, COUNT(*) AS n FROM payments WHERE received_at >= ? AND received_at < ? GROUP BY method', [$S, $E]) as $x) {
        $r['receivedBy'][$x['method']] = $x;
    }
    $r['handed'] = (float) q_val("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE method = 'gotovina' AND handed_at >= ? AND handed_at < ?", [$S, $E]);
    $r['cashHeld'] = (float) q_val("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE method = 'gotovina' AND handed_at IS NULL");
    $r['remaining'] = due_payments()[1];

    // ── Zadaci ──
    $r['tasks'] = q_one(
        "SELECT SUM(created_at >= ? AND created_at < ?) AS created,
                SUM(status = 'zavrseno' AND completed_at >= ? AND completed_at < ?) AS done,
                SUM(status = 'zavrseno' AND completed_at >= ? AND completed_at < ? AND due_date IS NOT NULL AND DATE(completed_at) > due_date) AS done_late,
                SUM(status <> 'zavrseno' AND archived_at IS NULL AND due_date IS NOT NULL AND due_date < LEAST(CURDATE(), ?)) AS open_late,
                SUM(status <> 'zavrseno' AND archived_at IS NULL AND accepted_at IS NULL) AS not_accepted
         FROM tasks",
        [$S, $E, $S, $E, $S, $E, $end->format('Y-m-d')]
    );
    $r['tasks']['reopened'] = (int) q_val("SELECT COUNT(*) FROM task_comments WHERE kind = 'ponovo_otvoren' AND created_at >= ? AND created_at < ?", [$S, $E]);
    return $r;
}

/** Fotografije PRE/POSLE za projekte (za štampu). */
function report_photos(array $projectIds): array
{
    $out = [];
    if (!$projectIds) {
        return $out;
    }
    $in = implode(',', array_map('intval', $projectIds));
    foreach (q_all("SELECT id, project_id, kind, purpose FROM files WHERE pending = 0 AND project_id IN ($in)
                    AND (kind IN ('lokacija_pre','posle') OR purpose = 'celina') ORDER BY id") as $f) {
        $k = $f['kind'] === 'lokacija_pre' ? 'pre' : 'posle';
        $out[$f['project_id']][$k] ??= $f;
    }
    return $out;
}

function fmt_days(mixed $d): string
{
    return $d === null ? '—' : number_format((float) $d, 1, ',', '');
}

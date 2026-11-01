<?php
declare(strict_types=1);

// Mesečni izveštaj: završeno, montaže, zastoji, trajanje stavki, šta prelazi u sledeći mesec.

function report_data(DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $S = $start->format('Y-m-d H:i:s');
    $E = $end->format('Y-m-d H:i:s');
    $r = [];

    $r['completed'] = q_all("SELECT * FROM projects WHERE status = 'zavrsen' AND completed_at >= ? AND completed_at < ? ORDER BY completed_at", [$S, $E]);
    $r['byType'] = array_count_values(array_map(fn($p) => $p['type'] ?: 'novi', $r['completed']));
    $r['opened'] = (int) q_val('SELECT COUNT(*) FROM projects WHERE created_at >= ? AND created_at < ?', [$S, $E]);
    $r['montaze'] = (int) q_val("SELECT COUNT(*) FROM project_items WHERE kind IN ('montaza','servis') AND status = 'zavrseno' AND completed_at >= ? AND completed_at < ?", [$S, $E]);
    $r['avgDays'] = q_val(
        "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) / 24 FROM projects
         WHERE status = 'zavrsen' AND type = 'novi' AND completed_at >= ? AND completed_at < ?", [$S, $E]);

    $stops = q_all(
        "SELECT reason, TIMESTAMPDIFF(HOUR, created_at, LEAST(COALESCE(resolved_at, NOW()), ?)) / 24 AS days
         FROM stoppages WHERE project_id IS NOT NULL AND created_at >= ? AND created_at < ?", [$E, $S, $E]);
    $r['stops'] = count($stops);
    $r['lostDays'] = (int) round(array_sum(array_column($stops, 'days')));
    $byReason = [];
    foreach ($stops as $s) {
        $byReason[$s['reason']] = ($byReason[$s['reason']] ?? 0) + 1;
    }
    arsort($byReason);
    $r['byReason'] = $byReason;

    $r['itemSpeed'] = q_all(
        "SELECT kind, AVG(TIMESTAMPDIFF(HOUR, started_at, completed_at)) / 24 AS days, COUNT(*) AS n
         FROM project_items WHERE status = 'zavrseno' AND imported = 0 AND started_at IS NOT NULL AND kind <> 'nalog'
           AND completed_at >= ? AND completed_at < ? GROUP BY kind ORDER BY days DESC", [$S, $E]);

    $r['carry'] = q_all(
        "SELECT * FROM projects WHERE status <> 'otkazan' AND created_at < ? AND (completed_at IS NULL OR completed_at >= ?)
         ORDER BY install_date IS NULL, install_date", [$E, $E]);
    return $r;
}

/** Slike PRE (lokacija) i POSLE (montaža) za štampu. */
function report_photos(array $projectIds): array
{
    $out = [];
    if (!$projectIds) {
        return $out;
    }
    foreach (q_all("SELECT id, project_id, kind FROM files WHERE pending = 0 AND kind IN ('lokacija_pre','posle') AND project_id IN (" . in_list($projectIds) . ') ORDER BY id', array_values($projectIds)) as $f) {
        $out[$f['project_id']][$f['kind'] === 'lokacija_pre' ? 'pre' : 'posle'] ??= $f;
    }
    return $out;
}

function fmt_days(mixed $d): string
{
    return $d === null ? '—' : number_format((float) $d, 1, ',', '');
}

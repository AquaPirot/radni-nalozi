<?php
declare(strict_types=1);

// Komandna tabla: šta traži odluku, zastoji, kašnjenja, naplata, teren danas.
// Svaka stavka: ['title', 'why' (razlog · odgovoran · uticaj), 'url', 'kind' (odluka|zastoj|kasni|naplata|teren)]

/** Svi aktivni projekti sa podacima (keš po zahtevu). */
function active_bundles(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = bundle(q_all("SELECT * FROM projects WHERE status IN ('aktivan','zavrsen','nacrt') AND archived_at IS NULL
                                AND (status <> 'zavrsen' OR completed_at > NOW() - INTERVAL 120 DAY)"));
    }
    return $cache;
}

/** Stvari koje čekaju odluku vlasnika. */
function owner_decisions(array $user): array
{
    $out = [];
    $soon = today()->modify('+' . (int) setting('plan_days_ahead', '7') . ' days')->format('Y-m-d');
    foreach (q_all("SELECT t.*, c.name AS created_name FROM tasks t LEFT JOIN users c ON c.id = t.created_by
                    WHERE t.assigned_to = ? AND t.status <> 'zavrseno' AND t.archived_at IS NULL AND t.created_by <> t.assigned_to
                    ORDER BY t.priority = 'hitno' DESC, t.due_date IS NULL, t.due_date", [$user['id']]) as $t) {
        $out[] = ['title' => $t['title'], 'why' => 'traži ' . ($t['created_name'] ?? '—') . ($t['due_date'] ? ' · rok ' . fmt_dm($t['due_date']) : ''), 'url' => url('zadatak', ['id' => $t['id']]), 'kind' => 'odluka', 'hot' => $t['priority'] === 'hitno'];
    }
    foreach (active_bundles() as $b) {
        $p = $b['p'];
        $ref = $p['customer_name'] . ($p['city'] ? ' · ' . $p['city'] : '');
        $link = url('projekat', ['id' => $p['id']]);
        if ($p['status'] !== 'aktivan') {
            continue;
        }
        foreach ($b['stops'] as $s) {
            if (!$s['owner_id'] || in_array((int) $s['owner_id'], owner_ids(), true)) {
                $out[] = ['title' => $ref, 'why' => stoppage_text($s, next_visit($b['visits'])), 'url' => $link, 'kind' => 'odluka', 'hot' => true];
            }
        }
        if ($b['phases'] && !current_order($b['orders'])) {
            $out[] = ['title' => $ref, 'why' => 'Izdati radni nalog — hala ne može da počne', 'url' => url('projekat', ['id' => $p['id'], 't' => 'proizvodnja']), 'kind' => 'odluka', 'hot' => false];
        }
        $open = array_filter($b['visits'], fn($v) => in_array($v['status'], ['planiran', 'u_toku'], true));
        foreach ($open as $v) {
            if (!$v['start_date']) {
                $out[] = ['title' => $ref, 'why' => 'Rasporediti: ' . mb_strtolower(VISIT_KINDS[$v['kind']]) . ($v['note'] ? ' (' . trim($v['note']) . ')' : ''), 'url' => url('termin', ['project' => $p['id'], 'id' => $v['id']]), 'kind' => 'odluka', 'hot' => false];
                continue;
            }
            if ($v['status'] !== 'planiran' || $v['start_date'] > $soon) {
                continue;
            }
            if ($v['kind'] === 'montaza' && $b['phases'] && !phases_done($b['phases']) && !$v['readiness_override']) {
                $out[] = ['title' => $ref, 'why' => 'Montaža ' . visit_when($v, false) . ' ugrožena — proizvod nije završen', 'url' => $link, 'kind' => 'odluka', 'hot' => true];
            } elseif (!$v['confirmed']) {
                $out[] = ['title' => $ref, 'why' => 'Potvrditi termin ' . visit_when($v) . ' sa kupcem', 'url' => url('termin', ['project' => $p['id'], 'id' => $v['id']]), 'kind' => 'odluka', 'hot' => false];
            }
        }
        if (!$open && $b['phases'] && phases_done($b['phases']) && !array_filter($b['visits'], fn($v) => $v['status'] === 'zavrseno' && $v['result'] === 'celo')) {
            $out[] = ['title' => $ref, 'why' => 'Proizvod spreman — rasporediti montažu', 'url' => url('termin', ['project' => $p['id']]), 'kind' => 'odluka', 'hot' => false];
        }
    }
    foreach (cash_held_by_worker() as $uid => [$sum, $n]) {
        $out[] = ['title' => 'Gotovina kod: ' . user_name($uid), 'why' => fmt_money((string) $sum) . ' · ' . $n . ($n === 1 ? ' uplata' : ' uplate') . ' — potvrdi kad primiš', 'url' => url('pregled', ['f' => 'naplata']), 'kind' => 'odluka', 'hot' => false];
    }
    usort($out, fn($a, $b) => (int) $b['hot'] <=> (int) $a['hot']);
    return $out;
}

/** Otvoreni zastoji (projekti i zadaci) — vlasnik sve, ostali svoje. */
function open_stops_list(array $user): array
{
    $out = [];
    $mine = $user['role'] !== 'vlasnik';
    foreach (active_bundles() as $b) {
        if ($b['p']['status'] !== 'aktivan') {
            continue;
        }
        foreach ($b['stops'] as $s) {
            if ($mine && (int) $s['owner_id'] !== (int) $user['id'] && (int) $s['created_by'] !== (int) $user['id']) {
                continue;
            }
            $out[] = ['title' => $b['p']['customer_name'] . ($b['p']['city'] ? ' · ' . $b['p']['city'] : '') . ($s['phase_name'] ? ' · ' . $s['phase_name'] : ''), 'why' => stoppage_text($s, next_visit($b['visits'])), 'url' => url('projekat', ['id' => $b['p']['id']]), 'kind' => 'zastoj', 'hot' => true];
        }
    }
    foreach (q_all("SELECT s.*, t.title FROM stoppages s JOIN tasks t ON t.id = s.task_id WHERE s.resolved_at IS NULL") as $s) {
        if ($mine && (int) $s['owner_id'] !== (int) $user['id'] && (int) $s['created_by'] !== (int) $user['id']) {
            continue;
        }
        $out[] = ['title' => 'Zadatak: ' . $s['title'], 'why' => stoppage_text($s), 'url' => url('zadatak', ['id' => $s['task_id']]), 'kind' => 'zastoj', 'hot' => true];
    }
    return $out;
}

/** Projekti i zadaci kojima je prošao rok (sa razlogom ako postoji). */
function late_list(array $user): array
{
    $out = [];
    foreach (active_bundles() as $b) {
        $p = $b['p'];
        if (!($late = project_late($p)) || ($user['role'] !== 'vlasnik' && $p['status'] !== 'aktivan')) {
            continue;
        }
        [$na, $who] = next_action($b);
        $why = 'kasni ' . $late . ' d (rok ' . fmt_dm($p['deadline']) . ')';
        $why .= $b['stops'] ? ' — ' . stoppage_text($b['stops'][0], null, true) : ($na !== '' ? ' — ' . $na . ($who !== '' ? ' · ' . $who : '') : '');
        $out[] = ['title' => $p['customer_name'] . ($p['city'] ? ' · ' . $p['city'] : ''), 'why' => $why, 'url' => url('projekat', ['id' => $p['id']]), 'kind' => 'kasni', 'hot' => true];
    }
    [$tv, $tvp] = task_visibility_sql($user);
    foreach (q_all("SELECT t.*, a.name AS assigned_name FROM tasks t JOIN users a ON a.id = t.assigned_to
                    WHERE $tv AND t.status <> 'zavrseno' AND t.archived_at IS NULL AND t.due_date < CURDATE() ORDER BY t.due_date", $tvp) as $t) {
        $out[] = ['title' => 'Zadatak: ' . $t['title'], 'why' => 'kasni ' . days_late($t['due_date']) . ' d · ' . $t['assigned_name'] . ($t['accepted_at'] ? '' : ' · nije preuzet'), 'url' => url('zadatak', ['id' => $t['id']]), 'kind' => 'kasni', 'hot' => true];
    }
    return $out;
}

/** Dospelo za naplatu: [lista, ukupno] */
function due_payments(): array
{
    $out = [];
    $sum = 0.0;
    foreach (active_bundles() as $b) {
        $pay = payment_info($b);
        if (!$pay['due'] || $pay['remaining'] <= 0) {
            continue;
        }
        $sum += $pay['remaining'];
        $p = $b['p'];
        $out[] = ['title' => $p['customer_name'] . ($p['city'] ? ' · ' . $p['city'] : ''), 'why' => fmt_money((string) $pay['remaining']) . ' · ' . mb_strtolower($pay['label']) . ' · naplaćuje: ' . mb_strtolower(COLLECTOR_LABELS[$p['payment_collector']]), 'url' => url('projekat', ['id' => $p['id']]), 'kind' => 'naplata', 'hot' => false];
    }
    return [$out, $sum];
}

/** Termini u periodu [od, do] (uključujući višednevne koji ga dodiruju). $teamLeader: samo ekipe kojima je vođa. */
function visits_between(string $from, string $to, ?int $teamLeader = null): array
{
    $sql = "SELECT v.*, t.name AS team_name, t.leader_id, p.customer_name, p.city, p.address, p.phone, p.id AS pid FROM visits v
            JOIN projects p ON p.id = v.project_id LEFT JOIN teams t ON t.id = v.team_id
            WHERE v.status IN ('planiran','u_toku') AND v.start_date IS NOT NULL AND p.status = 'aktivan' AND p.archived_at IS NULL
              AND v.start_date <= ? AND DATE_ADD(v.start_date, INTERVAL (v.days - 1) DAY) >= ?";
    $params = [$to, $from];
    if ($teamLeader) {
        $sql .= ' AND (t.leader_id = ? OR v.team_id IS NULL)';
        $params[] = $teamLeader;
    }
    return q_all($sql . ' ORDER BY v.start_date, v.start_time IS NULL, v.start_time', $params);
}

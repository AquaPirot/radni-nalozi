<?php
declare(strict_types=1);

// Teren: termini (ekipa, datum, trajanje, okvirno/potvrđeno), spremnost, montaža, uplate.

function teams_active(): array
{
    return q_all('SELECT t.*, u.name AS leader_name FROM teams t LEFT JOIN users u ON u.id = t.leader_id WHERE t.active = 1 ORDER BY t.sort, t.name');
}

function load_visit(int $id): ?array
{
    return q_one('SELECT v.*, t.name AS team_name, t.leader_id FROM visits v LEFT JOIN teams t ON t.id = v.team_id WHERE v.id = ?', [$id]);
}

/** Termini iste ekipe koji se preklapaju sa datim periodom. */
function visit_overlaps(?int $teamId, ?string $start, int $days, int $excludeId = 0): array
{
    if (!$teamId || !$start) {
        return [];
    }
    $end = (new DateTimeImmutable($start))->modify('+' . max(0, $days - 1) . ' days')->format('Y-m-d');
    return q_all(
        "SELECT v.*, p.customer_name, p.city FROM visits v JOIN projects p ON p.id = v.project_id
         WHERE v.team_id = ? AND v.id <> ? AND v.status IN ('planiran','u_toku') AND v.start_date IS NOT NULL
           AND v.start_date <= ? AND DATE_ADD(v.start_date, INTERVAL (v.days - 1) DAY) >= ?",
        [$teamId, $excludeId, $end, $start]
    );
}

/** Može li korisnik da radi na terminu (teren: vođa ekipe ili bilo ko sa ulogom teren; vlasnik uvek). */
function can_act_visit(array $user, array $project, array $visit): bool
{
    return $project['status'] === 'aktivan' && in_array($visit['status'], ['planiran', 'u_toku'], true)
        && in_array($user['role'], ['vlasnik', 'teren'], true);
}

function visit_input(array $post): array
{
    $date = trim((string) ($post['start_date'] ?? ''));
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $time = trim((string) ($post['start_time'] ?? ''));
    return [
        'kind'       => isset(VISIT_KINDS[$post['kind'] ?? '']) ? $post['kind'] : 'montaza',
        'team_id'    => (int) ($post['team_id'] ?? 0) ?: null,
        'start_date' => $d && $d->format('Y-m-d') === $date ? $date : null,
        'start_time' => preg_match('/^\d{2}:\d{2}$/', $time) ? $time . ':00' : null,
        'days'       => max(1, min(30, (int) ($post['days'] ?? 1))),
        'confirmed'  => !empty($post['confirmed']),
        'note'       => trim((string) ($post['note'] ?? '')),
        'reason'     => trim((string) ($post['reason'] ?? '')),
        'override'   => trim((string) ($post['override'] ?? '')),
    ];
}

/**
 * Pravi ili menja termin. Pomeranje postojećeg datuma traži razlog i obaveštava ekipu.
 * Potvrđivanje termina traži spremnost (vlasnik može izuzetak uz belešku).
 */
function visit_save(array $project, ?array $visit, array $in, array $user): int
{
    if ($user['role'] !== 'vlasnik') {
        throw new RuleError('Termine raspoređuje vlasnik.');
    }
    $moved = $visit && $visit['start_date'] && $in['start_date'] !== $visit['start_date'];
    if ($moved && $in['reason'] === '') {
        throw new RuleError('Upiši razlog pomeranja termina.');
    }
    if ($in['confirmed'] && !$in['start_date']) {
        throw new RuleError('Potvrđen termin mora imati datum.');
    }
    $b = bundle_one((int) $project['id']);
    if ($in['confirmed'] && (!$visit || !$visit['confirmed'])) {
        $probe = ['team_id' => $in['team_id'], 'confirmed' => 1];
        $checks = readiness($b, $probe);
        if (!readiness_ok($checks) && $in['override'] === '' && $in['kind'] === 'montaza') {
            $miss = array_map(fn($c) => mb_strtolower($c[0]), array_filter($checks, fn($c) => !$c[1]));
            throw new RuleError('Montaža nije spremna: ' . implode(', ', $miss) . '. Upiši razlog izuzetka da je ipak potvrdiš.', $miss);
        }
    }

    if ($visit) {
        q('UPDATE visits SET kind = ?, team_id = ?, start_date = ?, start_time = ?, days = ?, confirmed = ?, note = ?,
              readiness_override = COALESCE(?, readiness_override), override_by = IF(? IS NULL, override_by, ?) WHERE id = ?',
            [$in['kind'], $in['team_id'], $in['start_date'], $in['start_time'], $in['days'], (int) $in['confirmed'], $in['note'] ?: null,
             $in['override'] ?: null, $in['override'] ?: null, $user['id'], $visit['id']]);
        $vid = (int) $visit['id'];
        log_activity('visit_updated', (int) $project['id'], null, [
            'visit_id' => $vid, 'from' => $visit['start_date'], 'to' => $in['start_date'], 'reason' => $in['reason'] ?: null,
            'confirmed' => (int) $in['confirmed'], 'override' => $in['override'] ?: null,
        ]);
    } else {
        q('INSERT INTO visits (project_id, kind, team_id, start_date, start_time, days, confirmed, note, readiness_override, override_by, created_by)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$project['id'], $in['kind'], $in['team_id'], $in['start_date'], $in['start_time'], $in['days'], (int) $in['confirmed'],
             $in['note'] ?: null, $in['override'] ?: null, $in['override'] ? $user['id'] : null, $user['id']]);
        $vid = (int) db()->lastInsertId();
        log_activity('visit_created', (int) $project['id'], null, ['visit_id' => $vid, 'date' => $in['start_date']]);
    }

    // Obaveštenja: ekipi pri raspoređivanju/pomeranju/potvrdi
    $v = load_visit($vid);
    $when = visit_when($v);
    if ($v['leader_id'] && $in['start_date'] && (!$visit || $moved || ($in['confirmed'] && !$visit['confirmed']) || (int) $visit['team_id'] !== (int) $in['team_id'])) {
        $txt = $moved
            ? 'POMEREN TERMIN: ' . project_ref($project) . ' — ' . visit_when($visit) . ' → ' . $when . ' (razlog: ' . $in['reason'] . ')'
            : VISIT_KINDS[$v['kind']] . ' ' . ($in['confirmed'] ? '(potvrđeno)' : '(okvirno)') . ': ' . project_ref($project) . ' — ' . $when;
        notify_user((int) $v['leader_id'], $txt, url('projekat', ['id' => $project['id'], 't' => 'montaza']));
    }
    if ($moved) {
        foreach (array_filter(users_map(), fn($u) => $u['role'] === 'hala' && $u['active']) as $hid => $_) {
            notify_user($hid, 'Pomeren termin montaže: ' . project_ref($project) . ' → ' . $when, url('projekat', ['id' => $project['id']]));
        }
    }
    return $vid;
}

function visit_cancel(array $visit, string $reason, array $user): void
{
    if ($user['role'] !== 'vlasnik') {
        throw new RuleError('Termin otkazuje vlasnik.');
    }
    if ($reason === '') {
        throw new RuleError('Upiši razlog otkazivanja.');
    }
    q("UPDATE visits SET status = 'otkazan', note = CONCAT(COALESCE(note, ''), ?) WHERE id = ?", ["\nOtkazano: $reason", $visit['id']]);
    log_activity('visit_cancelled', (int) $visit['project_id'], null, ['visit_id' => (int) $visit['id'], 'reason' => $reason]);
    if ($visit['leader_id']) {
        $p = load_project((int) $visit['project_id']);
        notify_user((int) $visit['leader_id'], 'OTKAZAN termin: ' . project_ref($p) . ' — ' . visit_when($visit) . " ($reason)", url('projekat', ['id' => $p['id']]));
    }
}

// ── Uplate ──────────────────────────────────────────────────

/** Evidentira primljen novac. Razlika ili delimična uplata odmah ide vlasniku. */
function payment_add(array $project, float $amount, string $method, array $user, ?int $visitId = null, string $note = ''): int
{
    if ($amount <= 0) {
        throw new RuleError('Iznos mora biti veći od nule.');
    }
    if (!isset(METHOD_LABELS[$method])) {
        $method = 'gotovina';
    }
    q('INSERT INTO payments (project_id, visit_id, amount, method, received_by, note) VALUES (?, ?, ?, ?, ?, ?)',
        [$project['id'], $visitId, number_format($amount, 2, '.', ''), $method, $user['id'], $note !== '' ? mb_substr($note, 0, 500) : null]);
    $id = (int) db()->lastInsertId();
    log_activity('payment_received', (int) $project['id'], null, ['payment_id' => $id, 'amount' => $amount, 'method' => $method]);
    $info = payment_info(bundle_one((int) $project['id']));
    $msg = 'Naplaćeno ' . fmt_money((string) $amount) . ' (' . mb_strtolower(METHOD_LABELS[$method]) . '): ' . project_ref($project) . ' — ' . $user['name'];
    if ($info['remaining'] > 0) {
        $msg .= ' — OSTAJE ' . fmt_money((string) $info['remaining']);
    }
    notify(['vlasnik'], $msg, (int) $project['id']);
    return $id;
}

/** Vlasnik potvrđuje da je primio gotovinu od radnika ("predato firmi"). */
function payments_handover(array $ids, array $user): int
{
    if ($user['role'] !== 'vlasnik' || !$ids) {
        throw new RuleError('Predaju novca potvrđuje vlasnik.');
    }
    $n = q("UPDATE payments SET handed_at = NOW(), handed_to = ? WHERE method = 'gotovina' AND handed_at IS NULL AND id IN (" . in_list($ids) . ')',
        array_merge([$user['id']], $ids))->rowCount();
    log_activity('cash_handover', null, null, ['payments' => $ids, 'count' => $n]);
    return $n;
}

/** Gotovina koja je kod radnika (nije predata): [user_id => [iznos, broj]] */
function cash_held_by_worker(): array
{
    $out = [];
    foreach (q_all("SELECT received_by, SUM(amount) s, COUNT(*) n FROM payments WHERE method = 'gotovina' AND handed_at IS NULL GROUP BY received_by") as $r) {
        $out[(int) $r['received_by']] = [(float) $r['s'], (int) $r['n']];
    }
    return $out;
}

// ── Montaža: početak i završetak ────────────────────────────

/** Da li ova poseta treba da naplati (teren naplaćuje, ostalo je za naplatu). */
function visit_collects(array $b, array $visit): bool
{
    $p = $b['p'];
    if ($p['payment_collector'] !== 'teren' || $p['payment_when'] === 'ne' || $visit['kind'] === 'dijagnoza') {
        return false;
    }
    return payment_info($b)['remaining'] > 0;
}

/** "Počinjem". Naplata PRE: bez uplate ekipa ne počinje (osim izuzetka vlasnika na terminu). */
function visit_start(int $visitId, array $user, ?float $paid = null, string $method = 'gotovina'): void
{
    $v = load_visit($visitId);
    $b = $v ? bundle_one((int) $v['project_id']) : null;
    if (!$v || !$b || !can_act_visit($user, $b['p'], $v)) {
        throw new RuleError('Termin ne postoji ili je završen.');
    }
    if ($v['status'] === 'u_toku') {
        return;
    }
    if ($paid !== null && $paid > 0) {
        payment_add($b['p'], $paid, $method, $user, $visitId);
        $b = bundle_one((int) $v['project_id']);
    }
    // Naplata PRE: bez pune uplate ekipa ne počinje. Izuzetak može samo vlasnik (sam pokreće termin).
    if ($b['p']['payment_when'] === 'pre' && visit_collects($b, $v) && $v['kind'] !== 'dijagnoza' && $user['role'] !== 'vlasnik') {
        $rem = payment_info($b)['remaining'];
        throw new RuleError('Naplata PRE početka: ostalo je ' . fmt_money((string) $rem) . '. Upiši primljeni iznos ili pozovi vlasnika.');
    }
    q("UPDATE visits SET status = 'u_toku', started_at = COALESCE(started_at, NOW()) WHERE id = ?", [$visitId]);
    log_activity('visit_started', (int) $v['project_id'], null, ['visit_id' => $visitId]);
    notify(['vlasnik'], VISIT_KINDS[$v['kind']] . ' počela: ' . project_ref($b['p']) . ' — ' . $user['name'], (int) $v['project_id']);
}

/**
 * Završetak termina.
 * $in: result (celo|delimicno|ponovni), remaining (tekst), note, video, handover, paid (float|null), method, debt_ok (bool)
 * $photos: ['celina'=>[ids], 'detalj'=>[ids], 'nedostatak'=>[ids]]
 * Vraća spisak onoga što fali (prazno = uspelo).
 */
function visit_complete(int $visitId, array $user, array $in, array $photos): array
{
    $v = load_visit($visitId);
    $b = $v ? bundle_one((int) $v['project_id']) : null;
    if (!$v || !$b || !can_act_visit($user, $b['p'], $v)) {
        throw new RuleError('Termin ne postoji ili je već završen.');
    }
    $p = $b['p'];
    $result = isset(VISIT_RESULTS[$in['result'] ?? '']) ? $in['result'] : '';
    $have = fn(string $purpose) => (int) q_val('SELECT COUNT(*) FROM files WHERE visit_id = ? AND purpose = ?', [$visitId, $purpose]) + count($photos[$purpose] ?? []);

    $miss = [];
    if ($result === '') {
        $miss[] = 'ishod (celo / delimično / ponovni dolazak)';
    }
    if (in_array($v['kind'], ['montaza', 'dorada', 'servis'], true) && $have('celina') < 1) {
        $miss[] = 'slika celine';
    }
    if ($result && $result !== 'celo' && trim((string) $in['remaining']) === '') {
        $miss[] = 'šta je ostalo da se uradi';
    }
    if ($v['kind'] === 'montaza' && $result === 'celo') {
        if (empty($in['video'])) {
            $miss[] = 'video u Viber grupi';
        }
        if (empty($in['handover'])) {
            $miss[] = 'predat daljinski i uputstvo';
        }
    }
    $collects = visit_collects($b, $v) && $result === 'celo';
    $rem = payment_info($b)['remaining'];
    if ($collects && ($in['paid'] === null) && empty($in['debt_ok'])) {
        $miss[] = 'naplata (' . fmt_money((string) $rem) . ')';
    }
    if ($collects && $in['paid'] !== null && $in['paid'] + 0.001 < $rem && empty($in['debt_ok'])) {
        $miss[] = 'potvrda da kupac duguje ostatak';
    }
    if ($miss) {
        return $miss;
    }

    db()->beginTransaction();
    try {
        foreach (['celina', 'detalj', 'nedostatak'] as $purpose) {
            attach_temp_files($photos[$purpose] ?? [], (int) $p['id'], ['visit_id' => $visitId, 'kind' => $purpose === 'celina' ? 'posle' : 'foto', 'purpose' => $purpose], (int) $user['id']);
        }
        q("UPDATE visits SET status = 'zavrseno', result = ?, remaining = ?, started_at = COALESCE(started_at, NOW()), completed_at = NOW(),
              completed_by = ?, note = CONCAT(COALESCE(note, ''), ?), video_sent = ?, handover_done = ? WHERE id = ?",
            [$result, $result !== 'celo' ? trim((string) $in['remaining']) : null, $user['id'],
             trim((string) $in['note']) !== '' ? "\n" . trim((string) $in['note']) : '', (int) !empty($in['video']), (int) !empty($in['handover']), $visitId]);
        q('UPDATE stoppages SET resolved_at = NOW(), resolved_by = ? WHERE visit_id = ? AND resolved_at IS NULL', [$user['id'], $visitId]);
        // Delimično / ponovni dolazak → povezan novi termin (neraspoređen) sa onim što je ostalo
        if ($result !== 'celo') {
            q("INSERT INTO visits (project_id, kind, team_id, parent_visit_id, note, created_by) VALUES (?, 'dorada', ?, ?, ?, ?)",
                [$p['id'], $v['team_id'], $visitId, 'Ostalo: ' . trim((string) $in['remaining']), $user['id']]);
        }
        log_activity('visit_completed', (int) $p['id'], null, ['visit_id' => $visitId, 'result' => $result]);
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
    if ($collects && $in['paid'] !== null && $in['paid'] > 0) {
        payment_add($p, (float) $in['paid'], $in['method'] ?? 'gotovina', $user, $visitId);
    }
    $txt = VISIT_KINDS[$v['kind']] . ' — ' . mb_strtolower(VISIT_RESULTS[$result]) . ': ' . project_ref($p) . ' (' . $user['name'] . ')';
    if ($result !== 'celo') {
        $txt .= ' — OSTALO: ' . trim((string) $in['remaining']) . ' — rasporedi ponovni dolazak';
    }
    if ($collects && !empty($in['debt_ok'])) {
        $after = payment_info(bundle_one((int) $p['id']))['remaining'];
        if ($after > 0) {
            $txt .= ' — KUPAC DUGUJE ' . fmt_money((string) $after);
        }
    }
    notify(['vlasnik'], $txt, (int) $p['id']);
    maybe_complete_project((int) $p['id']);
    return [];
}

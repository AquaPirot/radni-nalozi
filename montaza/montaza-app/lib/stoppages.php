<?php
declare(strict_types=1);

// Zastoj kao konkretna obaveza: razlog, opis, ko rešava, sledeći korak, očekivano rešenje.

/**
 * $target: ['project_id'=>…] + jedno od 'phase_id' | 'visit_id' | 'task_id' (ili samo projekat)
 * $in: reason, note, owner_id, next_step, expected_date
 */
function stoppage_open(array $target, array $in, array $user): int
{
    if (!isset(REASON_LABELS[$in['reason'] ?? '']) || $in['reason'] === 'ekipa') {
        throw new RuleError('Izaberi razlog zastoja.');
    }
    if (trim((string) $in['note']) === '') {
        throw new RuleError('Upiši kratak opis (npr. „čeka staklo").');
    }
    if (empty($in['owner_id'])) {
        throw new RuleError('Izaberi ko rešava zastoj.');
    }
    $exp = null;
    if (!empty($in['expected_date'])) {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $in['expected_date']);
        $exp = $d ? $d->format('Y-m-d') : null;
    }
    db()->beginTransaction();
    try {
        if (!empty($target['phase_id'])) {
            q("UPDATE production_phases SET status = 'zastoj' WHERE id = ? AND status <> 'zavrseno'", [$target['phase_id']]);
        }
        if (!empty($target['task_id'])) {
            q("UPDATE tasks SET status = 'zastoj' WHERE id = ? AND status <> 'zavrseno'", [$target['task_id']]);
        }
        q('INSERT INTO stoppages (project_id, phase_id, visit_id, task_id, reason, note, owner_id, next_step, expected_date, created_by)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$target['project_id'] ?? null, $target['phase_id'] ?? null, $target['visit_id'] ?? null, $target['task_id'] ?? null,
             $in['reason'], mb_substr(trim((string) $in['note']), 0, 500), (int) $in['owner_id'],
             trim((string) ($in['next_step'] ?? '')) !== '' ? mb_substr(trim((string) $in['next_step']), 0, 255) : null, $exp, $user['id']]);
        $id = (int) db()->lastInsertId();
        log_activity('stoppage', $target['project_id'] ?? null, null, $target + ['reason' => $in['reason'], 'note' => $in['note'], 'owner' => (int) $in['owner_id']]);
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }

    $s = q_one('SELECT * FROM stoppages WHERE id = ?', [$id]);
    $where = '';
    $link = url();
    if (!empty($target['project_id'])) {
        $p = load_project((int) $target['project_id']);
        $nv = next_visit(visits_for([(int) $p['id']])[(int) $p['id']] ?? []);
        $where = project_ref($p);
        $link = url('projekat', ['id' => $p['id']]);
        $text = 'ZASTOJ — ' . $where . ': ' . stoppage_text($s, $nv) . ' (prijavio ' . $user['name'] . ')';
    } else {
        $t = q_one('SELECT title FROM tasks WHERE id = ?', [$target['task_id']]);
        $link = url('zadatak', ['id' => $target['task_id']]);
        $text = 'ZASTOJ na zadatku „' . ($t['title'] ?? '') . '": ' . stoppage_text($s) . ' (prijavio ' . $user['name'] . ')';
    }
    $to = array_unique(array_merge(owner_ids(), [(int) $in['owner_id']]));
    foreach ($to as $uid) {
        if ($uid !== (int) $user['id']) {
            notify_user($uid, $text, $link);
        }
    }
    return $id;
}

/** Zatvara zastoj i vraća fazu/zadatak u rad. */
function stoppage_resolve(int $id, array $user, string $note = ''): void
{
    $s = q_one('SELECT * FROM stoppages WHERE id = ? AND resolved_at IS NULL', [$id]);
    if (!$s) {
        throw new RuleError('Zastoj je već rešen.');
    }
    $canResolve = $user['role'] === 'vlasnik' || (int) $s['owner_id'] === (int) $user['id'] || (int) $s['created_by'] === (int) $user['id'];
    if (!$canResolve) {
        throw new RuleError('Zastoj rešava odgovorna osoba ili vlasnik.');
    }
    q('UPDATE stoppages SET resolved_at = NOW(), resolved_by = ?, note = CONCAT(note, ?) WHERE id = ?',
        [$user['id'], $note !== '' ? ' → rešeno: ' . mb_substr($note, 0, 200) : '', $id]);
    if ($s['phase_id']) {
        q("UPDATE production_phases SET status = IF(started_at IS NULL, 'ceka', 'u_toku') WHERE id = ? AND status = 'zastoj'", [$s['phase_id']]);
    }
    if ($s['task_id']) {
        q("UPDATE tasks SET status = IF(started_at IS NULL, 'ceka', 'u_toku') WHERE id = ? AND status = 'zastoj'", [$s['task_id']]);
    }
    log_activity('stoppage_resolved', $s['project_id'] ? (int) $s['project_id'] : null, null, ['stoppage_id' => $id, 'note' => $note]);
    if ((int) $s['created_by'] !== (int) $user['id'] && $s['created_by']) {
        notify_user((int) $s['created_by'], 'Rešen zastoj: ' . $s['note'] . ($note !== '' ? " — $note" : '') . ' (' . $user['name'] . ')',
            $s['project_id'] ? url('projekat', ['id' => $s['project_id']]) : url('zadatak', ['id' => $s['task_id']]));
    }
}

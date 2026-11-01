<?php
declare(strict_types=1);

// Proizvodnja: radni nalog (verzije, izdat ≠ preuzet) i faze po sistemu.

class RuleError extends RuntimeException
{
    public function __construct(string $message, public readonly array $missing = [])
    {
        parent::__construct($message);
    }
}

/** Faze iz šablona za vrstu sistema (+ čelična konstrukcija na početku ako je uključena). */
function create_system_phases(int $projectId, int $systemId, ?int $typeId, bool $withSteel, int $doneUntilSort = 0, ?int $userId = null): void
{
    $tpl = $typeId ? q_all('SELECT name, sort, requires_photo FROM phase_templates WHERE system_type_id = ? ORDER BY sort, id', [$typeId]) : [];
    if (!$tpl) {
        $tpl = [['name' => 'Izrada', 'sort' => 10, 'requires_photo' => 1], ['name' => 'Pakovanje', 'sort' => 20, 'requires_photo' => 1]];
    }
    if ($withSteel) {
        array_unshift($tpl, ['name' => 'Čelična konstrukcija', 'sort' => 5, 'requires_photo' => 1]);
    }
    $assignee = default_hala_user();
    foreach ($tpl as $t) {
        $imported = $doneUntilSort > 0 && (int) $t['sort'] <= $doneUntilSort;
        q(
            'INSERT INTO production_phases (project_id, system_id, name, sort, assignee_id, status, completed_at, completed_by, requires_photo, imported)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$projectId, $systemId, $t['name'], $t['sort'], $assignee, $imported ? 'zavrseno' : 'ceka',
             $imported ? date('Y-m-d H:i:s') : null, $imported ? $userId : null, (int) $t['requires_photo'], (int) $imported]
        );
    }
}

// ── Radni nalog ─────────────────────────────────────────────

/** Izdaje nalog (nova verzija). PDF je opcion samo za "uneto naknadno". Obaveštava halu. */
function order_issue(array $project, ?array $pdfUpload, string $note, array $user): int
{
    $stored = $pdfUpload ? store_pdf($pdfUpload) : null;
    try {
        db()->beginTransaction();
        $prev = q_one('SELECT * FROM project_orders WHERE project_id = ? ORDER BY version DESC LIMIT 1 FOR UPDATE', [$project['id']]);
        $version = $prev ? (int) $prev['version'] + 1 : 1;
        $fileId = $stored ? insert_file((int) $project['id'], null, 'nalog_pdf', $stored, (int) $user['id']) : null;
        q('INSERT INTO project_orders (project_id, version, file_id, note, issued_by) VALUES (?, ?, ?, ?, ?)',
            [$project['id'], $version, $fileId, $note !== '' ? mb_substr($note, 0, 500) : null, $user['id']]);
        $oid = (int) db()->lastInsertId();
        log_activity('order_issued', (int) $project['id'], null, ['version' => $version, 'file' => $fileId]);
        db()->commit();
    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        if ($stored) {
            delete_stored($stored);
        }
        throw $ex;
    }
    $msg = $version > 1
        ? 'IZMENJEN NALOG v' . $version . ': ' . project_ref($project) . ($prev && $prev['accepted_at'] ? ' — stara verzija više ne važi, preuzmi novu.' : '') . ($note !== '' ? " ($note)" : '')
        : 'Novi radni nalog: ' . project_ref($project) . ' — preuzmi ga u aplikaciji.';
    notify(['hala'], $msg, (int) $project['id']);
    return $oid;
}

/** Hala potvrđuje da je preuzela (aktuelni) nalog. */
function order_accept(int $orderId, array $user): void
{
    if (!in_array($user['role'], ['hala', 'vlasnik'], true)) {
        throw new RuleError('Nalog preuzima hala.');
    }
    $o = q_one('SELECT * FROM project_orders WHERE id = ?', [$orderId]);
    if (!$o) {
        throw new RuleError('Nalog ne postoji.');
    }
    $latest = (int) q_val('SELECT MAX(version) FROM project_orders WHERE project_id = ?', [$o['project_id']]);
    if ((int) $o['version'] !== $latest) {
        throw new RuleError('Postoji novija verzija naloga — preuzmi nju.');
    }
    if ($o['accepted_at']) {
        return;
    }
    q('UPDATE project_orders SET accepted_by = ?, accepted_at = NOW() WHERE id = ?', [$user['id'], $orderId]);
    log_activity('order_accepted', (int) $o['project_id'], null, ['version' => (int) $o['version']]);
    $p = load_project((int) $o['project_id']);
    notify(['vlasnik'], $user['name'] . ' je preuzeo nalog v' . $o['version'] . ': ' . project_ref($p), (int) $p['id']);
}

// ── Faze proizvodnje ────────────────────────────────────────

function lock_phase(int $id): array
{
    $ph = q_one('SELECT * FROM production_phases WHERE id = ? FOR UPDATE', [$id]);
    if (!$ph) {
        throw new RuleError('Faza ne postoji.');
    }
    $p = q_one('SELECT * FROM projects WHERE id = ? FOR UPDATE', [$ph['project_id']]);
    return [$p, $ph];
}

function can_act_phase(array $user, array $project, array $phase): bool
{
    return $project['status'] === 'aktivan' && $phase['status'] !== 'zavrseno'
        && in_array($user['role'], ['vlasnik', 'hala'], true);
}

/** Hala radi samo po preuzetom nalogu (vlasnik može izuzetno). */
function phase_blocked(array $project, array $user): ?string
{
    $o = q_one('SELECT * FROM project_orders WHERE project_id = ? ORDER BY version DESC LIMIT 1', [$project['id']]);
    if (!$o) {
        return 'Nalog još nije izdat.';
    }
    if (!$o['accepted_at'] && $user['role'] !== 'vlasnik') {
        return 'Prvo preuzmi nalog' . ($o['version'] > 1 ? ' (nova verzija v' . $o['version'] . ')' : '') . '.';
    }
    return null;
}

function phase_start(int $id, array $user): void
{
    db()->beginTransaction();
    try {
        [$p, $ph] = lock_phase($id);
        if (!can_act_phase($user, $p, $ph)) {
            throw new RuleError('Faza je već završena ili nemaš pravo.');
        }
        if ($why = phase_blocked($p, $user)) {
            throw new RuleError($why);
        }
        $resume = $ph['status'] === 'zastoj';
        q("UPDATE production_phases SET status = 'u_toku', started_at = COALESCE(started_at, NOW()) WHERE id = ?", [$id]);
        if ($resume) {
            q('UPDATE stoppages SET resolved_at = NOW(), resolved_by = ? WHERE phase_id = ? AND resolved_at IS NULL', [$user['id'], $id]);
        }
        log_activity($resume ? 'stoppage_resolved' : 'phase_started', (int) $p['id'], null, ['phase' => $ph['name'], 'phase_id' => $id]);
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
}

/** Završetak faze uz fotografije (ako faza traži sliku i podešavanje min_photos_hala > 0). */
function phase_complete(int $id, array $user, string $note, array $fotoIds): array
{
    db()->beginTransaction();
    try {
        [$p, $ph] = lock_phase($id);
        if (!can_act_phase($user, $p, $ph)) {
            throw new RuleError('Faza je već završena ili nemaš pravo.');
        }
        if ($why = phase_blocked($p, $user)) {
            throw new RuleError($why);
        }
        attach_temp_files($fotoIds, (int) $p['id'], ['phase_id' => $id, 'kind' => 'foto'], (int) $user['id']);
        $have = (int) q_val('SELECT COUNT(*) FROM files WHERE phase_id = ?', [$id]);
        $min = $ph['requires_photo'] ? (int) setting('min_photos_hala', '1') : 0;
        if ($have < $min) {
            throw new RuleError('Potrebna je fotografija.', ['fotografija']);
        }
        $prev = q_val("SELECT MAX(completed_at) FROM production_phases WHERE system_id = ? AND sort < ? AND status = 'zavrseno'", [$ph['system_id'], $ph['sort']]);
        q("UPDATE production_phases SET status = 'zavrseno', started_at = COALESCE(started_at, ?, ?), completed_at = NOW(), completed_by = ?, note = ?, imported = 0 WHERE id = ?",
            [$prev, $p['released_at'] ?? $p['created_at'], $user['id'], $note !== '' ? mb_substr($note, 0, 2000) : null, $id]);
        q('UPDATE stoppages SET resolved_at = NOW(), resolved_by = ? WHERE phase_id = ? AND resolved_at IS NULL', [$user['id'], $id]);
        log_activity('phase_completed', (int) $p['id'], null, ['phase' => $ph['name'], 'phase_id' => $id, 'photos' => count($fotoIds)]);
        db()->commit();
    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $ex;
    }

    $b = bundle_one((int) $p['id']);
    $allDone = phases_done($b['phases']);
    if ($allDone) {
        $nv = next_visit($b['visits']);
        notify(['teren', 'vlasnik'], 'SPREMNO za montažu: ' . project_ref($p) . ($nv ? ' — ' . visit_when($nv) : ' — termin nije raspoređen'), (int) $p['id']);
    }
    maybe_complete_project((int) $p['id']);
    return ['all_done' => $allDone];
}

/** Vlasnik menja odgovornu osobu i rok faze. */
function phase_assign(int $id, ?int $assigneeId, ?string $due, array $user): void
{
    if ($user['role'] !== 'vlasnik') {
        throw new RuleError('Samo vlasnik dodeljuje faze.');
    }
    $ph = q_one('SELECT * FROM production_phases WHERE id = ?', [$id]);
    if (!$ph) {
        throw new RuleError('Faza ne postoji.');
    }
    q('UPDATE production_phases SET assignee_id = ?, due_date = ? WHERE id = ?', [$assigneeId, $due, $id]);
    log_activity('phase_assigned', (int) $ph['project_id'], null, ['phase' => $ph['name'], 'assignee' => $assigneeId, 'due' => $due]);
    if ($assigneeId && $assigneeId !== (int) $ph['assignee_id'] && $assigneeId !== (int) $user['id']) {
        $p = load_project((int) $ph['project_id']);
        notify_user($assigneeId, 'Dodeljena ti je faza „' . $ph['name'] . '": ' . project_ref($p) . ($due ? ', rok ' . fmt_dm($due) : ''), url('projekat', ['id' => $p['id'], 't' => 'proizvodnja']));
    }
}

/** Projekat je završen kad su sve faze i svi termini gotovi (celo) i nema otvorenih ponovnih dolazaka. */
function maybe_complete_project(int $projectId): bool
{
    $b = bundle_one($projectId);
    if (!$b || $b['p']['status'] !== 'aktivan' || !phases_done($b['phases'])) {
        return false;
    }
    if (!$b['visits']) {
        return false; // bez terena projekat se zatvara ručno
    }
    foreach ($b['visits'] as $v) {
        if ($v['status'] !== 'zavrseno') {
            return false;
        }
    }
    q("UPDATE projects SET status = 'zavrsen', completed_at = NOW() WHERE id = ? AND status = 'aktivan'", [$projectId]);
    log_activity('project_completed', $projectId);
    return true;
}

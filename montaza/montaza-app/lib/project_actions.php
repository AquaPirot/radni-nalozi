<?php
declare(strict_types=1);

// Radnje nad projektom (vlasnik): nacrt, puštanje u realizaciju, izmena sistema, arhiva.

/** Čuva projekat (novi ili postojeći). $release: pusti u realizaciju posle čuvanja. Vraća id. */
function project_save(?array $project, array $in, array $user, bool $release): int
{
    db()->beginTransaction();
    try {
        $fields = [
            'work_type' => $in['work_type'], 'intake' => $in['intake'],
            'customer_name' => $in['customer_name'], 'phone' => $in['phone'] ?: null, 'city' => $in['city'] ?: null,
            'address' => $in['address'] ?: null, 'description' => $in['description'] ?: null, 'deadline' => $in['deadline'],
            'payment_when' => $in['payment_when'], 'payment_amount' => $in['payment_when'] === 'ne' ? null : $in['payment_amount'],
            'payment_collector' => $in['payment_collector'], 'notes' => $in['notes'] ?: null,
            'parent_project_id' => $in['work_type'] === 'reklamacija' ? $in['parent_project_id'] : null,
        ];
        if ($project) {
            $pid = (int) $project['id'];
            $changed = [];
            foreach ($fields as $k => $v) {
                if ((string) ($project[$k] ?? '') !== (string) ($v ?? '')) {
                    $changed[$k] = ['old' => $project[$k] ?? null, 'new' => $v];
                }
            }
            if ($changed) {
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
                q("UPDATE projects SET $set WHERE id = ?", array_merge(array_values($fields), [$pid]));
                log_activity('project_updated', $pid, null, $changed);
            }
        } else {
            $code = next_project_code();
            $fields['code'] = $code;
            $fields['status'] = 'nacrt';
            $fields['created_by'] = $user['id'];
            $cols = implode(', ', array_keys($fields));
            q("INSERT INTO projects ($cols) VALUES (" . in_list($fields) . ')', array_values($fields));
            $pid = (int) db()->lastInsertId();
            log_activity('project_created', $pid, null, ['code' => $code, 'customer' => $in['customer_name'], 'work_type' => $in['work_type']]);
        }

        $status = $project['status'] ?? 'nacrt';
        save_systems($pid, $in['systems'], $status !== 'nacrt', $user);

        db()->commit();
    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $ex;
    }

    if ($in['lokacija_id']) {
        try {
            set_lokacija_photo(load_project($pid), (int) $in['lokacija_id'], $user);
        } catch (RuleError) {
            // već zakačena (dupli klik) — ništa
        }
    }
    if ($in['nalog_id']) {
        order_issue_from_file(load_project($pid), (int) $in['nalog_id'], $user);
    }
    if ($release && $status === 'nacrt') {
        project_release($pid, $in, $user);
    }
    return $pid;
}

/**
 * Sistemi projekta. Ako je projekat već u realizaciji, novi sistem odmah dobija faze;
 * brisanje i promena vrste su dozvoljeni samo dok nijedna faza tog sistema nije započeta.
 */
function save_systems(int $pid, array $systems, bool $active, array $user): void
{
    $existing = [];
    foreach (q_all('SELECT * FROM project_systems WHERE project_id = ?', [$pid]) as $s) {
        $existing[(int) $s['id']] = $s;
    }
    $started = fn(int $sid) => (bool) q_val("SELECT 1 FROM production_phases WHERE system_id = ? AND (status <> 'ceka' OR imported = 1) LIMIT 1", [$sid]);
    $sort = 10;
    foreach ($systems as $s) {
        $id = $s['id'] && isset($existing[$s['id']]) ? $s['id'] : null;
        if ($s['delete']) {
            if ($id) {
                if ($started($id)) {
                    throw new RuleError('Sistem čija je izrada počela ne može da se obriše.');
                }
                q('DELETE FROM project_systems WHERE id = ?', [$id]);
                log_activity('system_removed', $pid, null, ['system_id' => $id]);
            }
            continue;
        }
        $row = [$s['type_id'], $s['qty'], $s['width'], $s['length'], $s['height'], $s['ral'] ?: null,
                $s['spec'] ? json_encode($s['spec'], JSON_UNESCAPED_UNICODE) : null, $s['note'] ?: null, (int) $s['with_steel'], $sort];
        if ($id) {
            $old = $existing[$id];
            $typeChanged = (int) $old['system_type_id'] !== (int) $s['type_id'] || (int) $old['with_steel'] !== (int) $s['with_steel'];
            if ($typeChanged && $started($id)) {
                throw new RuleError('Vrsta sistema ne može da se menja kad je izrada počela.');
            }
            q('UPDATE project_systems SET system_type_id = ?, qty = ?, width = ?, length = ?, height = ?, ral = ?, spec = ?, note = ?, with_steel = ?, sort = ? WHERE id = ?',
                array_merge($row, [$id]));
            if ($typeChanged && $active) {
                q('DELETE FROM production_phases WHERE system_id = ?', [$id]);
                create_system_phases($pid, $id, $s['type_id'], $s['with_steel'], 0, (int) $user['id']);
            }
        } else {
            q('INSERT INTO project_systems (system_type_id, qty, width, length, height, ral, spec, note, with_steel, sort, project_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge($row, [$pid]));
            $id = (int) db()->lastInsertId();
            if ($active) {
                create_system_phases($pid, $id, $s['type_id'], $s['with_steel'], 0, (int) $user['id']);
                log_activity('system_added', $pid, null, ['system_id' => $id]);
            } else {
                // nacrt: zapamti "već urađeno do" za puštanje u realizaciju
                if ($s['done_until']) {
                    q("UPDATE project_systems SET spec = JSON_SET(COALESCE(spec, '{}'), '$._done_until', ?) WHERE id = ?", [$s['done_until'], $id]);
                }
            }
        }
        $sort += 10;
    }
}

/** Nacrt → realizacija: pravi faze za sisteme, beleži preuzeti posao, obaveštava halu i teren. */
function project_release(int $pid, array $in, array $user): void
{
    $p = load_project($pid);
    if (!$p || $p['status'] !== 'nacrt') {
        return;
    }
    $anyDone = false;
    foreach (systems_for([$pid])[$pid] ?? [] as $s) {
        if (q_val('SELECT 1 FROM production_phases WHERE system_id = ? LIMIT 1', [$s['id']])) {
            continue;
        }
        $doneUntil = (int) ($s['spec']['_done_until'] ?? 0);
        foreach ($in['systems'] as $row) {
            if ($row['id'] && (int) $row['id'] === (int) $s['id'] && $row['done_until']) {
                $doneUntil = $row['done_until'];
            }
        }
        if ($p['intake'] !== 'preuzet') {
            $doneUntil = 0;
        }
        $anyDone = $anyDone || $doneUntil > 0;
        create_system_phases($pid, (int) $s['id'], $s['system_type_id'] ? (int) $s['system_type_id'] : null, (bool) $s['with_steel'], $doneUntil, (int) $user['id']);
    }
    q("UPDATE projects SET status = 'aktivan', released_at = NOW() WHERE id = ?", [$pid]);
    // Preuzet započet posao: nalog se smatra izdatim i preuzetim ako je izrada već počela
    if ($p['intake'] === 'preuzet' && $anyDone && !q_val('SELECT 1 FROM project_orders WHERE project_id = ?', [$pid])) {
        q("INSERT INTO project_orders (project_id, version, note, issued_by, accepted_by, accepted_at) VALUES (?, 1, 'Uneto pri preuzimanju započetog posla', ?, ?, NOW())",
            [$pid, $user['id'], $user['id']]);
    }
    log_activity('project_released', $pid);
    $b = bundle_one($pid);
    notify(['hala', 'teren'], 'Novi posao: ' . project_ref($p) . ($b['systems'] ? ' — ' . systems_summary($b['systems']) : '') . ($p['deadline'] ? ', rok ' . fmt_dm($p['deadline']) : ''), $pid);
}

/** PDF koji je već poslat (na čekanju) postaje nova verzija naloga. */
function order_issue_from_file(array $project, int $fileId, array $user, string $note = ''): void
{
    $n = attach_temp_files([$fileId], (int) $project['id'], ['kind' => 'nalog_pdf'], (int) $user['id']);
    if (!$n) {
        return;
    }
    $prev = q_one('SELECT * FROM project_orders WHERE project_id = ? ORDER BY version DESC LIMIT 1', [$project['id']]);
    $version = $prev ? (int) $prev['version'] + 1 : 1;
    q('INSERT INTO project_orders (project_id, version, file_id, note, issued_by) VALUES (?, ?, ?, ?, ?)',
        [$project['id'], $version, $fileId, $note !== '' ? $note : null, $user['id']]);
    log_activity('order_issued', (int) $project['id'], null, ['version' => $version, 'file' => $fileId]);
    if ($project['status'] === 'aktivan' || $version > 1) {
        notify(['hala'], ($version > 1 ? 'IZMENJEN NALOG v' . $version : 'Novi radni nalog') . ': ' . project_ref($project) . ' — preuzmi ga u aplikaciji.' . ($note !== '' ? " ($note)" : ''), (int) $project['id']);
    }
}

function project_archive(array $p, array $user): void
{
    q('UPDATE projects SET archived_at = NOW() WHERE id = ?', [$p['id']]);
    log_activity('project_archived', (int) $p['id'], null, ['code' => $p['code']]);
}

function project_restore(array $p, array $user): void
{
    q('UPDATE projects SET archived_at = NULL WHERE id = ?', [$p['id']]);
    log_activity('project_restored', (int) $p['id']);
}

/** Trajno brisanje — samo iz arhive. */
function project_delete(array $project): void
{
    $files = q_all('SELECT * FROM files WHERE project_id = ? AND task_id IS NULL', [$project['id']]);
    db()->beginTransaction();
    q('UPDATE files SET project_id = NULL WHERE project_id = ? AND task_id IS NOT NULL', [$project['id']]);
    q('UPDATE stoppages SET project_id = NULL WHERE project_id = ? AND task_id IS NOT NULL', [$project['id']]);
    q('UPDATE projects SET parent_project_id = NULL WHERE parent_project_id = ?', [$project['id']]);
    q('DELETE FROM projects WHERE id = ?', [$project['id']]);
    log_activity('project_deleted', (int) $project['id'], null, ['code' => $project['code'], 'customer' => $project['customer_name']]);
    db()->commit();
    foreach ($files as $f) {
        delete_stored($f);
    }
}

/** Postavlja ili menja sliku lokacije PRE (fajl je već poslat). */
function set_lokacija_photo(array $project, int $fileId, array $user): void
{
    $old = q_all("SELECT * FROM files WHERE project_id = ? AND kind = 'lokacija_pre'", [$project['id']]);
    if (!attach_temp_files([$fileId], (int) $project['id'], ['kind' => 'lokacija_pre'], (int) $user['id'])) {
        throw new RuleError('Slika nije pronađena — pošalji je ponovo.');
    }
    foreach ($old as $f) {
        q('DELETE FROM files WHERE id = ?', [$f['id']]);
        delete_stored($f);
    }
    log_activity('file_uploaded', (int) $project['id'], null, ['kind' => 'lokacija_pre', 'file' => $fileId]);
}

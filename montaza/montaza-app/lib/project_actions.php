<?php
declare(strict_types=1);

// Radnje nad projektima (vlasnik). Pravila se proveravaju u rules.php.

/**
 * Pravi projekat sa stavkama iz šablona, slikom lokacije i PDF nalogom.
 * Fajlovi se prvo snime na disk; ako baza pukne, brišu se.
 */
function create_project(array $in, array $user, ?array $lokacijaUpload, ?array $pdfUpload): int
{
    $stored = [];
    try {
        $lok = $lokacijaUpload ? $stored[] = store_image($lokacijaUpload) : null;
        $pdf = $pdfUpload ? $stored[] = store_pdf($pdfUpload) : null;

        db()->beginTransaction();
        $code = next_project_code();
        q(
            'INSERT INTO projects (code, type, parent_project_id, customer_name, phone, city, address, description,
                has_steel, install_week_start, deadline, payment_when, payment_amount, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $code, $in['type'], $in['type'] === 'reklamacija' ? $in['parent_project_id'] : null,
                $in['customer_name'], $in['phone'] ?: null, $in['city'] ?: null, $in['address'] ?: null,
                $in['description'] ?: null, (int) $in['has_steel'], $in['install_week_start'], $in['deadline'],
                $in['payment_when'], $in['payment_when'] === 'ne' ? null : $in['payment_amount'],
                $in['notes'] ?: null, $user['id'],
            ]
        );
        $pid = (int) db()->lastInsertId();

        // Stavke iz šablona. Za "započet": sve do izabrane stavke već je urađeno (imported, bez slika).
        $doneSort = 0;
        if ($in['type'] === 'zapocet' && $in['done_until'] !== '') {
            foreach (TEMPLATE_STD as [$kind, , $sort]) {
                if ($kind === $in['done_until']) {
                    $doneSort = $sort;
                }
            }
        }
        $itemIds = [];
        foreach (item_template($in['type'], $in['has_steel']) as [$kind, $resp, $sort]) {
            $imported = $sort <= $doneSort;
            q(
                'INSERT INTO project_items (project_id, kind, sort, responsible, due_date, status, completed_at, completed_by, imported)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $pid, $kind, $sort, $resp,
                    in_array($kind, ['montaza', 'servis'], true) ? $in['deadline'] : null,
                    $imported ? 'zavrseno' : 'ceka',
                    $imported ? date('Y-m-d H:i:s') : null,
                    $imported ? $user['id'] : null,
                    (int) $imported,
                ]
            );
            $itemIds[$kind] = (int) db()->lastInsertId();
        }

        if ($lok) {
            insert_file($pid, null, 'lokacija_pre', $lok, $user['id']);
        }
        if ($pdf && isset($itemIds['nalog'])) {
            insert_file($pid, $itemIds['nalog'], 'nalog_pdf', $pdf, $user['id']);
            // Pravilo 1: PDF zatvara stavku "nalog"
            q(
                "UPDATE project_items SET status = 'zavrseno', completed_at = COALESCE(completed_at, NOW()),
                    completed_by = COALESCE(completed_by, ?), imported = 0 WHERE id = ?",
                [$user['id'], $itemIds['nalog']]
            );
        }

        log_activity('project_created', $pid, null, [
            'code' => $code, 'type' => $in['type'], 'customer' => $in['customer_name'],
            'done_until' => $in['done_until'] ?: null,
        ]);
        db()->commit();
    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        foreach ($stored as $s) {
            delete_stored($s);
        }
        throw $ex;
    }

    rule_maybe_complete_project($pid);
    return $pid;
}

/** Izmena projekta. Tip se ne menja. Vraća listu poruka za korisnika (npr. šta nije moglo). */
function update_project(array $project, array $in, array $user, array $dueDates): array
{
    $pid = (int) $project['id'];
    $items = load_items($pid);
    $notes = [];

    $fields = [
        'customer_name'      => $in['customer_name'],
        'phone'              => $in['phone'] ?: null,
        'city'               => $in['city'] ?: null,
        'address'            => $in['address'] ?: null,
        'description'        => $in['description'] ?: null,
        'install_week_start' => $in['install_week_start'],
        'deadline'           => $in['deadline'],
        'notes'              => $in['notes'] ?: null,
    ];

    // Naplata se ne menja posle potvrde terena
    if ($project['payment_confirmed_at']) {
        if ($in['payment_when'] !== $project['payment_when'] || $in['payment_amount'] !== ($project['payment_amount'] ?? null)) {
            $notes[] = 'Naplata je već potvrđena — iznos i način naplate nisu menjani.';
        }
    } else {
        $fields['payment_when'] = $in['payment_when'];
        $fields['payment_amount'] = $in['payment_when'] === 'ne' ? null : $in['payment_amount'];
    }

    db()->beginTransaction();
    try {
        $changed = [];
        foreach ($fields as $k => $v) {
            $old = $project[$k];
            if ((string) $old !== (string) $v) {
                $changed[$k] = ['old' => $old, 'new' => $v];
            }
        }
        if ($changed) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
            q("UPDATE projects SET $set WHERE id = ?", array_merge(array_values($fields), [$pid]));
        }

        // Čelična konstrukcija: uključi/isključi dok stavka nije započeta
        if ($project['type'] !== 'reklamacija' && (bool) $project['has_steel'] !== $in['has_steel']) {
            if (!rule_can_toggle_steel($items)) {
                $notes[] = 'Čelična konstrukcija je već započeta — ne može se isključiti.';
            } else {
                if ($in['has_steel']) {
                    q("INSERT INTO project_items (project_id, kind, sort, responsible) VALUES (?, 'celik', 20, 'hala')", [$pid]);
                } else {
                    q("DELETE FROM project_items WHERE project_id = ? AND kind = 'celik' AND status = 'ceka'", [$pid]);
                }
                q('UPDATE projects SET has_steel = ? WHERE id = ?', [(int) $in['has_steel'], $pid]);
                $changed['has_steel'] = ['old' => (int) $project['has_steel'], 'new' => (int) $in['has_steel']];
            }
        }

        // Rokovi po stavkama
        foreach ($items as $it) {
            if (!array_key_exists($it['id'], $dueDates)) {
                continue;
            }
            $raw = trim((string) $dueDates[$it['id']]);
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
            $new = $d && $d->format('Y-m-d') === $raw ? $raw : null;
            if ($new !== $it['due_date']) {
                q('UPDATE project_items SET due_date = ? WHERE id = ?', [$new, $it['id']]);
                $changed['due_' . $it['kind']] = ['old' => $it['due_date'], 'new' => $new];
            }
        }

        if ($changed) {
            log_activity('project_updated', $pid, null, $changed);
        }
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
    return $notes;
}

/** Briše projekat, njegove stavke i fajlove (i sa diska). activity_log ostaje. */
function delete_project(array $project): void
{
    $files = load_files((int) $project['id']);
    db()->beginTransaction();
    // Zadaci vezani za projekat ostaju (bez veze), a njihove slike sa njima
    q('UPDATE files SET project_id = NULL WHERE project_id = ? AND task_id IS NOT NULL', [$project['id']]);
    q('UPDATE stoppages SET project_id = NULL WHERE project_id = ? AND task_id IS NOT NULL', [$project['id']]);
    q('UPDATE projects SET parent_project_id = NULL WHERE parent_project_id = ?', [$project['id']]);
    q('DELETE FROM projects WHERE id = ?', [$project['id']]);
    log_activity('project_deleted', (int) $project['id'], null, [
        'code' => $project['code'], 'customer' => $project['customer_name'], 'files' => count($files),
    ]);
    db()->commit();
    foreach ($files as $f) {
        delete_stored($f);
    }
}

/** Kači PDF nalog i zatvara stavku "nalog" (pravilo 1). */
function attach_nalog_pdf(array $project, array $upload, array $user): void
{
    $nalog = q_one("SELECT * FROM project_items WHERE project_id = ? AND kind = 'nalog'", [$project['id']]);
    if (!$nalog) {
        throw new UploadError('Ovaj projekat nema stavku nalog.');
    }
    $pdf = store_pdf($upload);
    try {
        db()->beginTransaction();
        $fid = insert_file((int) $project['id'], (int) $nalog['id'], 'nalog_pdf', $pdf, $user['id']);
        if ($nalog['status'] !== 'zavrseno' || $nalog['imported']) {
            q(
                "UPDATE project_items SET status = 'zavrseno', completed_at = NOW(), completed_by = ?, imported = 0 WHERE id = ?",
                [$user['id'], $nalog['id']]
            );
            log_activity('item_completed', (int) $project['id'], (int) $nalog['id'], ['kind' => 'nalog', 'file' => $fid]);
        } else {
            log_activity('file_uploaded', (int) $project['id'], (int) $nalog['id'], ['kind' => 'nalog_pdf', 'file' => $fid]);
        }
        db()->commit();
    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        delete_stored($pdf);
        throw $ex;
    }
    rule_maybe_complete_project((int) $project['id']);
}

/** Postavlja (ili menja) sliku lokacije PRE. */
function set_lokacija_photo(array $project, array $upload, array $user): void
{
    $img = store_image($upload);
    $old = q_all("SELECT * FROM files WHERE project_id = ? AND kind = 'lokacija_pre'", [$project['id']]);
    try {
        db()->beginTransaction();
        $fid = insert_file((int) $project['id'], null, 'lokacija_pre', $img, $user['id']);
        q("DELETE FROM files WHERE project_id = ? AND kind = 'lokacija_pre' AND id <> ?", [$project['id'], $fid]);
        log_activity('file_uploaded', (int) $project['id'], null, ['kind' => 'lokacija_pre', 'file' => $fid, 'replaced' => count($old)]);
        db()->commit();
    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        delete_stored($img);
        throw $ex;
    }
    foreach ($old as $f) {
        delete_stored($f);
    }
}

<?php
declare(strict_types=1);

// v3: sve izmene na projektu. Prava se proveravaju ovde, ne u stranama.

class RuleError extends RuntimeException
{
}

function parse_date($v): ?string
{
    $v = trim((string) $v);
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v ? $v : null;
}

function parse_time($v): ?string
{
    $v = trim((string) $v);
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v . ':00' : null;
}

function in_tx(callable $fn): mixed
{
    db()->beginTransaction();
    try {
        $r = $fn();
        db()->commit();
        return $r;
    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $ex;
    }
}

// ── Projekat ────────────────────────────────────────────────

function project_input(array $post): array
{
    return [
        'type'          => isset(TYPE_LABELS[$post['type'] ?? '']) ? $post['type'] : 'novi',
        'customer_name' => trim((string) ($post['customer_name'] ?? '')),
        'phone'         => trim((string) ($post['phone'] ?? '')),
        'city'          => trim((string) ($post['city'] ?? '')),
        'address'       => trim((string) ($post['address'] ?? '')),
        'works'         => array_slice(array_values(array_unique(array_filter(array_map(fn($w) => mb_substr(trim((string) $w), 0, 80), (array) ($post['works'] ?? []))))), 0, 20),
        'install_date'  => parse_date($post['install_date'] ?? ''),
        'install_raw'   => trim((string) ($post['install_date'] ?? '')),
        'install_time'  => parse_time($post['install_time'] ?? ''),
        'description'   => trim((string) ($post['description'] ?? '')),
        'parent_project_id' => (int) ($post['parent_project_id'] ?? 0) ?: null,
    ];
}

function project_validate(array $in): array
{
    $err = [];
    if ($in['customer_name'] === '') {
        $err['customer_name'] = 'Upiši ime kupca.';
    } elseif (mb_strlen($in['customer_name']) > 150) {
        $err['customer_name'] = 'Predugačko.';
    }
    if ($in['type'] === 'reklamacija' && $in['description'] === '') {
        $err['description'] = 'Opiši problem.';
    }
    if ($in['install_raw'] !== '' && !$in['install_date']) {
        $err['install_date'] = 'Neispravan datum.';
    }
    return $err;
}

/** Novi ili izmena. Vraća id. $nalogIds / $lokIds: već poslati fajlovi. */
function project_save(?array $project, array $in, array $user, array $nalogIds, array $lokIds, array $problemIds = []): int
{
    if ($user['role'] !== 'vlasnik') {
        throw new RuleError('Projekte otvara i menja vlasnik.');
    }
    $id = in_tx(function () use ($project, $in, $user, $nalogIds, $lokIds, $problemIds) {
        $cols = [$in['type'], $in['customer_name'], $in['phone'] ?: null, $in['city'] ?: null, $in['address'] ?: null,
                 (int) in_array('Čelik', $in['works'], true), $in['install_date'], $in['install_date'] ? $in['install_time'] : null, $in['description'] ?: null,
                 $in['type'] === 'reklamacija' ? $in['parent_project_id'] : null];
        if (!$project) {
            q('INSERT INTO projects (code, type, customer_name, phone, city, address, has_steel, install_date, install_time, description, parent_project_id, status, created_by)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'aktivan\', ?)', array_merge([next_project_code()], $cols, [$user['id']]));
            $id = (int) db()->lastInsertId();
            sync_items($id, $in['type'], $in['works']);
            log_activity('project_created', $id);
        } else {
            $id = (int) $project['id'];
            q('UPDATE projects SET type = ?, customer_name = ?, phone = ?, city = ?, address = ?, has_steel = ?, install_date = ?, install_time = ?, description = ?, parent_project_id = ? WHERE id = ?',
                array_merge($cols, [$id]));
            sync_items($id, $in['type'], $in['works']);
            log_activity('project_updated', $id);
        }
        if ($nalogIds && attach_temp_files($nalogIds, $id, ['kind' => 'nalog_pdf'], (int) $user['id'])) {
            item_mark_done_silent($id, 'nalog', $user);
            log_activity('nalog_uploaded', $id);
        }
        attach_temp_files($lokIds, $id, ['kind' => 'lokacija_pre'], (int) $user['id']);
        attach_temp_files($problemIds, $id, ['kind' => 'foto', 'purpose' => 'nedostatak'], (int) $user['id']);
        return $id;
    });

    $p = load_project($id);
    $when = install_when($p, false);
    if (!$project) {
        notify(['hala', 'teren'], 'Novi projekat: ' . project_ref($p) . ($when ? ' — montaža ' . $when : ''), $id);
    } elseif ((string) $project['install_date'] !== (string) $p['install_date'] || (string) $project['install_time'] !== (string) $p['install_time']) {
        log_activity('install_set', $id, null, ['from' => $project['install_date'], 'to' => $p['install_date']]);
        notify(['teren', 'hala'], ($p['install_date'] ? 'Montaža ' . ($project['install_date'] ? 'pomerena' : 'zakazana') . ': ' . project_ref($p) . ' — ' . $when : 'Montaža bez datuma: ' . project_ref($p)), $id);
    }
    return $id;
}

/** Brz upis dana i vremena montaže (vlasnik, sa strane projekta). */
function project_set_install(array $project, ?string $date, ?string $time, array $user): void
{
    if ($user['role'] !== 'vlasnik') {
        throw new RuleError('Datum montaže određuje vlasnik.');
    }
    q('UPDATE projects SET install_date = ?, install_time = ? WHERE id = ?', [$date, $date ? $time : null, $project['id']]);
    $p = load_project((int) $project['id']);
    log_activity('install_set', (int) $p['id'], null, ['from' => $project['install_date'], 'to' => $date]);
    notify(['teren', 'hala'], $date ? 'Montaža ' . ($project['install_date'] ? 'pomerena' : 'zakazana') . ': ' . project_ref($p) . ' — ' . install_when($p, false) : 'Montaža bez datuma: ' . project_ref($p), (int) $p['id']);
}

function project_delete(array $project, array $user): void
{
    if ($user['role'] !== 'vlasnik') {
        throw new RuleError('Samo vlasnik.');
    }
    $files = q_all('SELECT * FROM files WHERE project_id = ?', [$project['id']]);
    q('UPDATE projects SET parent_project_id = NULL WHERE parent_project_id = ?', [$project['id']]);
    q('DELETE FROM projects WHERE id = ?', [$project['id']]);
    log_activity('project_deleted', null, null, ['code' => $project['code'], 'customer' => $project['customer_name']]);
    foreach ($files as $f) {
        delete_stored($f);
    }
}

function nalog_upload(array $project, int $fileId, array $user): void
{
    if ($user['role'] !== 'vlasnik') {
        throw new RuleError('Nalog postavlja vlasnik.');
    }
    if (!attach_temp_files([$fileId], (int) $project['id'], ['kind' => 'nalog_pdf'], (int) $user['id'])) {
        throw new RuleError('PDF nije stigao — pokušaj ponovo.');
    }
    item_mark_done_silent((int) $project['id'], 'nalog', $user);
    log_activity('nalog_uploaded', (int) $project['id']);
    notify(['hala'], 'Nalog (PDF): ' . project_ref($project), (int) $project['id']);
}

function photos_add(array $project, array $ids, array $user): int
{
    $n = attach_temp_files($ids, (int) $project['id'], ['kind' => 'foto'], (int) $user['id']);
    if ($n) {
        log_activity('photos_added', (int) $project['id'], null, ['n' => $n]);
    }
    return $n;
}

// ── Stavke ──────────────────────────────────────────────────

function lock_item(int $id): array
{
    $i = q_one('SELECT * FROM project_items WHERE id = ? FOR UPDATE', [$id]);
    if (!$i) {
        throw new RuleError('Stavka ne postoji.');
    }
    return $i;
}

function item_mark_done_silent(int $projectId, string $kind, array $user): void
{
    q("UPDATE project_items SET status = 'zavrseno', started_at = COALESCE(started_at, NOW()), completed_at = NOW(), completed_by = ?
       WHERE project_id = ? AND kind = ? AND status <> 'zavrseno'", [$user['id'], $projectId, $kind]);
}

function item_start(int $id, array $user): void
{
    $it = in_tx(function () use ($id, $user) {
        $it = lock_item($id);
        if (!can_act_item($user, $it)) {
            throw new RuleError('Ovu stavku vodi ' . ['vlasnik' => 'vlasnik', 'hala' => 'hala', 'teren' => 'teren'][$it['responsible']] . '.');
        }
        if ($it['status'] === 'zavrseno') {
            throw new RuleError('Stavka je već završena.');
        }
        q("UPDATE project_items SET status = 'u_toku', started_at = COALESCE(started_at, NOW()) WHERE id = ?", [$id]);
        return $it;
    });
    log_activity('item_started', (int) $it['project_id'], $id, ['kind' => $it['kind'], 'label' => item_label($it)]);
}

/** Završetak stavke sa slikama ($fotoIds: već poslate). */
function item_complete(int $id, array $user, string $note, array $fotoIds): void
{
    [$it, $p] = in_tx(function () use ($id, $user, $note, $fotoIds) {
        $it = lock_item($id);
        $p = load_project((int) $it['project_id']);
        if (!can_act_item($user, $it)) {
            throw new RuleError('Nemaš pravo da završiš ovu stavku.');
        }
        if ($it['status'] === 'zavrseno') {
            throw new RuleError('Stavka je već završena.');
        }
        $kind = item_is_field($it['kind']) ? ($it['kind'] === 'dijagnoza' ? 'foto' : 'posle') : 'foto';
        attach_temp_files($fotoIds, (int) $p['id'], ['kind' => $kind, 'item_id' => $id], (int) $user['id']);
        $have = (int) q_val('SELECT COUNT(*) FROM files WHERE item_id = ?', [$id]);
        $min = item_min_photos($it);
        if ($have < $min) {
            throw new RuleError('Potrebno je bar ' . $min . ($min === 1 ? ' slika' : ' slike') . ' (sada ' . $have . ').');
        }
        q("UPDATE project_items SET status = 'zavrseno', started_at = COALESCE(started_at, NOW()), completed_at = NOW(), completed_by = ?, note = ? WHERE id = ?",
            [$user['id'], $note !== '' ? mb_substr($note, 0, 2000) : null, $id]);
        q('UPDATE stoppages SET resolved_at = NOW(), resolved_by = ? WHERE item_id = ? AND resolved_at IS NULL', [$user['id'], $id]);
        log_activity('item_completed', (int) $p['id'], $id, ['kind' => $it['kind'], 'label' => item_label($it), 'note' => $note]);
        $open = (int) q_val("SELECT COUNT(*) FROM project_items WHERE project_id = ? AND status <> 'zavrseno'", [$p['id']]);
        if ($open === 0) {
            q("UPDATE projects SET status = 'zavrsen', completed_at = NOW() WHERE id = ?", [$p['id']]);
            q('UPDATE stoppages SET resolved_at = NOW(), resolved_by = ? WHERE project_id = ? AND resolved_at IS NULL', [$user['id'], $p['id']]);
            log_activity('project_completed', (int) $p['id']);
        }
        return [$it, $p];
    });

    $label = item_label($it);
    $b = bundle_one((int) $p['id']);
    if ($b['p']['status'] === 'zavrsen') {
        notify(['vlasnik', 'hala', 'teren'], 'ZAVRŠENO: ' . project_ref($p) . ' — ' . $user['name'] . ($note !== '' ? " ($note)" : ''), (int) $p['id']);
    } elseif (!item_is_field($it['kind']) && ready_for_field($b['items'])) {
        notify(['teren', 'vlasnik'], 'SPREMNO za montažu: ' . project_ref($p) . ($p['install_date'] ? ' — ' . install_when($p, false) : ' — datum još nije određen'), (int) $p['id']);
    } else {
        notify(['vlasnik'], $label . ' završeno: ' . project_ref($p) . ' — ' . $user['name'], (int) $p['id']);
    }
}

/** Vlasnik vraća stavku na „čeka" (greška pri označavanju). */
function item_reset(int $id, array $user): void
{
    if ($user['role'] !== 'vlasnik') {
        throw new RuleError('Samo vlasnik.');
    }
    $it = q_one('SELECT * FROM project_items WHERE id = ?', [$id]) ?? throw new RuleError('Stavka ne postoji.');
    q("UPDATE project_items SET status = 'ceka', started_at = NULL, completed_at = NULL, completed_by = NULL WHERE id = ?", [$id]);
    q("UPDATE projects SET status = 'aktivan', completed_at = NULL WHERE id = ? AND status = 'zavrsen'", [$it['project_id']]);
    log_activity('item_reset', (int) $it['project_id'], $id, ['kind' => $it['kind'], 'label' => item_label($it)]);
}

// ── Zastoj (na nivou projekta) ──────────────────────────────

function stop_open(array $project, string $reason, string $note, array $user): void
{
    if (!in_array($reason, REASONS_NEW, true)) {
        throw new RuleError('Izaberi razlog.');
    }
    if (trim($note) === '') {
        throw new RuleError('Napiši ukratko šta je problem.');
    }
    if (stops_for([(int) $project['id']])) {
        throw new RuleError('Zastoj je već prijavljen — dopiši u komentar.');
    }
    $cur = current_item(items_for([(int) $project['id']])[(int) $project['id']] ?? []);
    q('INSERT INTO stoppages (project_id, item_id, reason, note, created_by) VALUES (?, ?, ?, ?, ?)',
        [$project['id'], $cur['id'] ?? null, $reason, mb_substr(trim($note), 0, 500), $user['id']]);
    log_activity('stoppage', (int) $project['id'], null, ['reason' => $reason, 'note' => $note]);
    notify(['vlasnik', 'hala', 'teren'], 'ZASTOJ: ' . project_ref($project) . ' — ' . trim($note) . ' (' . $user['name'] . ')', (int) $project['id']);
}

function stop_resolve(array $project, array $user): void
{
    $s = stops_for([(int) $project['id']])[(int) $project['id']] ?? throw new RuleError('Nema otvorenog zastoja.');
    q('UPDATE stoppages SET resolved_at = NOW(), resolved_by = ? WHERE id = ?', [$user['id'], $s['id']]);
    log_activity('stoppage_resolved', (int) $project['id'], null, ['note' => $s['note']]);
    notify(['vlasnik', 'hala', 'teren'], 'Rešen zastoj: ' . project_ref($project) . ' — ' . $s['note'] . ' (' . $user['name'] . ')', (int) $project['id']);
}

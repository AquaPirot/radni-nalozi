<?php
declare(strict_types=1);

// Zadaci van projekata (spec tačka 15).
// Vlasnik vidi sve; ostali vide zadatke koje su dobili ili zadali.

const TASK_STATUS_LABELS = [
    'ceka'     => 'Čeka',
    'u_toku'   => 'U toku',
    'zavrseno' => 'Završeno',
    'zastoj'   => 'Zastoj',
];

function task_visibility_sql(array $user): array
{
    if ($user['role'] === 'vlasnik') {
        return ['1=1', []];
    }
    return ['(t.assigned_to = ? OR t.created_by = ?)', [$user['id'], $user['id']]];
}

function load_task(int $id): ?array
{
    return q_one(
        'SELECT t.*, a.name AS assigned_name, c.name AS created_name, d.name AS completed_name,
                p.customer_name AS project_name, p.code AS project_code
         FROM tasks t
         JOIN users a ON a.id = t.assigned_to
         LEFT JOIN users c ON c.id = t.created_by
         LEFT JOIN users d ON d.id = t.completed_by
         LEFT JOIN projects p ON p.id = t.project_id
         WHERE t.id = ?',
        [$id]
    );
}

function can_view_task(array $user, array $task): bool
{
    return $user['role'] === 'vlasnik'
        || (int) $task['assigned_to'] === (int) $user['id']
        || (int) $task['created_by'] === (int) $user['id'];
}

/** Završava / prijavljuje zastoj: onaj kome je dodeljen, ili vlasnik. */
function can_act_task(array $user, array $task): bool
{
    return $task['status'] !== 'zavrseno'
        && ($user['role'] === 'vlasnik' || (int) $task['assigned_to'] === (int) $user['id']);
}

/** Menja / briše: onaj ko je zadao, ili vlasnik. */
function can_edit_task(array $user, array $task): bool
{
    return $user['role'] === 'vlasnik' || (int) $task['created_by'] === (int) $user['id'];
}

/** Kome korisnik sme da zada zadatak: vlasnik svima; ostali sebi ili vlasniku. */
function assignable_users(array $user): array
{
    $all = q_all("SELECT id, name, role FROM users WHERE active = 1 ORDER BY FIELD(role, 'vlasnik', 'hala', 'teren'), name");
    if ($user['role'] === 'vlasnik') {
        return $all;
    }
    return array_values(array_filter($all, fn($u) => (int) $u['id'] === (int) $user['id'] || $u['role'] === 'vlasnik'));
}

/** Broj otvorenih zadataka dodeljenih korisniku (bedž u meniju). */
function my_open_tasks(int $userId): int
{
    return (int) q_val("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status <> 'zavrseno'", [$userId]);
}

/** [tekst, klasa] statusne linije zadatka */
function task_status_line(array $t): array
{
    if ($t['status'] === 'zavrseno') {
        return ['Završeno ' . fmt_dm(substr((string) $t['completed_at'], 0, 10)) . ($t['completed_name'] ? ' · ' . $t['completed_name'] : ''), 'ok'];
    }
    if ($t['status'] === 'zastoj') {
        $s = q_one('SELECT reason, note FROM stoppages WHERE task_id = ? AND resolved_at IS NULL ORDER BY id DESC LIMIT 1', [$t['id']]);
        return ['Zastoj' . ($s ? ': ' . ($s['note'] ?: mb_strtolower(REASON_LABELS[$s['reason']])) : ''), 'warn'];
    }
    $late = days_late($t['due_date']);
    if ($late > 0) {
        return ['Kasni ' . $late . ' ' . ($late === 1 ? 'dan' : 'dana'), 'warn'];
    }
    return [$t['status'] === 'u_toku' ? 'U toku' : 'Čeka', $t['status'] === 'u_toku' ? 'live' : 'idle'];
}

function task_input(array $post): array
{
    $due = trim((string) ($post['due_date'] ?? ''));
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $due);
    return [
        'title'          => trim((string) ($post['title'] ?? '')),
        'description'    => trim((string) ($post['description'] ?? '')),
        'assigned_to'    => (int) ($post['assigned_to'] ?? 0),
        'project_id'     => (int) ($post['project_id'] ?? 0) ?: null,
        'due_date'       => $d && $d->format('Y-m-d') === $due ? $due : null,
        'due_raw'        => $due,
        'priority'       => ($post['priority'] ?? '') === 'hitno' ? 'hitno' : 'normal',
        'requires_photo' => !empty($post['requires_photo']),
    ];
}

function task_validate(array $in, array $user): array
{
    $err = [];
    if ($in['title'] === '') {
        $err['title'] = 'Upiši šta treba uraditi.';
    } elseif (mb_strlen($in['title']) > 200) {
        $err['title'] = 'Predugačko — detalje stavi u opis.';
    }
    if (!in_array($in['assigned_to'], array_map('intval', array_column(assignable_users($user), 'id')), true)) {
        $err['assigned_to'] = 'Izaberi kome je zadatak.';
    }
    if ($in['due_raw'] !== '' && $in['due_date'] === null) {
        $err['due_date'] = 'Neispravan datum.';
    }
    if ($in['project_id'] && !load_project($in['project_id'])) {
        $err['project_id'] = 'Projekat ne postoji.';
    }
    return $err;
}

function task_create(array $in, array $user): int
{
    q(
        'INSERT INTO tasks (title, description, assigned_to, created_by, project_id, due_date, priority, requires_photo)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$in['title'], $in['description'] ?: null, $in['assigned_to'], $user['id'], $in['project_id'],
         $in['due_date'], $in['priority'], (int) $in['requires_photo']]
    );
    $id = (int) db()->lastInsertId();
    log_activity('task_created', $in['project_id'], null, ['task_id' => $id, 'title' => $in['title'], 'assigned_to' => $in['assigned_to']]);
    if ($in['assigned_to'] !== (int) $user['id']) {
        notify_user($in['assigned_to'], ($in['priority'] === 'hitno' ? 'HITNO — ' : '') . 'Novi zadatak od ' . $user['name'] . ': '
            . $in['title'] . ($in['due_date'] ? ' (rok ' . fmt_dm($in['due_date']) . ')' : ''), url('zadatak', ['id' => $id]));
    }
    return $id;
}

function task_update(array $task, array $in, array $user): void
{
    q(
        'UPDATE tasks SET title = ?, description = ?, assigned_to = ?, project_id = ?, due_date = ?, priority = ?, requires_photo = ? WHERE id = ?',
        [$in['title'], $in['description'] ?: null, $in['assigned_to'], $in['project_id'], $in['due_date'],
         $in['priority'], (int) $in['requires_photo'], $task['id']]
    );
    log_activity('task_updated', $in['project_id'], null, ['task_id' => (int) $task['id']]);
    if ($in['assigned_to'] !== (int) $task['assigned_to'] && $in['assigned_to'] !== (int) $user['id']) {
        notify_user($in['assigned_to'], 'Dodeljen ti je zadatak: ' . $in['title'], url('zadatak', ['id' => $task['id']]));
    }
}

function task_delete(array $task): void
{
    $files = q_all('SELECT * FROM files WHERE task_id = ?', [$task['id']]);
    q('DELETE FROM tasks WHERE id = ?', [$task['id']]);
    log_activity('task_deleted', $task['project_id'] ? (int) $task['project_id'] : null, null, ['task_id' => (int) $task['id'], 'title' => $task['title']]);
    foreach ($files as $f) {
        delete_stored($f);
    }
}

function lock_task(int $id): array
{
    $t = q_one('SELECT * FROM tasks WHERE id = ? FOR UPDATE', [$id]);
    if (!$t) {
        throw new RuleError('Zadatak ne postoji.');
    }
    return $t;
}

function task_start(int $id, array $user): void
{
    db()->beginTransaction();
    try {
        $t = lock_task($id);
        if (!can_act_task($user, $t)) {
            throw new RuleError('Nemaš pravo ili je zadatak već završen.');
        }
        $resume = $t['status'] === 'zastoj';
        q("UPDATE tasks SET status = 'u_toku', started_at = COALESCE(started_at, NOW()) WHERE id = ?", [$id]);
        if ($resume) {
            q('UPDATE stoppages SET resolved_at = NOW(), resolved_by = ? WHERE task_id = ? AND resolved_at IS NULL', [$user['id'], $id]);
        }
        log_activity($resume ? 'task_resumed' : 'task_started', $t['project_id'] ? (int) $t['project_id'] : null, null, ['task_id' => $id]);
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
}

/** Završetak zadatka; slika je obavezna samo ako je zadatak tako zadat. */
function task_complete(int $id, array $user, string $note, array $fotos): void
{
    $stored = [];
    try {
        foreach ($fotos as $f) {
            $stored[] = store_image($f);
        }
        db()->beginTransaction();
        $t = lock_task($id);
        if (!can_act_task($user, $t)) {
            throw new RuleError('Nemaš pravo ili je zadatak već završen.');
        }
        $have = (int) q_val('SELECT COUNT(*) FROM files WHERE task_id = ?', [$id]) + count($stored);
        if ($t['requires_photo'] && $have < 1) {
            throw new RuleError('Za ovaj zadatak je potrebna slika.', ['slika']);
        }
        foreach ($stored as $s) {
            q(
                "INSERT INTO files (project_id, task_id, kind, path, thumb_path, mime, width, height, size, uploaded_by)
                 VALUES (?, ?, 'foto', ?, ?, ?, ?, ?, ?, ?)",
                [$t['project_id'], $id, $s['path'], $s['thumb_path'], $s['mime'], $s['width'], $s['height'], $s['size'], $user['id']]
            );
        }
        q(
            "UPDATE tasks SET status = 'zavrseno', started_at = COALESCE(started_at, created_at), completed_at = NOW(),
                completed_by = ?, note = ? WHERE id = ?",
            [$user['id'], $note !== '' ? mb_substr($note, 0, 2000) : null, $id]
        );
        q('UPDATE stoppages SET resolved_at = NOW(), resolved_by = ? WHERE task_id = ? AND resolved_at IS NULL', [$user['id'], $id]);
        log_activity('task_completed', $t['project_id'] ? (int) $t['project_id'] : null, null, ['task_id' => $id, 'photos' => count($stored)]);
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
    if ($t['created_by'] && (int) $t['created_by'] !== (int) $user['id']) {
        notify_user((int) $t['created_by'], 'Završeno: ' . $t['title'] . ' — ' . $user['name'] . ($note !== '' ? " ($note)" : ''), url('zadatak', ['id' => $id]));
    }
}

function task_stoppage(int $id, array $user, string $reason, string $note): void
{
    if (!isset(REASON_LABELS[$reason])) {
        throw new RuleError('Izaberi razlog zastoja.');
    }
    db()->beginTransaction();
    try {
        $t = lock_task($id);
        if (!can_act_task($user, $t) || $t['status'] === 'zastoj') {
            throw new RuleError('Zastoj nije moguće prijaviti.');
        }
        q("UPDATE tasks SET status = 'zastoj' WHERE id = ?", [$id]);
        q(
            'INSERT INTO stoppages (project_id, task_id, reason, note, created_by) VALUES (?, ?, ?, ?, ?)',
            [$t['project_id'], $id, $reason, $note !== '' ? mb_substr($note, 0, 500) : null, $user['id']]
        );
        log_activity('task_stoppage', $t['project_id'] ? (int) $t['project_id'] : null, null, ['task_id' => $id, 'reason' => $reason, 'note' => $note]);
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
    $owners = array_map('intval', array_column(q_all("SELECT id FROM users WHERE role = 'vlasnik' AND active = 1"), 'id'));
    $to = array_unique(array_filter([(int) $t['created_by'], ...$owners]));
    foreach ($to as $uid) {
        if ($uid !== (int) $user['id']) {
            notify_user($uid, 'ZASTOJ na zadatku „' . $t['title'] . '" — ' . REASON_LABELS[$reason] . ($note !== '' ? " ($note)" : '') . ' · ' . $user['name'], url('zadatak', ['id' => $id]));
        }
    }
}

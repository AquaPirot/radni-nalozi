<?php
declare(strict_types=1);

// Komentari na projektu: svi vide, svi pišu, ostali dobijaju obaveštenje.

function project_comments(int $projectId): array
{
    return q_all('SELECT c.*, u.name AS user_name FROM project_comments c LEFT JOIN users u ON u.id = c.user_id
                  WHERE c.project_id = ? ORDER BY c.created_at, c.id', [$projectId]);
}

function project_comment_count(array $projectIds): array
{
    if (!$projectIds) {
        return [];
    }
    $out = [];
    foreach (q_all('SELECT project_id, COUNT(*) AS n FROM project_comments WHERE project_id IN (' . in_list($projectIds) . ') GROUP BY project_id', array_values($projectIds)) as $r) {
        $out[(int) $r['project_id']] = (int) $r['n'];
    }
    return $out;
}

function project_comment_add(array $project, array $user, string $body, ?int $fileId): void
{
    $body = trim($body);
    if ($fileId && !attach_temp_files([$fileId], (int) $project['id'], ['kind' => 'foto'], (int) $user['id'])) {
        $fileId = null;
    }
    if ($body === '' && !$fileId) {
        throw new RuleError('Upiši komentar ili dodaj sliku.');
    }
    q('INSERT INTO project_comments (project_id, user_id, body, file_id) VALUES (?, ?, ?, ?)',
        [$project['id'], $user['id'], $body !== '' ? mb_substr($body, 0, 2000) : null, $fileId]);
    $text = $user['name'] . ' — ' . project_ref($project) . ': ' . ($body !== '' ? mb_strimwidth($body, 0, 160, '…') : '(slika)');
    foreach (q_all('SELECT id FROM users WHERE active = 1 AND id <> ?', [$user['id']]) as $u) {
        notify_user((int) $u['id'], $text, url('projekat', ['id' => $project['id']]) . '#komentari');
    }
}

/** Poslednji komentari drugih (projekti + zadaci) za Danas. */
function recent_comments(int $exceptUserId, int $days, int $limit): array
{
    $rows = q_all(
        "SELECT * FROM (
            SELECT c.created_at, c.body, c.file_id, u.name AS user_name, p.customer_name AS what, p.city, c.project_id AS pid, NULL AS tid
              FROM project_comments c JOIN projects p ON p.id = c.project_id LEFT JOIN users u ON u.id = c.user_id
             WHERE c.user_id <> ? AND c.created_at > NOW() - INTERVAL ? DAY
            UNION ALL
            SELECT c.created_at, c.body, c.file_id, u.name, t.title, NULL, NULL, c.task_id
              FROM task_comments c JOIN tasks t ON t.id = c.task_id LEFT JOIN users u ON u.id = c.user_id
             WHERE c.kind = 'komentar' AND c.user_id <> ? AND c.created_at > NOW() - INTERVAL ? DAY
         ) x ORDER BY created_at DESC LIMIT " . max(1, $limit),
        [$exceptUserId, $days, $exceptUserId, $days]
    );
    return array_map(fn($r) => $r + [
        'where' => $r['pid'] ? $r['what'] . ($r['city'] ? ', ' . $r['city'] : '') : 'zadatak „' . $r['what'] . '"',
        'url'   => $r['pid'] ? url('projekat', ['id' => $r['pid']]) . '#komentari' : url('zadatak', ['id' => $r['tid']]),
    ], $rows);
}

/** „pre 5 min", „pre 2 h", „juče 14:05", „3.11. 09:10" */
function fmt_ago(string $dt): string
{
    $t = strtotime($dt);
    $d = time() - $t;
    if ($d < 60) {
        return 'upravo';
    }
    if ($d < 3600) {
        return 'pre ' . (int) floor($d / 60) . ' min';
    }
    if (date('Y-m-d', $t) === date('Y-m-d')) {
        return 'danas ' . date('H:i', $t);
    }
    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('-1 day'))) {
        return 'juče ' . date('H:i', $t);
    }
    return date('j.n. H:i', $t);
}

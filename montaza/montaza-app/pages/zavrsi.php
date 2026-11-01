<?php
declare(strict_types=1);

// Završi stavku (?p=zavrsi&item=…) — slike, štikle, napomena. Pravila proverava server (rules.php).

$item = q_one('SELECT * FROM project_items WHERE id = ?', [(int) ($_GET['item'] ?? 0)]);
$project = $item ? load_project((int) $item['project_id']) : null;
if (!$item || !$project || !can_view_project($user, (int) $project['id'])) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'projekti']);
    return;
}
$back = url('projekat', ['id' => $project['id']]);
if ($item['kind'] === 'nalog' || !can_act_item($user, $project, $item)) {
    redirect($back);
}

$error = null;
$missing = [];
$note = '';
if (is_post()) {
    csrf_check();
    $note = trim((string) ($_POST['note'] ?? ''));
    $paid = !empty($_POST['paid']) ? parse_amount((string) ($_POST['paid_amount'] ?? '')) : null;
    if (!empty($_POST['paid']) && ($paid === null || (float) $paid <= 0)) {
        $error = 'Unesi iznos koji je naplaćen.';
    } else {
        try {
            $r = item_complete((int) $item['id'], $user, [
                'note'        => mb_substr($note, 0, 2000),
                'paid_amount' => $paid,
                'video'       => !empty($_POST['video']),
                'handover'    => !empty($_POST['handover']),
            ], uploaded_files('posle')[0] ?? null, uploaded_files('fotos'));
            flash('ok', ITEM_LABELS[$item['kind']] . ' — završeno.' . ($r['project_completed'] ? ' Projekat je završen.' : ''));
            redirect($back);
        } catch (RuleError | UploadError $ex) {
            $error = $ex->getMessage();
            $missing = $ex instanceof RuleError ? $ex->missing : [];
        }
    }
    $error .= ' Slike treba ponovo izabrati.';
}

$items = load_items((int) $project['id']);
$files = q_all("SELECT * FROM files WHERE item_id = ? AND kind IN ('posle','foto') ORDER BY id", [$item['id']]);
$lokacija = q_one("SELECT * FROM files WHERE project_id = ? AND kind = 'lokacija_pre' ORDER BY id DESC LIMIT 1", [$project['id']]);

$isMontaza = $item['kind'] === 'montaza';
view('zavrsi', [
    'title'    => $isMontaza ? 'Montaža završena' : ITEM_LABELS[$item['kind']] . ' — završi',
    'eyebrow'  => mb_strtoupper(project_ref($project)),
    'tab'      => 'projekti',
    'focus'    => true,
    'user'     => $user,
    'project'  => $project,
    'item'     => $item,
    'files'    => $files,
    'lokacija' => $lokacija,
    'warning'  => rule_montaza_warning($items, $item),
    'error'    => $error,
    'note'     => $note,
    'topExtra' => '<a class="icon-btn" href="' . e($back) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

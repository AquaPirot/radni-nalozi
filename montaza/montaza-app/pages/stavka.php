<?php
declare(strict_types=1);

// Stavka: završetak sa slikama (hala: čelik, aluminijum…; teren: montaža/servis) ili pregled završene.

$it = q_one('SELECT * FROM project_items WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
$p = $it ? load_project((int) $it['project_id']) : null;
if (!$it || !$p) {
    http_response_code(404);
    view('404', ['title' => 'Nije pronađeno', 'tab' => 'projekti']);
    return;
}
$back = url('projekat', ['id' => $p['id']]);
$error = null;
$note = trim((string) ($_POST['note'] ?? ''));
if (is_post()) {
    csrf_check();
    if (!once_check()) {
        redirect($back);
    }
    try {
        item_complete((int) $it['id'], $user, $note, posted_ids('foto_ids'));
        flash('ok', ITEM_LABELS[$it['kind']] . ' — završeno.');
        redirect($back);
    } catch (RuleError $ex) {
        $error = $ex->getMessage();
    }
}
$photos = q_all('SELECT * FROM files WHERE item_id = ? AND pending = 0 ORDER BY id', [$it['id']]);
$min = $it['kind'] === 'nalog' ? 0 : (int) setting(item_is_field($it['kind']) ? 'min_photos_montaza' : 'min_photos_hala', item_is_field($it['kind']) ? '3' : '1');

view('stavka', [
    'title'    => ITEM_LABELS[$it['kind']],
    'eyebrow'  => mb_strtoupper($p['customer_name'] . ($p['city'] ? ', ' . $p['city'] : '')),
    'tab'      => 'projekti',
    'focus'    => true,
    'user'     => $user,
    'it'       => $it,
    'p'        => $p,
    'photos'   => $photos,
    'min'      => max(0, $min - count($photos)),
    'error'    => $error,
    'note'     => $note,
    'topExtra' => '<a class="icon-btn" href="' . e($back) . '" aria-label="Zatvori">' . icon('close') . '</a>',
]);

<?php
declare(strict_types=1);

// Kratke akcije (dugmad): POST action=…, uvek CSRF + jednokratni token (protiv duplog slanja).
// Prava se proveravaju u funkcijama (lib/*), ne ovde.

require dirname(__DIR__) . '/_init.php';
session_boot();

if (!is_post()) {
    http_response_code(405);
    exit;
}
csrf_check();
$user = require_login();

// Povratak: samo lokalna adresa
$back = (string) ($_POST['back'] ?? '');
if ($back === '' || !str_starts_with($back, '/') || str_starts_with($back, '//')) {
    $back = url();
} else {
    $back = rtrim((string) config('base_url', ''), '/') . $back;
}
if (!once_check()) {
    redirect($back);
}

$id = (int) ($_POST['id'] ?? 0);
$owner = fn() => $user['role'] === 'vlasnik' ? true : throw new RuleError('Samo vlasnik.');
$project = fn() => load_project($id) ?? throw new RuleError('Projekat ne postoji.');

try {
    switch ((string) ($_POST['action'] ?? '')) {
        // ── Projekat ──
        case 'release':
            $owner();
            $p = $project();
            $b = bundle_one($id);
            $in = project_input(['work_type' => $p['work_type'], 'intake' => $p['intake'], 'customer_name' => $p['customer_name'], 'city' => $p['city'],
                'deadline' => $p['deadline'], 'payment_when' => $p['payment_when'], 'payment_amount' => $p['payment_amount'], 'payment_collector' => $p['payment_collector'],
                'systems' => array_map(fn($s) => ['id' => $s['id'], 'type_id' => $s['system_type_id'], 'qty' => $s['qty'], 'width' => $s['width'], 'note' => $s['note']], $b['systems'])]);
            $hasLok = (bool) q_val("SELECT 1 FROM files WHERE project_id = ? AND kind = 'lokacija_pre'", [$id]);
            if ($err = validate_project($in, true, $hasLok)) {
                throw new RuleError('Pre puštanja u realizaciju fali: ' . implode(' ', $err));
            }
            project_release($id, $in, $user);
            flash('ok', 'Projekat je pušten u realizaciju.');
            break;
        case 'archive':
            $owner();
            project_archive($project(), $user);
            flash('ok', 'Projekat je arhiviran. Vraća se iz filtera „Arhiva".');
            $back = url('projekti');
            break;
        case 'restore':
            $owner();
            project_restore($project(), $user);
            flash('ok', 'Projekat je vraćen iz arhive.');
            break;
        case 'delete':
            $owner();
            $p = $project();
            if (!$p['archived_at']) {
                throw new RuleError('Trajno se brišu samo arhivirani projekti.');
            }
            project_delete($p);
            flash('ok', 'Projekat ' . $p['code'] . ' je trajno obrisan.');
            $back = url('projekti', ['f' => 'arhiva']);
            break;
        case 'lokacija':
            $owner();
            set_lokacija_photo($project(), posted_ids('file_id')[0] ?? 0, $user);
            flash('ok', 'Slika lokacije je sačuvana.');
            break;
        case 'order_issue':
            $owner();
            $fid = posted_ids('file_id')[0] ?? 0;
            if (!$fid) {
                throw new RuleError('Izaberi PDF naloga.');
            }
            order_issue_from_file($project(), $fid, $user, trim((string) ($_POST['note'] ?? '')));
            flash('ok', 'Nalog je izdat — hala dobija obaveštenje.');
            break;
        case 'order_accept':
            order_accept($id, $user);
            flash('ok', 'Nalog je preuzet.');
            break;

        // ── Proizvodnja ──
        case 'phase_start':
            phase_start($id, $user);
            flash('ok', 'Faza je u radu.');
            break;
        case 'phase_assign':
            phase_assign($id, (int) ($_POST['assignee_id'] ?? 0) ?: null, parse_date($_POST['due_date'] ?? ''), $user);
            flash('ok', 'Sačuvano.');
            break;

        // ── Teren ──
        case 'visit_start':
            $paid = parse_amount((string) ($_POST['paid'] ?? ''));
            visit_start($id, $user, $paid !== null ? (float) $paid : null, (string) ($_POST['method'] ?? 'gotovina'));
            flash('ok', 'Počelo — srećan rad.');
            break;
        case 'visit_cancel':
            $v = load_visit($id) ?? throw new RuleError('Termin ne postoji.');
            visit_cancel($v, trim((string) ($_POST['reason'] ?? '')), $user);
            flash('ok', 'Termin je otkazan.');
            break;

        // ── Zastoj ──
        case 'stoppage_resolve':
            stoppage_resolve($id, $user, trim((string) ($_POST['note'] ?? '')));
            flash('ok', 'Zastoj je rešen.');
            break;

        // ── Naplata ──
        case 'payment_add':
            $p = $project();
            if (!can_see_payment($user)) {
                throw new RuleError('Nemaš pravo.');
            }
            $amount = parse_amount((string) ($_POST['amount'] ?? ''));
            if ($amount === null) {
                throw new RuleError('Unesi iznos.');
            }
            payment_add($p, (float) $amount, (string) ($_POST['method'] ?? 'gotovina'), $user, null, trim((string) ($_POST['note'] ?? '')));
            flash('ok', 'Uplata je evidentirana.');
            break;
        case 'cash_handover':
            $n = payments_handover(posted_ids('ids'), $user);
            flash('ok', "Potvrđeno: primljeno $n " . ($n === 1 ? 'uplata' : 'uplate') . '.');
            break;

        // ── Zadaci ──
        case 'task_done':
            $t = load_task($id) ?? throw new RuleError('Zadatak ne postoji.');
            if ($t['requires_photo']) {
                redirect(url('zadatak_zavrsi', ['id' => $id]));
            }
            task_complete($id, $user, trim((string) ($_POST['note'] ?? '')), []);
            flash('ok', 'Završeno: ' . $t['title']);
            break;
        case 'task_start':
            task_start($id, $user);
            flash('ok', 'Zadatak je u radu.');
            break;
        case 'task_accept':
            task_accept($id, $user);
            flash('ok', 'Preuzeto.');
            break;
        case 'task_reopen':
            task_reopen($id, $user, (string) ($_POST['reason'] ?? ''));
            flash('ok', 'Zadatak je ponovo otvoren.');
            break;
        case 'task_comment':
            $t = load_task($id);
            if (!$t || !can_view_task($user, $t)) {
                throw new RuleError('Zadatak ne postoji.');
            }
            $body = trim((string) ($_POST['body'] ?? ''));
            $fid = posted_ids('file_id')[0] ?? null;
            if ($body === '' && !$fid) {
                throw new RuleError('Upiši komentar ili dodaj sliku.');
            }
            task_comment($id, $user, 'komentar', $body, $fid);
            $other = (int) $t['assigned_to'] === (int) $user['id'] ? (int) $t['created_by'] : (int) $t['assigned_to'];
            if ($other && $other !== (int) $user['id']) {
                notify_user($other, $user['name'] . ' — „' . $t['title'] . '": ' . ($body !== '' ? $body : '(slika)'), url('zadatak', ['id' => $id]));
            }
            break;
        case 'task_delete':
            $t = load_task($id) ?? throw new RuleError('Zadatak ne postoji.');
            if (!can_edit_task($user, $t)) {
                throw new RuleError('Nemaš pravo da obrišeš ovaj zadatak.');
            }
            task_delete($t);
            flash('ok', 'Zadatak je obrisan.');
            $back = url('zadaci');
            break;

        default:
            http_response_code(400);
            exit;
    }
} catch (RuleError | UploadError $ex) {
    flash('warn', $ex->getMessage());
}
redirect($back);

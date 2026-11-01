<?php
declare(strict_types=1);

// Kratke akcije nad stavkom: start (uz naplatu PRE) i nastavak posle zastoja.

require dirname(__DIR__) . '/_init.php';
session_boot();

if (!is_post()) {
    http_response_code(405);
    exit;
}
csrf_check();
$user = require_login();
$itemId = (int) ($_POST['item'] ?? 0);
$pid = (int) q_val('SELECT project_id FROM project_items WHERE id = ?', [$itemId]);
$back = $pid ? url('projekat', ['id' => $pid]) : url();

try {
    switch ($_POST['action'] ?? '') {
        case 'start':
            $paid = null;
            if (array_key_exists('paid_amount', $_POST)) {
                $paid = parse_amount((string) $_POST['paid_amount']);
                if ($paid === null || (float) $paid <= 0) {
                    throw new RuleError('Unesi iznos koji je naplaćen.');
                }
            }
            item_start($itemId, $user, $paid);
            flash('ok', $paid !== null ? 'Naplata potvrđena — montaža je počela.' : 'Stavka je započeta.');
            break;
        case 'resume':
            item_resume($itemId, $user);
            flash('ok', 'Zastoj je zatvoren — nastavljeno.');
            break;
        default:
            http_response_code(400);
            exit;
    }
} catch (RuleError $ex) {
    flash('warn', $ex->getMessage());
}
redirect($back);

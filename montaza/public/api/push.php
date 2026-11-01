<?php
declare(strict_types=1);

// Pretplata telefona na obaveštenja iz aplikacije (Web Push).

require dirname(__DIR__) . '/_init.php';
session_boot();

if (!is_post()) {
    http_response_code(405);
    exit;
}
csrf_check();
$user = current_user();
if (!$user) {
    json_out(['ok' => false, 'error' => 'Nisi prijavljen.'], 401);
}

try {
    switch ($_POST['action'] ?? '') {
        case 'subscribe':
            $sub = json_decode((string) ($_POST['subscription'] ?? ''), true);
            push_save_subscription((int) $user['id'], is_array($sub) ? $sub : []);
            log_activity('push_enabled');
            $sent = push_to_user((int) $user['id'], 'AG Montaža', 'Obaveštenja su uključena na ovom telefonu ✓', url());
            json_out(['ok' => true, 'test_sent' => $sent > 0]);
        case 'unsubscribe':
            $endpoint = (string) ($_POST['endpoint'] ?? '');
            q('DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?', [hash('sha256', $endpoint), $user['id']]);
            log_activity('push_disabled');
            json_out(['ok' => true]);
        case 'test':
            $sent = push_to_user((int) $user['id'], 'AG Montaža', 'Probno obaveštenje — radi ✓', url());
            json_out(['ok' => $sent > 0, 'sent' => $sent]);
    }
    json_out(['ok' => false, 'error' => 'Nepoznata akcija'], 400);
} catch (RuntimeException $ex) {
    json_out(['ok' => false, 'error' => $ex->getMessage()], 400);
}

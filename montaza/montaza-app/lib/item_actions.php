<?php
declare(strict_types=1);

// Radnje nad stavkama projekta (faza 3). Pravila su u rules.php.

/** Kršenje poslovnog pravila; poruka (i lista) se prikazuje korisniku. */
class RuleError extends RuntimeException
{
    public function __construct(string $message, public readonly array $missing = [])
    {
        parent::__construct($message);
    }
}

/** Učitava i zaključava projekat + stavku unutar transakcije (sprečava dupli klik / dva telefona). */
function lock_item(int $itemId): array
{
    $item = q_one('SELECT * FROM project_items WHERE id = ? FOR UPDATE', [$itemId]);
    if (!$item) {
        throw new RuleError('Stavka ne postoji.');
    }
    $project = q_one('SELECT * FROM projects WHERE id = ? FOR UPDATE', [$item['project_id']]);
    return [$project, $item];
}

function assert_can_act(array $user, array $project, array $item): void
{
    if (!can_act_item($user, $project, $item)) {
        throw new RuleError('Nemaš pravo da menjaš ovu stavku ili je već zatvorena.');
    }
}

/** Potvrda naplate (pravila 3, 4, 5). Pozivati unutar transakcije. */
function confirm_payment(array $project, string $amount, array $user): void
{
    q(
        'UPDATE projects SET payment_received = ?, payment_confirmed_at = NOW(), payment_confirmed_by = ? WHERE id = ?',
        [$amount, $user['id'], $project['id']]
    );
    $diff = round((float) $amount - (float) $project['payment_amount'], 2);
    log_activity('payment_confirmed', (int) $project['id'], null, [
        'expected' => $project['payment_amount'], 'received' => $amount, 'diff' => $diff,
    ]);
    $msg = 'Naplata potvrđena: ' . project_ref($project) . ' — ' . fmt_money($amount);
    if ($diff != 0.0) {
        // Pravilo 5: razlika u iznosu → odmah vlasniku
        $msg = 'RAZLIKA U NAPLATI: ' . project_ref($project) . ' — očekivano ' . fmt_money($project['payment_amount'])
            . ', naplaćeno ' . fmt_money($amount) . ' (' . ($diff > 0 ? '+' : '') . fmt_money((string) $diff) . ')';
    }
    notify(['vlasnik'], $msg, (int) $project['id']);
}

/** Stavka prelazi u "u toku". $paidAmount: za naplatu PRE ("Naplaćeno — počinjem"). */
function item_start(int $itemId, array $user, ?string $paidAmount = null): void
{
    db()->beginTransaction();
    try {
        [$project, $item] = lock_item($itemId);
        assert_can_act($user, $project, $item);
        if ($item['status'] !== 'ceka') {
            throw new RuleError('Stavka je već započeta.');
        }
        if ($paidAmount !== null && is_payment_item($item) && $project['payment_when'] !== 'ne' && !$project['payment_confirmed_at']) {
            confirm_payment($project, $paidAmount, $user);
            $project['payment_confirmed_at'] = date('Y-m-d H:i:s');
        }
        if ($why = rule_start_blocked($project, $item)) {
            throw new RuleError($why);
        }
        q("UPDATE project_items SET status = 'u_toku', started_at = COALESCE(started_at, NOW()) WHERE id = ?", [$itemId]);
        log_activity('item_started', (int) $project['id'], $itemId, ['kind' => $item['kind']]);
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
}

/**
 * Zatvaranje stavke uz slike.
 * $in: note, paid_amount (string|null), video (bool), handover (bool)
 * $posle: upload slike POSLE (samo montaža), $fotos: ostale slike
 */
function item_complete(int $itemId, array $user, array $in, ?array $posle, array $fotos): array
{
    $stored = [];
    try {
        // Fajlove snimamo pre transakcije (sporo), pa ih brišemo ako pravila ne prođu
        $posleStored = $posle ? $stored[] = store_image($posle) : null;
        $fotoStored = [];
        foreach ($fotos as $f) {
            $fotoStored[] = $stored[] = store_image($f);
        }

        db()->beginTransaction();
        [$project, $item] = lock_item($itemId);
        assert_can_act($user, $project, $item);
        if ($posleStored && $item['kind'] !== 'montaza') {
            $fotoStored[] = $posleStored;
            $posleStored = null;
        }

        $existing = q_one(
            "SELECT SUM(kind IN ('posle','foto')) AS photos, SUM(kind = 'posle') AS posle, SUM(kind = 'nalog_pdf') AS pdf
             FROM files WHERE item_id = ?",
            [$itemId]
        );
        $payNow = is_payment_item($item) && $project['payment_when'] !== 'ne' && !$project['payment_confirmed_at']
            && $in['paid_amount'] !== null;
        $state = [
            'photos'   => (int) $existing['photos'] + count($fotoStored) + ($posleStored ? 1 : 0),
            'posle'    => (int) $existing['posle'] > 0 || $posleStored,
            'pdf'      => (int) $existing['pdf'] > 0,
            'paid'     => (bool) $project['payment_confirmed_at'] || $payNow,
            'video'    => !empty($in['video']),
            'handover' => !empty($in['handover']),
        ];
        $missing = rule_completion_missing($project, $item, $state);
        if ($missing) {
            throw new RuleError('Ne može da se zatvori — fali: ' . implode(', ', $missing) . '.', $missing);
        }

        if ($payNow) {
            confirm_payment($project, $in['paid_amount'], $user);
        }
        foreach ($fotoStored as $s) {
            insert_file((int) $project['id'], $itemId, 'foto', $s, $user['id']);
        }
        if ($posleStored) {
            insert_file((int) $project['id'], $itemId, 'posle', $posleStored, $user['id']);
        }
        if ($item['kind'] === 'montaza') {
            q('UPDATE projects SET video_sent_viber = 1, handover_done = 1 WHERE id = ?', [$project['id']]);
        }

        // started_at: ako nije eksplicitno započeta, uzmi kraj prethodne stavke (ili otvaranje projekta)
        $prevDone = q_val(
            "SELECT MAX(completed_at) FROM project_items WHERE project_id = ? AND sort < ? AND status = 'zavrseno'",
            [$project['id'], $item['sort']]
        );
        q(
            "UPDATE project_items SET status = 'zavrseno', started_at = COALESCE(started_at, ?, ?),
                completed_at = NOW(), completed_by = ?, note = ?, imported = 0 WHERE id = ?",
            [$prevDone, $project['created_at'], $user['id'], $in['note'] !== '' ? $in['note'] : null, $itemId]
        );
        q('UPDATE stoppages SET resolved_at = NOW(), resolved_by = ? WHERE item_id = ? AND resolved_at IS NULL', [$user['id'], $itemId]);
        log_activity('item_completed', (int) $project['id'], $itemId, [
            'kind' => $item['kind'], 'photos' => count($fotoStored) + ($posleStored ? 1 : 0),
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

    $completed = rule_maybe_complete_project((int) $project['id']);

    // Obaveštenja (tačka 9)
    $ref = project_ref($project);
    if ($item['kind'] === 'pakovanje') {
        $week = $project['install_week_start'] ? ', ned. ' . (int) (new DateTimeImmutable($project['install_week_start']))->format('W') : '';
        notify(['teren', 'vlasnik'], "Spremno za montažu: $ref$week", (int) $project['id']);
    } elseif ($item['kind'] === 'montaza' || $item['kind'] === 'servis') {
        notify(['vlasnik'], ITEM_LABELS[$item['kind']] . " završena: $ref", (int) $project['id']);
    } elseif ($item['responsible'] === 'hala') {
        notify(['vlasnik'], ITEM_LABELS[$item['kind']] . " gotovo: $ref", (int) $project['id']);
    }
    return ['project_completed' => $completed];
}

/** Pravilo 9: prijava zastoja. */
function item_stoppage(int $itemId, array $user, string $reason, string $note): void
{
    if (!isset(REASON_LABELS[$reason])) {
        throw new RuleError('Izaberi razlog zastoja.');
    }
    db()->beginTransaction();
    try {
        [$project, $item] = lock_item($itemId);
        assert_can_act($user, $project, $item);
        if ($item['status'] === 'zastoj') {
            throw new RuleError('Zastoj je već prijavljen.');
        }
        q("UPDATE project_items SET status = 'zastoj' WHERE id = ?", [$itemId]);
        q(
            'INSERT INTO stoppages (project_id, item_id, reason, note, created_by) VALUES (?, ?, ?, ?, ?)',
            [$project['id'], $itemId, $reason, $note !== '' ? mb_substr($note, 0, 500) : null, $user['id']]
        );
        log_activity('stoppage', (int) $project['id'], $itemId, ['kind' => $item['kind'], 'reason' => $reason, 'note' => $note]);
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
    notify(['vlasnik'], 'ZASTOJ — ' . project_ref($project) . ' · ' . ITEM_LABELS[$item['kind']] . ': '
        . REASON_LABELS[$reason] . ($note !== '' ? " ($note)" : ''), (int) $project['id']);
}

/** Zastoj se zatvara kad se stavka nastavi. */
function item_resume(int $itemId, array $user): void
{
    db()->beginTransaction();
    try {
        [$project, $item] = lock_item($itemId);
        assert_can_act($user, $project, $item);
        if ($item['status'] !== 'zastoj') {
            throw new RuleError('Stavka nije u zastoju.');
        }
        // Pravilo 3 važi i posle zastoja: bez potvrđene naplate PRE stavka se vraća na "čeka"
        if (rule_start_blocked($project, $item)) {
            q("UPDATE project_items SET status = 'ceka' WHERE id = ?", [$itemId]);
        } else {
            q("UPDATE project_items SET status = 'u_toku', started_at = COALESCE(started_at, NOW()) WHERE id = ?", [$itemId]);
        }
        q('UPDATE stoppages SET resolved_at = NOW(), resolved_by = ? WHERE item_id = ? AND resolved_at IS NULL', [$user['id'], $itemId]);
        log_activity('stoppage_resolved', (int) $project['id'], $itemId, ['kind' => $item['kind']]);
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
}

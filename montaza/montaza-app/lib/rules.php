<?php
declare(strict_types=1);

// Poslovna pravila (tačka 5 speca). Proveravaju se OVDE, na serveru — UI ih samo prikazuje.
// Faza 2: pravila 1, 7, 8 i validacija projekta. Ostala pravila dolaze u fazi 3.

/** Normalizuje unos forme projekta. */
function project_input(array $post): array
{
    $date = function ($v): ?string {
        $v = trim((string) $v);
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    };
    $week = $date($post['install_week_start'] ?? '');
    if ($week !== null) {
        $week = week_monday($week)->format('Y-m-d'); // uvek ponedeljak
    }
    $type = (string) ($post['type'] ?? '');
    return [
        'type'               => $type,
        'customer_name'      => trim((string) ($post['customer_name'] ?? '')),
        'phone'              => trim((string) ($post['phone'] ?? '')),
        'city'               => trim((string) ($post['city'] ?? '')),
        'address'            => trim((string) ($post['address'] ?? '')),
        'description'        => trim((string) ($post['description'] ?? '')),
        'has_steel'          => $type !== 'reklamacija' && !empty($post['has_steel']),
        'install_week_start' => $week,
        'deadline'           => $date($post['deadline'] ?? ''),
        'deadline_raw'       => trim((string) ($post['deadline'] ?? '')),
        'payment_when'       => (string) ($post['payment_when'] ?? 'ne'),
        'payment_amount'     => parse_amount((string) ($post['payment_amount'] ?? '')),
        'payment_amount_raw' => trim((string) ($post['payment_amount'] ?? '')),
        'notes'              => trim((string) ($post['notes'] ?? '')),
        'done_until'         => (string) ($post['done_until'] ?? ''),
        'parent_project_id'  => (int) ($post['parent_project_id'] ?? 0) ?: null,
    ];
}

/** "1.200" / "1200" / "1.200,50" / "1200.5" → "1200.00"; prazno/neispravno → null */
function parse_amount(string $s): ?string
{
    $s = str_replace([' ', "\u{00A0}", '€', 'EUR', 'eur'], '', trim($s));
    if ($s === '') {
        return null;
    }
    if (str_contains($s, ',')) {
        $s = str_replace(['.', ','], ['', '.'], $s);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
        $s = str_replace('.', '', $s);
    }
    return is_numeric($s) && (float) $s >= 0 && (float) $s < 100000000 ? number_format((float) $s, 2, '.', '') : null;
}

/**
 * Validacija projekta. Vraća [polje => poruka].
 * $hasLokacija: da li postoji (ili se upravo šalje) slika lokacije PRE.
 */
function rules_validate_project(array $in, bool $isNew, bool $hasLokacija): array
{
    $err = [];
    if ($isNew && !isset(TYPE_LABELS[$in['type']])) {
        $err['type'] = 'Izaberi tip projekta.';
    }
    if ($in['customer_name'] === '') {
        $err['customer_name'] = 'Unesi kupca.';
    } elseif (mb_strlen($in['customer_name']) > 150) {
        $err['customer_name'] = 'Predugačko ime.';
    }
    if (mb_strlen($in['phone']) > 50) {
        $err['phone'] = 'Neispravan telefon.';
    }
    if ($in['deadline_raw'] !== '' && $in['deadline'] === null) {
        $err['deadline'] = 'Neispravan datum.';
    }
    if (!isset(PAYMENT_LABELS[$in['payment_when']])) {
        $err['payment_when'] = 'Izaberi način naplate.';
    } elseif ($in['payment_when'] !== 'ne' && ($in['payment_amount'] === null || (float) $in['payment_amount'] <= 0)) {
        $err['payment_amount'] = 'Unesi iznos koji teren naplaćuje.';
    }
    // Pravilo 7: slika lokacije PRE obavezna za novi projekat
    if ($isNew && $in['type'] === 'novi' && !$hasLokacija) {
        $err['lokacija'] = 'Slika mesta montaže je obavezna za novi projekat.';
    }
    if ($isNew && $in['type'] === 'zapocet' && $in['done_until'] !== ''
        && !in_array($in['done_until'], ['nalog', 'celik', 'aluminijum', 'cerada', 'pakovanje'], true)) {
        $err['done_until'] = 'Neispravan izbor.';
    }
    if ($isNew && $in['type'] === 'zapocet' && $in['done_until'] === 'celik' && !$in['has_steel']) {
        $err['done_until'] = 'Čelična konstrukcija nije uključena.';
    }
    return $err;
}

/** Pravilo 1: stavka "nalog" se zatvara samo uz PDF. */
function rule_nalog_has_pdf(int $itemId): bool
{
    return (bool) q_val("SELECT 1 FROM files WHERE item_id = ? AND kind = 'nalog_pdf' LIMIT 1", [$itemId]);
}

/** Čelik se sme uključiti/isključiti dok stavka nije započeta. */
function rule_can_toggle_steel(array $items): bool
{
    foreach ($items as $i) {
        if ($i['kind'] === 'celik' && $i['status'] !== 'ceka') {
            return false;
        }
    }
    return true;
}

/** Pravilo 8: sve stavke završene → projekat završen. Vraća true ako je upravo završen. */
function rule_maybe_complete_project(int $projectId): bool
{
    $open = (int) q_val("SELECT COUNT(*) FROM project_items WHERE project_id = ? AND status <> 'zavrseno'", [$projectId]);
    $total = (int) q_val('SELECT COUNT(*) FROM project_items WHERE project_id = ?', [$projectId]);
    if ($total === 0 || $open > 0) {
        return false;
    }
    $n = q("UPDATE projects SET status = 'zavrsen', completed_at = NOW() WHERE id = ? AND status = 'aktivan'", [$projectId])->rowCount();
    if ($n) {
        log_activity('project_completed', $projectId);
    }
    return $n > 0;
}

<?php
declare(strict_types=1);

// Unos i provera projekta (v2): nacrt traži samo kupca; "Pusti u realizaciju" proverava sve neophodno.

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

/** "4", "4,5", "4.5" → "4.50" (metri) ili null */
function parse_meters($v): ?string
{
    $v = str_replace([',', ' ', 'm'], ['.', '', ''], trim((string) $v));
    return $v !== '' && is_numeric($v) && (float) $v > 0 && (float) $v < 100 ? number_format((float) $v, 2, '.', '') : null;
}

function parse_date($v): ?string
{
    $v = trim((string) $v);
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return $d && $d->format('Y-m-d') === $v ? $v : null;
}

function project_input(array $post): array
{
    $systems = [];
    foreach ((array) ($post['systems'] ?? []) as $row) {
        if (!is_array($row) || (empty($row['type_id']) && empty($row['id']))) {
            continue;
        }
        $spec = [];
        foreach ((array) ($row['spec'] ?? []) as $k => $val) {
            if (isset(SPEC_FIELDS[$k]) && trim((string) $val) !== '') {
                $spec[$k] = mb_substr(trim((string) $val), 0, 120);
            }
        }
        $systems[] = [
            'id'         => (int) ($row['id'] ?? 0) ?: null,
            'type_id'    => (int) ($row['type_id'] ?? 0) ?: null,
            'qty'        => max(1, min(99, (int) ($row['qty'] ?? 1))),
            'width'      => parse_meters($row['width'] ?? ''),
            'length'     => parse_meters($row['length'] ?? ''),
            'height'     => parse_meters($row['height'] ?? ''),
            'ral'        => mb_substr(trim((string) ($row['ral'] ?? '')), 0, 60),
            'spec'       => $spec,
            'note'       => trim((string) ($row['note'] ?? '')),
            'with_steel' => !empty($row['with_steel']),
            'done_until' => (int) ($row['done_until'] ?? 0),
            'delete'     => !empty($row['_delete']),
        ];
    }
    $deadlineRaw = trim((string) ($post['deadline'] ?? ''));
    return [
        'work_type'          => isset(WORK_TYPES[$post['work_type'] ?? '']) ? $post['work_type'] : 'novi',
        'intake'             => ($post['intake'] ?? '') === 'preuzet' ? 'preuzet' : 'novi',
        'customer_name'      => trim((string) ($post['customer_name'] ?? '')),
        'phone'              => trim((string) ($post['phone'] ?? '')),
        'city'               => trim((string) ($post['city'] ?? '')),
        'address'            => trim((string) ($post['address'] ?? '')),
        'description'        => trim((string) ($post['description'] ?? '')),
        'deadline'           => parse_date($deadlineRaw),
        'deadline_raw'       => $deadlineRaw,
        'payment_when'       => isset(PAYMENT_LABELS[$post['payment_when'] ?? '']) ? $post['payment_when'] : 'ne',
        'payment_amount'     => parse_amount((string) ($post['payment_amount'] ?? '')),
        'payment_amount_raw' => trim((string) ($post['payment_amount'] ?? '')),
        'payment_collector'  => isset(COLLECTOR_LABELS[$post['payment_collector'] ?? '']) ? $post['payment_collector'] : 'teren',
        'notes'              => trim((string) ($post['notes'] ?? '')),
        'parent_project_id'  => (int) ($post['parent_project_id'] ?? 0) ?: null,
        'systems'            => $systems,
        'lokacija_id'        => posted_ids('lokacija_id')[0] ?? null,
        'nalog_id'           => posted_ids('nalog_id')[0] ?? null,
    ];
}

/**
 * Provera. $release = da li se pušta u realizaciju (puna provera) ili čuva nacrt.
 * $hasLokacija: postoji slika lokacije (već sačuvana ili upravo poslata).
 */
function validate_project(array $in, bool $release, bool $hasLokacija): array
{
    $err = [];
    if ($in['customer_name'] === '') {
        $err['customer_name'] = 'Unesi kupca.';
    }
    if ($in['deadline_raw'] !== '' && $in['deadline'] === null) {
        $err['deadline'] = 'Neispravan datum.';
    }
    if ($in['payment_when'] !== 'ne' && $in['payment_amount_raw'] !== '' && $in['payment_amount'] === null) {
        $err['payment_amount'] = 'Neispravan iznos.';
    }
    if (!$release) {
        return $err;
    }
    if ($in['city'] === '') {
        $err['city'] = 'Unesi mesto.';
    }
    if ($in['deadline'] === null) {
        $err['deadline'] = 'Unesi dogovoreni krajnji rok.';
    }
    if ($in['payment_when'] !== 'ne' && ($in['payment_amount'] === null || (float) $in['payment_amount'] <= 0)) {
        $err['payment_amount'] = 'Unesi iznos za naplatu.';
    }
    $live = array_filter($in['systems'], fn($s) => !$s['delete']);
    if ($in['work_type'] === 'novi' && !$live) {
        $err['systems'] = 'Dodaj bar jedan sistem (vrsta, količina, mere).';
    }
    foreach ($live as $i => $s) {
        if (!$s['type_id']) {
            $err['systems'] = 'Izaberi vrstu za svaki sistem.';
        } elseif ($in['work_type'] === 'novi' && !$s['width'] && $s['note'] === '') {
            $err['systems'] = 'Upiši mere (ili napomenu) za svaki sistem.';
        }
    }
    if ($in['work_type'] === 'novi' && $in['intake'] === 'novi' && !$hasLokacija) {
        $err['lokacija'] = 'Za novi posao je potrebna slika mesta montaže.';
    }
    return $err;
}

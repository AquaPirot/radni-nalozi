<?php
declare(strict_types=1);

const TYPE_LABELS = [
    'novi'        => 'Novi',
    'zapocet'     => 'Započet',
    'reklamacija' => 'Reklamacija',
];

const ITEM_LABELS = [
    'nalog'      => 'Izdat nalog',
    'celik'      => 'Čelična konstrukcija',
    'aluminijum' => 'Aluminijum',
    'cerada'     => 'Cerada',
    'pakovanje'  => 'Pakovanje',
    'montaza'    => 'Montaža',
    'dijagnoza'  => 'Dijagnoza',
    'materijal'  => 'Materijal',
    'servis'     => 'Servis',
];

const REASON_LABELS = [
    'materijal' => 'Fali materijal',
    'vreme'     => 'Vremenski uslovi',
    'kupac'     => 'Kupac nije spreman',
    'ekipa'     => 'Ekipa',
    'ostalo'    => 'Ostalo',
];

const PAYMENT_LABELS = [
    'pre'   => 'Pre početka',
    'posle' => 'Posle montaže',
    'ne'    => 'Ne naplaćuje',
];

const MONTHS_FULL = [1 => 'Januar', 'Februar', 'Mart', 'April', 'Maj', 'Jun', 'Jul', 'Avgust', 'Septembar', 'Oktobar', 'Novembar', 'Decembar'];
const MONTHS_SHORT = [1 => 'JAN', 'FEB', 'MAR', 'APR', 'MAJ', 'JUN', 'JUL', 'AVG', 'SEP', 'OKT', 'NOV', 'DEC'];

// ── Šabloni stavki (tačka 4 speca) ──────────────────────────
// sort ima fiksne "slotove" da se čelik može ubaciti/izbaciti naknadno.

const TEMPLATE_STD = [
    ['nalog',      'vlasnik', 10],
    ['celik',      'hala',    20],
    ['aluminijum', 'hala',    30],
    ['cerada',     'hala',    40],
    ['pakovanje',  'hala',    50],
    ['montaza',    'teren',   60],
];
const TEMPLATE_REKLAMACIJA = [
    ['dijagnoza', 'teren', 10],
    ['materijal', 'hala',  20],
    ['servis',    'teren', 30],
];

/** @return list<array{0:string,1:string,2:int}> */
function item_template(string $type, bool $steel): array
{
    if ($type === 'reklamacija') {
        return TEMPLATE_REKLAMACIJA;
    }
    return array_values(array_filter(TEMPLATE_STD, fn($t) => $t[0] !== 'celik' || $steel));
}

// ── Datumi i nedelje ────────────────────────────────────────

function today(): DateTimeImmutable
{
    return new DateTimeImmutable('today');
}

function week_monday(DateTimeInterface|string $d): DateTimeImmutable
{
    $d = is_string($d) ? new DateTimeImmutable($d) : DateTimeImmutable::createFromInterface($d);
    return $d->setTime(0, 0)->modify('monday this week');
}

/** Radna nedelja: "pon 3.11. – pet 7.11." (umesto broja nedelje — svima jasnije). */
function week_label(DateTimeInterface|string $mon, bool $upper = false): string
{
    $mon = week_monday($mon);
    $s = 'pon ' . $mon->format('j.n.') . ' – pet ' . $mon->modify('+4 days')->format('j.n.');
    return $upper ? mb_strtoupper($s) : $s;
}

/** Kratko: "3.–7.11." ili "29.9.–3.10." */
function week_short(DateTimeInterface|string $mon): string
{
    $mon = week_monday($mon);
    $fri = $mon->modify('+4 days');
    return ($mon->format('n') === $fri->format('n') ? $mon->format('j.') : $mon->format('j.n.')) . '–' . $fri->format('j.n.');
}

/** "ova nedelja" / "sledeća nedelja" / "za 2 nedelje" / "prošla nedelja" / "" */
function week_relative(DateTimeInterface|string $mon): string
{
    $diff = (int) round((week_monday($mon)->getTimestamp() - week_monday(today())->getTimestamp()) / (7 * 86400));
    return match (true) {
        $diff === 0 => 'ova nedelja',
        $diff === 1 => 'sledeća nedelja',
        $diff === -1 => 'prošla nedelja',
        $diff > 1 && $diff < 5 => "za $diff nedelje",
        $diff >= 5 => "za $diff nedelja",
        default => '',
    };
}

/** Za padajući meni: "pon 10.11. – pet 14.11. (sledeća nedelja)" */
function week_option_label(DateTimeImmutable $mon): string
{
    $rel = week_relative($mon);
    return week_label($mon) . ($rel !== '' ? " ($rel)" : '');
}

/** Opcije nedelja: tekuća + narednih $count-1; uključuje i $include ako je ranija. */
function week_options(?string $include = null, int $count = 16): array
{
    $mon = week_monday(today());
    $opts = [];
    if ($include && $include < $mon->format('Y-m-d')) {
        $opts[$include] = week_option_label(new DateTimeImmutable($include));
    }
    for ($i = 0; $i < $count; $i++) {
        $w = $mon->modify("+$i weeks");
        $opts[$w->format('Y-m-d')] = week_option_label($w);
    }
    return $opts;
}

/** "7.11" */
function fmt_dm(?string $date): string
{
    return $date ? (new DateTimeImmutable($date))->format('j.n') : '';
}

/** "14.11.2026" */
function fmt_date(?string $date): string
{
    return $date ? (new DateTimeImmutable($date))->format('j.n.Y') : '';
}

function fmt_money(?string $amount): string
{
    if ($amount === null || $amount === '') {
        return '';
    }
    $f = (float) $amount;
    $dec = fmod($f, 1.0) == 0.0 ? 0 : 2;
    return number_format($f, $dec, ',', '.') . ' €';
}

/** Broj dana kašnjenja (0 ako ne kasni). */
function days_late(?string $date): int
{
    if (!$date) {
        return 0;
    }
    $diff = (int) (new DateTimeImmutable($date))->diff(today())->format('%r%a');
    return max(0, $diff);
}

// ── Prava pristupa (tačka 6 speca) ──────────────────────────

/**
 * Hala/teren vide samo AKTIVNE projekte koji imaju stavku za njihovu ulogu.
 * Vraća SQL uslov nad aliasom p i parametre.
 */
function visibility_sql(array $user): array
{
    if ($user['role'] === 'vlasnik') {
        return ['1=1', []];
    }
    return [
        "p.status = 'aktivan' AND EXISTS (SELECT 1 FROM project_items vi WHERE vi.project_id = p.id AND vi.responsible = ?)",
        [$user['role']],
    ];
}

function can_view_project(array $user, int $projectId): bool
{
    [$where, $params] = visibility_sql($user);
    return (bool) q_val("SELECT 1 FROM projects p WHERE p.id = ? AND $where", array_merge([$projectId], $params));
}

/** Hala nikad ne vidi iznose naplate. */
function can_see_payment(array $user): bool
{
    return in_array($user['role'], ['vlasnik', 'teren'], true);
}

// ── Učitavanje ──────────────────────────────────────────────

function load_project(int $id): ?array
{
    return q_one('SELECT * FROM projects WHERE id = ?', [$id]);
}

function load_items(int $projectId): array
{
    return q_all(
        'SELECT i.*, u.name AS completed_by_name FROM project_items i
         LEFT JOIN users u ON u.id = i.completed_by
         WHERE i.project_id = ? ORDER BY i.sort, i.id',
        [$projectId]
    );
}

/** Stavke za više projekata odjednom: [project_id => [items]] */
function load_items_for(array $projectIds): array
{
    if (!$projectIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($projectIds), '?'));
    $out = [];
    foreach (q_all("SELECT * FROM project_items WHERE project_id IN ($in) ORDER BY project_id, sort, id", array_values($projectIds)) as $r) {
        $out[$r['project_id']][] = $r;
    }
    return $out;
}

/** Otvoreni zastoji: [project_id => stoppage] (najnoviji) */
function open_stoppages_for(array $projectIds): array
{
    if (!$projectIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($projectIds), '?'));
    $out = [];
    foreach (q_all("SELECT * FROM stoppages WHERE resolved_at IS NULL AND project_id IN ($in) ORDER BY created_at", array_values($projectIds)) as $r) {
        $out[$r['project_id']] = $r;
    }
    return $out;
}

function load_files(int $projectId): array
{
    return q_all('SELECT * FROM files WHERE project_id = ? AND task_id IS NULL ORDER BY created_at, id', [$projectId]);
}

// ── Prikaz ──────────────────────────────────────────────────

/** [tekst, css klasa] oznake tipa */
function type_tag(array $p): array
{
    return match ($p['type']) {
        'reklamacija' => ['REKLAMACIJA', 'info'],
        'zapocet'     => ['ZAPOČET', ''],
        default       => [$p['has_steel'] ? 'NOVI + ČELIK' : 'NOVI', ''],
    };
}

function short_desc(?string $desc, int $len = 60): string
{
    $line = trim(strtok((string) $desc, "\n") ?: '');
    return mb_strlen($line) > $len ? mb_substr($line, 0, $len - 1) . '…' : $line;
}

function items_done(array $items): int
{
    return count(array_filter($items, fn($i) => $i['status'] === 'zavrseno'));
}

/** Prva stavka koja nije završena (ili null). */
function current_item(array $items): ?array
{
    foreach ($items as $i) {
        if ($i['status'] !== 'zavrseno') {
            return $i;
        }
    }
    return null;
}

/**
 * Statusna linija kartice: [tekst, klasa]
 * klasa: ok (zeleno) | warn (narandžasto) | live (belo) | idle (sivo)
 */
function project_status_line(array $p, array $items, ?array $stoppage): array
{
    if ($p['status'] === 'zavrsen') {
        return ['Završeno ' . fmt_dm(substr((string) $p['completed_at'], 0, 10)), 'ok'];
    }
    if ($p['status'] === 'otkazan') {
        return ['Otkazano', 'idle'];
    }
    if ($stoppage) {
        $why = trim((string) $stoppage['note']) ?: mb_strtolower(REASON_LABELS[$stoppage['reason']]);
        return ['Zastoj: ' . $why, 'warn'];
    }
    $late = days_late($p['deadline']);
    if ($late > 0) {
        return ['Kasni ' . $late . ' ' . ($late === 1 ? 'dan' : 'dana'), 'warn'];
    }
    $cur = current_item($items);
    if (!$cur) {
        return ['Sve stavke završene', 'ok'];
    }
    if ($cur['kind'] === 'montaza' && $cur['status'] === 'ceka') {
        return ['Spremno za montažu', 'ok'];
    }
    $label = ITEM_LABELS[$cur['kind']];
    if ($cur['status'] === 'u_toku') {
        return [$label . ($cur['kind'] === 'montaza' ? ' u toku' : ' u izradi'), 'live'];
    }
    return ['Čeka: ' . mb_strtolower($label), 'idle'];
}

/** Klasa segmenta trake koraka. */
function step_class(array $item): string
{
    return match ($item['status']) {
        'zavrseno' => 'done',
        'u_toku'   => 'live',
        'zastoj'   => 'warn',
        default    => '',
    };
}

// ── Šifra projekta ──────────────────────────────────────────

/** Sledeća šifra "2026-214". Pozivati UNUTAR transakcije. */
function next_project_code(): string
{
    $year = date('Y');
    $max = (int) q_val(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(code, 6) AS UNSIGNED)), 0) FROM projects WHERE code LIKE ? FOR UPDATE",
        [$year . '-%']
    );
    return sprintf('%s-%03d', $year, $max + 1);
}

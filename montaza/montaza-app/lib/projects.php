<?php
declare(strict_types=1);

// v2 — projekat kao centralno mesto: sistemi, faze proizvodnje, nalozi, termini, uplate, zastoji.
// Tri odvojena statusa: FAZA realizacije, PROBLEM (zastoj), NAPLATA.

const WORK_TYPES = ['novi' => 'Novi posao', 'servis' => 'Servis', 'reklamacija' => 'Reklamacija'];
const INTAKE_LABELS = ['novi' => 'Novi projekat', 'preuzet' => 'Preuzet započet posao'];
const PAYMENT_LABELS = ['pre' => 'Pre početka', 'posle' => 'Posle montaže', 'ne' => 'Ne naplaćuje se'];
const COLLECTOR_LABELS = ['teren' => 'Ekipa na terenu', 'vlasnik' => 'Vlasnik', 'racun' => 'Uplata na račun'];
const METHOD_LABELS = ['gotovina' => 'Gotovina', 'racun' => 'Na račun', 'kartica' => 'Kartica'];
const VISIT_KINDS = ['montaza' => 'Montaža', 'servis' => 'Servis', 'dijagnoza' => 'Dijagnoza', 'dorada' => 'Ponovni dolazak'];
const VISIT_RESULTS = ['celo' => 'Završeno u celosti', 'delimicno' => 'Delimično završeno', 'ponovni' => 'Potreban ponovni dolazak'];
const PHOTO_PURPOSE = ['celina' => 'Celina (isti ugao kao PRE)', 'detalj' => 'Važan detalj', 'nedostatak' => 'Nedostatak / šta je ostalo'];
const STATUS_LABELS = ['ceka' => 'Čeka', 'u_toku' => 'U radu', 'zavrseno' => 'Završeno', 'zastoj' => 'Zastoj'];

const REASON_LABELS = [
    'materijal'   => 'Materijal',
    'proizvodnja' => 'Proizvodnja',
    'kupac'       => 'Kupac',
    'teren'       => 'Teren',
    'vreme'       => 'Vremenski uslovi',
    'transport'   => 'Transport',
    'ostalo'      => 'Drugo',
    'ekipa'       => 'Ekipa', // stari podaci
];

const SPEC_FIELDS = [
    'krov'          => 'Krov',
    'platno'        => 'Platno',
    'lamele'        => 'Lamele',
    'staklo'        => 'Staklo',
    'motor'         => 'Motor',
    'rasveta'       => 'Rasveta',
    'senzori'       => 'Senzori',
    'odvodnjavanje' => 'Odvodnjavanje',
    'kutija'        => 'Kutija',
    'broj_krila'    => 'Broj krila',
];

const STAGES = ['nacrt' => 'Nacrt', 'proizvodnja' => 'Proizvodnja', 'spremno' => 'Spremno', 'montaza' => 'Montaža', 'zavrseno' => 'Završeno', 'otkazan' => 'Otkazano'];

// ── Korisnici ───────────────────────────────────────────────

function users_map(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (q_all('SELECT id, name, role, active FROM users') as $u) {
            $map[(int) $u['id']] = $u;
        }
    }
    return $map;
}

function user_name(?int $id): string
{
    return $id ? (users_map()[$id]['name'] ?? '—') : '—';
}

/** Podrazumevana odgovorna osoba za proizvodnju (prvi aktivan šef hale). */
function default_hala_user(): ?int
{
    foreach (users_map() as $u) {
        if ($u['role'] === 'hala' && $u['active']) {
            return (int) $u['id'];
        }
    }
    return null;
}

function owner_ids(): array
{
    return array_keys(array_filter(users_map(), fn($u) => $u['role'] === 'vlasnik' && $u['active']));
}

// ── Prava pristupa ──────────────────────────────────────────

/** Hala i teren vide aktivne projekte (ne nacrte ni arhivu); vlasnik sve. */
/** Svi vide sve projekte (dogovor: svi pratimo i komentarišemo). Menjanje i dalje zavisi od uloge. */
function visibility_sql(array $user): array
{
    return ['1=1', []];
}

function can_view_project(array $user, int $projectId): bool
{
    [$where, $params] = visibility_sql($user);
    return (bool) q_val("SELECT 1 FROM projects p WHERE p.id = ? AND $where", array_merge([$projectId], $params));
}

/** Iznose vide svi. */
function can_see_payment(array $user): bool
{
    return true;
}

/** Uplatu upisuje vlasnik ili teren (koji naplaćuje). */
function can_record_payment(array $user): bool
{
    return in_array($user['role'], ['vlasnik', 'teren'], true);
}

// ── Učitavanje ──────────────────────────────────────────────

function load_project(int $id): ?array
{
    return q_one('SELECT * FROM projects WHERE id = ?', [$id]);
}

function in_list(array $ids): string
{
    return implode(',', array_fill(0, max(1, count($ids)), '?'));
}

/** [project_id => [sistemi]] sa nazivom vrste i poljima specifikacije */
function systems_for(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $out = [];
    foreach (q_all('SELECT s.*, t.name AS type_name, t.fields AS type_fields FROM project_systems s
                    LEFT JOIN system_types t ON t.id = s.system_type_id
                    WHERE s.project_id IN (' . in_list($ids) . ') ORDER BY s.project_id, s.sort, s.id', array_values($ids)) as $r) {
        $r['spec'] = $r['spec'] ? (json_decode($r['spec'], true) ?: []) : [];
        $out[$r['project_id']][] = $r;
    }
    return $out;
}

function phases_for(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $out = [];
    foreach (q_all('SELECT * FROM production_phases WHERE project_id IN (' . in_list($ids) . ') ORDER BY project_id, system_id, sort, id', array_values($ids)) as $r) {
        $out[$r['project_id']][] = $r;
    }
    return $out;
}

function visits_for(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $out = [];
    foreach (q_all("SELECT v.*, t.name AS team_name, t.leader_id FROM visits v LEFT JOIN teams t ON t.id = v.team_id
                    WHERE v.project_id IN (" . in_list($ids) . ") AND v.status <> 'otkazan'
                    ORDER BY v.project_id, v.start_date IS NULL, v.start_date, v.id", array_values($ids)) as $r) {
        $out[$r['project_id']][] = $r;
    }
    return $out;
}

function orders_for(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $out = [];
    foreach (q_all('SELECT * FROM project_orders WHERE project_id IN (' . in_list($ids) . ') ORDER BY project_id, version', array_values($ids)) as $r) {
        $out[$r['project_id']][] = $r;
    }
    return $out;
}

function payments_for(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $out = [];
    foreach (q_all('SELECT * FROM payments WHERE project_id IN (' . in_list($ids) . ') ORDER BY received_at', array_values($ids)) as $r) {
        $out[$r['project_id']][] = $r;
    }
    return $out;
}

/** Otvoreni zastoji: [project_id => [zastoji]] (najnoviji prvi) */
function open_stoppages_for(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $out = [];
    foreach (q_all('SELECT s.*, ph.name AS phase_name, v.kind AS visit_kind FROM stoppages s
                    LEFT JOIN production_phases ph ON ph.id = s.phase_id
                    LEFT JOIN visits v ON v.id = s.visit_id
                    WHERE s.resolved_at IS NULL AND s.task_id IS NULL AND s.project_id IN (' . in_list($ids) . ')
                    ORDER BY s.created_at DESC', array_values($ids)) as $r) {
        $out[$r['project_id']][] = $r;
    }
    return $out;
}

/** Sve o projektu, za listu ili detalj: [id => ['p'=>…, 'systems'=>…, 'phases'=>…, 'visits'=>…, 'orders'=>…, 'payments'=>…, 'stops'=>…]] */
function bundle(array $projects): array
{
    $ids = array_map('intval', array_column($projects, 'id'));
    $S = systems_for($ids);
    $P = phases_for($ids);
    $V = visits_for($ids);
    $O = orders_for($ids);
    $Pay = payments_for($ids);
    $St = open_stoppages_for($ids);
    $out = [];
    foreach ($projects as $p) {
        $id = (int) $p['id'];
        $out[$id] = [
            'p' => $p, 'systems' => $S[$id] ?? [], 'phases' => $P[$id] ?? [], 'visits' => $V[$id] ?? [],
            'orders' => $O[$id] ?? [], 'payments' => $Pay[$id] ?? [], 'stops' => $St[$id] ?? [],
        ];
    }
    return $out;
}

function bundle_one(int $id): ?array
{
    $p = load_project($id);
    return $p ? bundle([$p])[$id] : null;
}

// ── Prikaz sistema ──────────────────────────────────────────

function fmt_m(?string $v): string
{
    if ($v === null || $v === '') {
        return '';
    }
    return rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
}

/** "4 × 3 m" ili "4 × 3 × h 2,5 m" */
function dims_label(array $s): string
{
    $parts = array_filter([fmt_m($s['width']), fmt_m($s['length'])], fn($x) => $x !== '');
    $txt = implode(' × ', $parts);
    if (fmt_m($s['height']) !== '') {
        $txt .= ($txt !== '' ? ' × ' : '') . 'h ' . fmt_m($s['height']);
    }
    return $txt !== '' ? $txt . ' m' : '';
}

function system_label(array $s, bool $withQty = true): string
{
    $name = $s['type_name'] ?? 'Sistem';
    $qty = (int) $s['qty'] > 1 && $withQty ? (int) $s['qty'] . '× ' : '';
    $d = dims_label($s);
    return $qty . $name . ($d !== '' ? ' ' . $d : '');
}

/** "BIOFIX 4 × 3 m · 2× ZIP screen 3 × 2,5 m" */
function systems_summary(array $systems, int $max = 3): string
{
    if (!$systems) {
        return '';
    }
    $parts = array_map(fn($s) => system_label($s), array_slice($systems, 0, $max));
    $more = count($systems) - $max;
    return implode(' · ', $parts) . ($more > 0 ? " · +$more" : '');
}

function total_qty(array $systems): int
{
    return array_sum(array_map(fn($s) => (int) $s['qty'], $systems));
}

// ── Nalog ───────────────────────────────────────────────────

function current_order(array $orders): ?array
{
    return $orders ? $orders[count($orders) - 1] : null;
}

// ── Termini ─────────────────────────────────────────────────

function visit_end(array $v): ?string
{
    if (!$v['start_date']) {
        return null;
    }
    return (new DateTimeImmutable($v['start_date']))->modify('+' . max(0, (int) $v['days'] - 1) . ' days')->format('Y-m-d');
}

/** "pon 3.11." ili "pon 3.11. – sre 5.11." (+ " u 08:00") */
function visit_when(array $v, bool $time = true): string
{
    if (!$v['start_date']) {
        return 'nije raspoređeno';
    }
    $s = new DateTimeImmutable($v['start_date']);
    $txt = mb_substr(DAYS_FULL[(int) $s->format('w')], 0, 3) . ' ' . $s->format('j.n.');
    if ((int) $v['days'] > 1) {
        $e = new DateTimeImmutable(visit_end($v));
        $txt .= ' – ' . mb_substr(DAYS_FULL[(int) $e->format('w')], 0, 3) . ' ' . $e->format('j.n.');
    }
    if ($time && $v['start_time']) {
        $txt .= ' u ' . substr($v['start_time'], 0, 5);
    }
    return $txt;
}

/** Sledeći otvoreni termin (planiran/u toku), najraniji. */
function next_visit(array $visits): ?array
{
    $open = array_values(array_filter($visits, fn($v) => in_array($v['status'], ['planiran', 'u_toku'], true)));
    usort($open, fn($a, $b) => [$a['status'] !== 'u_toku', $a['start_date'] ?? '9999'] <=> [$b['status'] !== 'u_toku', $b['start_date'] ?? '9999']);
    return $open[0] ?? null;
}

// ── FAZA realizacije ────────────────────────────────────────

function phases_done(array $phases): bool
{
    foreach ($phases as $ph) {
        if ($ph['status'] !== 'zavrseno') {
            return false;
        }
    }
    return true;
}

/** [ključ, naziv] faze realizacije projekta. */
function project_stage(array $b): array
{
    $p = $b['p'];
    if ($p['status'] === 'nacrt') {
        return ['nacrt', STAGES['nacrt']];
    }
    if ($p['status'] === 'otkazan') {
        return ['otkazan', STAGES['otkazan']];
    }
    if ($p['status'] === 'zavrsen') {
        return ['zavrseno', STAGES['zavrseno']];
    }
    foreach ($b['visits'] as $v) {
        if ($v['status'] === 'u_toku' || ($v['status'] === 'zavrseno' && $v['result'] !== 'celo')) {
            return ['montaza', STAGES['montaza']];
        }
    }
    if (!phases_done($b['phases'])) {
        return ['proizvodnja', STAGES['proizvodnja']];
    }
    return ['spremno', STAGES['spremno']];
}

/** Stepper: Nalog · Izrada · Spremno · Montaža → [[naziv, stanje(done|cur|todo)]] */
function stage_steps(array $b): array
{
    [$st] = project_stage($b);
    $order = current_order($b['orders']);
    $orderDone = $order && $order['accepted_at'];
    $idx = match ($st) {
        'nacrt' => 0, 'proizvodnja' => $orderDone ? 1 : 0, 'spremno' => 2, 'montaza' => 3, 'zavrseno' => 4, default => 0,
    };
    $names = ['Nalog', 'Izrada', 'Spremno', 'Montaža'];
    $out = [];
    foreach ($names as $i => $n) {
        $out[] = [$n, $i < $idx ? 'done' : ($i === $idx ? 'cur' : 'todo')];
    }
    return $out;
}

// ── NAPLATA ─────────────────────────────────────────────────

/**
 * ['amount','received','remaining','status','label','cash_held']
 * status: ne | nije_dospela | za_naplatu | delimicno | naplaceno
 */
function payment_info(array $b): array
{
    $p = $b['p'];
    $amount = (float) ($p['payment_amount'] ?? 0);
    $received = array_sum(array_map(fn($x) => (float) $x['amount'], $b['payments']));
    $held = array_sum(array_map(fn($x) => $x['method'] === 'gotovina' && !$x['handed_at'] ? (float) $x['amount'] : 0.0, $b['payments']));
    $remaining = max(0.0, round($amount - $received, 2));
    if ($p['payment_when'] === 'ne' || $amount <= 0) {
        $status = 'ne';
    } elseif ($remaining <= 0) {
        $status = 'naplaceno';
    } elseif ($received > 0) {
        $status = 'delimicno';
    } else {
        $status = payment_due($b) ? 'za_naplatu' : 'nije_dospela';
    }
    $labels = ['ne' => 'Ne naplaćuje se', 'nije_dospela' => 'Nije dospela', 'za_naplatu' => 'Za naplatu', 'delimicno' => 'Delimično', 'naplaceno' => 'Naplaćeno'];
    return [
        'amount' => $amount, 'received' => $received, 'remaining' => $remaining,
        'status' => $status, 'label' => $labels[$status], 'cash_held' => $held,
        'due' => $status !== 'ne' && $remaining > 0 && payment_due($b),
    ];
}

/** Da li je naplata dospela: PRE — montaža je počela ili je u narednih N dana; POSLE — montaža je obavljena. */
function payment_due(array $b): bool
{
    $p = $b['p'];
    if ($p['payment_when'] === 'ne') {
        return false;
    }
    $ahead = today()->modify('+' . (int) setting('plan_days_ahead', '7') . ' days')->format('Y-m-d');
    foreach ($b['visits'] as $v) {
        if ($v['kind'] === 'dijagnoza') {
            continue;
        }
        if ($p['payment_when'] === 'pre' && ($v['status'] !== 'planiran' || ($v['start_date'] && $v['start_date'] <= $ahead))) {
            return true;
        }
        if ($p['payment_when'] === 'posle' && $v['status'] === 'zavrseno') {
            return true;
        }
    }
    return $p['status'] === 'zavrsen';
}

// ── PROBLEM ─────────────────────────────────────────────────

/** "Čeka staklo — proveriti isporuku — odgovoran Oliver — ugrožen termin montaže" */
function stoppage_text(array $s, ?array $nextVisit = null, bool $withOwner = true): string
{
    $what = trim((string) $s['note']) !== '' ? trim((string) $s['note']) : REASON_LABELS[$s['reason']] ?? 'Zastoj';
    $parts = [$what];
    if (!empty($s['next_step'])) {
        $parts[] = $s['next_step'];
    }
    if ($withOwner && !empty($s['owner_id'])) {
        $parts[] = 'odgovoran ' . user_name((int) $s['owner_id']);
    }
    if (stoppage_threatens($s, $nextVisit)) {
        $parts[] = 'ugrožen termin montaže';
    }
    return implode(' — ', $parts);
}

/** Zastoj ugrožava termin ako očekivano rešenje nije pre termina (ili nije upisano, a termin je za ≤ 7 dana). */
function stoppage_threatens(array $s, ?array $nextVisit): bool
{
    if (!$nextVisit || !$nextVisit['start_date']) {
        return false;
    }
    if (!empty($s['expected_date'])) {
        return $s['expected_date'] >= $nextVisit['start_date'];
    }
    return $nextVisit['start_date'] <= today()->modify('+7 days')->format('Y-m-d');
}

function project_late(array $p): int
{
    return in_array($p['status'], ['aktivan', 'nacrt'], true) ? days_late($p['deadline']) : 0;
}

// ── SLEDEĆA RADNJA ──────────────────────────────────────────

/** [tekst, odgovorna osoba (ime) ili ''] */
function next_action(array $b): array
{
    $p = $b['p'];
    if ($p['status'] === 'nacrt') {
        return ['Dopuni i pusti u realizaciju', user_name(owner_ids()[0] ?? null)];
    }
    if (in_array($p['status'], ['zavrsen', 'otkazan'], true)) {
        $pay = payment_info($b);
        return $pay['remaining'] > 0 && $pay['status'] !== 'ne' ? ['Naplatiti ' . fmt_money((string) $pay['remaining']), COLLECTOR_LABELS[$p['payment_collector']] ?? ''] : ['', ''];
    }
    if ($b['stops']) {
        $s = $b['stops'][0];
        return ['Rešiti zastoj: ' . ($s['next_step'] ?: ($s['note'] ?: REASON_LABELS[$s['reason']])), $s['owner_id'] ? user_name((int) $s['owner_id']) : 'nije određeno'];
    }
    if ($b['phases']) {
        $order = current_order($b['orders']);
        if (!$order) {
            return ['Izdati radni nalog', user_name(owner_ids()[0] ?? null)];
        }
        if (!$order['accepted_at']) {
            return ['Preuzeti nalog' . ($order['version'] > 1 ? ' (nova verzija v' . $order['version'] . ')' : ''), user_name(default_hala_user())];
        }
        foreach ($b['phases'] as $ph) {
            if ($ph['status'] !== 'zavrseno') {
                $sys = null;
                foreach ($b['systems'] as $s) {
                    if ((int) $s['id'] === (int) $ph['system_id']) {
                        $sys = $s;
                    }
                }
                return [$ph['name'] . (count($b['systems']) > 1 && $sys ? ' — ' . ($sys['type_name'] ?? '') : ''), user_name($ph['assignee_id'] ? (int) $ph['assignee_id'] : default_hala_user())];
            }
        }
    }
    $nv = next_visit($b['visits']);
    if (!$nv) {
        return [$b['visits'] ? 'Završiti projekat' : 'Rasporediti montažu', user_name(owner_ids()[0] ?? null)];
    }
    if (!$nv['start_date']) {
        return ['Rasporediti: ' . mb_strtolower(VISIT_KINDS[$nv['kind']]), user_name(owner_ids()[0] ?? null)];
    }
    if (!$nv['confirmed'] && $nv['status'] === 'planiran') {
        return ['Potvrditi termin sa kupcem (' . visit_when($nv, false) . ')', user_name(owner_ids()[0] ?? null)];
    }
    $who = $nv['team_name'] ?? 'ekipa';
    return [VISIT_KINDS[$nv['kind']] . ($nv['status'] === 'u_toku' ? ' u toku' : ' ' . visit_when($nv)), $who];
}

// ── SPREMNOST za montažu ────────────────────────────────────

/** [[naziv, ok(bool)]] — proizvod spreman, nalog preuzet, ekipa, termin potvrđen. */
function readiness(array $b, array $visit): array
{
    $order = current_order($b['orders']);
    $checks = [];
    if ($b['phases']) {
        $checks[] = ['Nalog preuzet u hali', (bool) ($order && $order['accepted_at'])];
        $checks[] = ['Proizvod završen i spakovan', phases_done($b['phases'])];
    }
    $checks[] = ['Ekipa određena', (bool) $visit['team_id']];
    $checks[] = ['Termin potvrđen sa kupcem', (bool) $visit['confirmed']];
    return $checks;
}

function readiness_ok(array $checks): bool
{
    foreach ($checks as [, $ok]) {
        if (!$ok) {
            return false;
        }
    }
    return true;
}

// ── Oznake za karticu ───────────────────────────────────────

function work_tag(array $p): array
{
    $t = WORK_TYPES[$p['work_type']] ?? 'Novi posao';
    if ($p['intake'] === 'preuzet') {
        $t .= ' · preuzet';
    }
    return [mb_strtoupper($t), $p['work_type'] === 'reklamacija' ? 'info' : ''];
}

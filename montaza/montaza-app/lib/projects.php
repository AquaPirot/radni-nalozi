<?php
declare(strict_types=1);

// v3 „jednostavno": projekat, stavke (kao v1), montaža (dan + vreme), zastoj, slike, PDF nalog.

const TYPE_LABELS = [
    'novi'        => 'Novi posao',
    'zapocet'     => 'Započet posao',
    'reklamacija' => 'Reklamacija / servis',
];

const ITEM_LABELS = [
    'nalog'      => 'Nalog',
    'celik'      => 'Čelik',
    'aluminijum' => 'Aluminijum',
    'cerada'     => 'Cerada',
    'pakovanje'  => 'Pakovanje',
    'montaza'    => 'Montaža',
    'dijagnoza'  => 'Dijagnoza',
    'materijal'  => 'Materijal',
    'servis'     => 'Servis',
];

const ITEM_STATUS = [
    'ceka'     => 'Čeka',
    'u_toku'   => 'U radu',
    'zavrseno' => 'Završeno',
    'zastoj'   => 'Zastoj',
];

const REASON_LABELS = [
    'materijal'   => 'Fali materijal',
    'kupac'       => 'Kupac nije spreman',
    'vreme'       => 'Vreme',
    'transport'   => 'Transport',
    'drugo'       => 'Drugo',
    // starije vrednosti (prikaz istorije)
    'proizvodnja' => 'Proizvodnja',
    'teren'       => 'Teren',
    'ekipa'       => 'Ekipa',
    'ostalo'      => 'Ostalo',
];
const REASONS_NEW = ['materijal', 'kupac', 'vreme', 'transport', 'drugo'];

// ── Stavke po izboru ────────────────────────────────────────
// Nalog (vlasnik) na početku, Montaža (teren) na kraju; između — stavke hale izabrane u formi projekta.
// Reklamacija ima samo „Servis" (teren). Spisak za izbor se menja u Podešavanjima.

const LEGACY_KINDS = ['čelik' => 'celik', 'aluminijum' => 'aluminijum', 'cerada' => 'cerada', 'pakovanje' => 'pakovanje', 'materijal' => 'materijal'];
const FIXED_KINDS = ['nalog', 'montaza', 'servis', 'dijagnoza'];
const DEFAULT_CATALOG = "Čelik\nAluminijum\nCerada\nStaklo\nGiljotina\nZIP zavese\nPakovanje";

/** Spisak stavki za izbor (Podešavanja → jedna po redu). */
function item_catalog(): array
{
    $list = array_values(array_unique(array_filter(array_map('trim', explode("\n", (string) setting('item_catalog', DEFAULT_CATALOG))))));
    return $list ?: ['Aluminijum', 'Pakovanje'];
}

function item_label(array $i): string
{
    return (string) (($i['label'] ?? null) ?: (ITEM_LABELS[$i['kind']] ?? ucfirst((string) $i['kind'])));
}

/** Ključ za poređenje stavki (isti naziv = ista stavka). */
function item_key(array $i): string
{
    return in_array($i['kind'], FIXED_KINDS, true) ? $i['kind'] : mb_strtolower(item_label($i));
}

/** Željene stavke za vrstu posla i izbor: [[kind, label|null, responsible, sort], …] */
function desired_items(string $type, array $works): array
{
    if ($type === 'reklamacija') {
        return [['servis', null, 'teren', 900]];
    }
    $out = [['nalog', null, 'vlasnik', 10]];
    $cat = item_catalog();
    foreach ($works as $w) {
        $idx = array_search($w, $cat, true);
        $kind = LEGACY_KINDS[mb_strtolower($w)] ?? 'rad';
        $out[] = [$kind, $kind === 'rad' ? $w : null, 'hala', 20 + ($idx === false ? 50 : (int) $idx) * 10];
    }
    $out[] = ['montaza', null, 'teren', 900];
    return $out;
}

/** Usklađuje stavke projekta sa izborom: dodaje nove, briše samo nezapočete koje više nisu izabrane. */
function sync_items(int $projectId, string $type, array $works): void
{
    $want = [];
    foreach (desired_items($type, $works) as $d) {
        $want[item_key(['kind' => $d[0], 'label' => $d[1]])] = $d;
    }
    $have = [];
    foreach (q_all('SELECT * FROM project_items WHERE project_id = ?', [$projectId]) as $i) {
        $k = item_key($i);
        if (!isset($want[$k]) && $i['status'] === 'ceka') {
            q('DELETE FROM project_items WHERE id = ?', [$i['id']]);
        } else {
            $have[$k] = true;
        }
    }
    foreach ($want as $k => [$kind, $label, $resp, $sort]) {
        if (!isset($have[$k])) {
            q('INSERT INTO project_items (project_id, kind, label, sort, responsible) VALUES (?, ?, ?, ?, ?)', [$projectId, $kind, $label, $sort, $resp]);
        }
    }
}

/** Izabrane stavke hale (za formu izmene). */
function project_works(array $items): array
{
    return array_values(array_map('item_label', array_filter($items, fn($i) => !in_array($i['kind'], FIXED_KINDS, true))));
}

/** Koliko slika treba za završetak: samo montaža/servis traže slike (podešavanje), ostalo ne. */
function item_min_photos(array $item): int
{
    return $item['kind'] === 'montaza' ? (int) setting('min_photos_montaza', '3') : 0;
}

/** Stavke na kojima se prilaže više slika (teren). */
function item_is_field(string $kind): bool
{
    return in_array($kind, ['montaza', 'servis', 'dijagnoza'], true);
}

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

function owner_ids(): array
{
    return array_keys(array_filter(users_map(), fn($u) => $u['role'] === 'vlasnik' && $u['active']));
}

// ── Prava ───────────────────────────────────────────────────
// Svi vide sve i komentarišu. Menja: projekat i datum montaže — vlasnik; stavke — odgovorni (hala/teren) ili vlasnik.

function visibility_sql(array $user): array
{
    return ['1=1', []];
}

function can_view_project(array $user, int $projectId): bool
{
    return (bool) q_val('SELECT 1 FROM projects WHERE id = ?', [$projectId]);
}

function can_act_item(array $user, array $item): bool
{
    return $user['role'] === 'vlasnik' || $user['role'] === $item['responsible'];
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

function items_for(array $projectIds): array
{
    $out = [];
    if ($projectIds) {
        foreach (q_all('SELECT * FROM project_items WHERE project_id IN (' . in_list($projectIds) . ') ORDER BY project_id, sort, id', array_values($projectIds)) as $r) {
            $out[(int) $r['project_id']][] = $r;
        }
    }
    return $out;
}

/** Otvoren zastoj po projektu (najnoviji). */
function stops_for(array $projectIds): array
{
    $out = [];
    if ($projectIds) {
        foreach (q_all('SELECT s.*, u.name AS created_name FROM stoppages s LEFT JOIN users u ON u.id = s.created_by
                        WHERE s.resolved_at IS NULL AND s.project_id IN (' . in_list($projectIds) . ') ORDER BY s.created_at', array_values($projectIds)) as $s) {
            $out[(int) $s['project_id']] = $s;
        }
    }
    return $out;
}

/** Nalog PDF (najnoviji) po projektu. */
function nalog_for(array $projectIds): array
{
    $out = [];
    if ($projectIds) {
        foreach (q_all("SELECT id, project_id FROM files WHERE kind = 'nalog_pdf' AND pending = 0 AND project_id IN (" . in_list($projectIds) . ') ORDER BY id', array_values($projectIds)) as $f) {
            $out[(int) $f['project_id']] = (int) $f['id'];
        }
    }
    return $out;
}

/** Projekti sa stavkama, zastojem i nalogom: [['p'=>…, 'items'=>…, 'stop'=>…, 'nalog'=>id|null], …] */
function bundle(array $projects): array
{
    $ids = array_map(fn($p) => (int) $p['id'], $projects);
    $items = items_for($ids);
    $stops = stops_for($ids);
    $nalog = nalog_for($ids);
    $out = [];
    foreach ($projects as $p) {
        $id = (int) $p['id'];
        $out[$id] = ['p' => $p, 'items' => $items[$id] ?? [], 'stop' => $stops[$id] ?? null, 'nalog' => $nalog[$id] ?? null];
    }
    return $out;
}

function bundle_one(int $id): ?array
{
    $p = load_project($id);
    return $p ? bundle([$p])[$id] : null;
}

// ── Status ──────────────────────────────────────────────────

function current_item(array $items): ?array
{
    foreach ($items as $i) {
        if ($i['status'] !== 'zavrseno') {
            return $i;
        }
    }
    return null;
}

/** Sve osim terenskih stavki završeno → proizvod spreman. */
function ready_for_field(array $items): bool
{
    foreach ($items as $i) {
        if (!item_is_field($i['kind']) && $i['status'] !== 'zavrseno') {
            return false;
        }
    }
    return true;
}

/** Statusna linija: [tekst, klasa ok|warn|live|idle] */
function status_line(array $b): array
{
    $p = $b['p'];
    if ($p['status'] === 'zavrsen') {
        return ['Završeno ' . fmt_dm(substr((string) $p['completed_at'], 0, 10)), 'ok'];
    }
    if ($p['status'] === 'otkazan') {
        return ['Otkazano', 'idle'];
    }
    if ($b['stop']) {
        return ['Zastoj: ' . (trim((string) $b['stop']['note']) ?: mb_strtolower(REASON_LABELS[$b['stop']['reason']] ?? 'zastoj')), 'warn'];
    }
    $cur = current_item($b['items']);
    if (!$cur) {
        return ['Sve stavke završene', 'ok'];
    }
    if ($p['type'] === 'reklamacija') {
        return [$cur['status'] === 'u_toku' ? 'Reklamacija — u radu' : 'Reklamacija — čeka servis', 'info'];
    }
    if (item_is_field($cur['kind']) && $cur['status'] === 'ceka' && ready_for_field($b['items'])) {
        return [['montaza' => 'Spremno za montažu', 'servis' => 'Spremno za servis', 'dijagnoza' => 'Čeka dijagnozu'][$cur['kind']], 'ok'];
    }
    $label = item_label($cur);
    if ($cur['status'] === 'u_toku') {
        return [$label . ' — u radu', 'info'];
    }
    return ['Čeka: ' . mb_strtolower($label), 'idle'];
}

/** Montaža kasni: datum je prošao, a terenska stavka nije završena. */
function install_late(array $b): int
{
    $p = $b['p'];
    if ($p['status'] !== 'aktivan' || !$p['install_date']) {
        return 0;
    }
    $field = array_filter($b['items'], fn($i) => item_is_field($i['kind']) && $i['status'] !== 'zavrseno');
    return $field ? days_late($p['install_date']) : 0;
}

/** „sre 4.11. u 08:00" / „danas" / „sutra u 9:30" */
function install_when(array $p, bool $relative = true): string
{
    if (!$p['install_date']) {
        return '';
    }
    $d = new DateTimeImmutable($p['install_date']);
    $diff = (int) today()->diff($d)->format('%r%a');
    $day = ['ned', 'pon', 'uto', 'sre', 'čet', 'pet', 'sub'][(int) $d->format('w')] . ' ' . $d->format('j.n.');
    if ($relative && $diff === 0) {
        $day = 'danas';
    } elseif ($relative && $diff === 1) {
        $day = 'sutra';
    }
    return $day . ($p['install_time'] ? ' u ' . substr((string) $p['install_time'], 0, 5) : '');
}

function address_is_link(?string $a): bool
{
    return (bool) preg_match('~^https?://~i', trim((string) $a));
}

/** Adresa za prikaz: ako je u adresu nalepljen link (Google Maps), prikazuje se mesto + „lokacija na mapi". */
function address_text(array $p): string
{
    if (address_is_link($p['address'])) {
        return trim(($p['city'] ? $p['city'] . ' · ' : '') . 'lokacija na mapi');
    }
    return implode(', ', array_filter([$p['address'], $p['city']]));
}

function maps_url(array $p): string
{
    if (address_is_link($p['address'])) {
        return trim((string) $p['address']);
    }
    $q = trim(implode(', ', array_filter([$p['address'], $p['city']])));
    return $q === '' ? '' : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
}

function tel_url(array $p): string
{
    $n = preg_replace('/[^\d+]/', '', (string) $p['phone']);
    return $n === '' ? '' : 'tel:' . $n;
}

// ── Kartica projekta (spisak) ───────────────────────────────

function project_card(array $b): string
{
    $p = $b['p'];
    [$line, $cls] = status_line($b);
    $late = install_late($b);
    $steps = '';
    foreach ($b['items'] as $i) {
        $c = match ($i['status']) { 'zavrseno' => 'done', 'u_toku' => 'live', 'zastoj' => 'warn', default => '' };
        $steps .= '<span class="' . $c . '" title="' . e(item_label($i) . ': ' . ITEM_STATUS[$i['status']]) . '"></span>';
    }
    $when = install_when($p);
    $meta = $p['status'] === 'zavrsen' ? '' : ($when
        ? '<span class="' . ($late ? 'warn' : (str_starts_with($when, 'danas') ? 'today' : (str_starts_with($when, 'sutra') ? 'tomorrow' : ''))) . '">' . icon('plan', 16) . ' ' . ($p['type'] === 'reklamacija' ? 'Izlazak ' : 'Montaža ') . e($when) . ($late ? ' — kasni ' . $late . ' d' : '') . '</span>'
        : '<span class="muted">' . icon('plan', 16) . ' Montaža: datum nije određen</span>');
    return '<a class="pcard st-' . ($late ? 'warn' : $cls) . '" href="' . e(url('projekat', ['id' => $p['id']])) . '">'
        . '<div class="pcard-top"><div class="pcard-name"><strong>' . e($p['customer_name']) . '</strong>'
        . ($p['type'] === 'reklamacija' ? '<span class="tag rekl">Reklamacija</span>' : '') . '</div>'
        . ($b['nalog'] ? '<span class="muted small">' . icon('file', 16) . '</span>' : '') . '</div>'
        . '<div class="pcard-sub">' . e(address_text($p)) . '</div>'
        . '<div class="steps">' . $steps . '</div>'
        . '<div class="pcard-foot"><span class="status ' . $cls . '"><i></i>' . e($line) . '</span></div>'
        . ($meta !== '' ? '<div class="pcard-meta small">' . $meta . '</div>' : '')
        . '</a>';
}

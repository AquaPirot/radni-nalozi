<?php
declare(strict_types=1);

/** HTML escape — sav izlaz ide kroz ovo. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $page = '', array $params = []): string
{
    $base = rtrim((string) config('base_url', ''), '/');
    if ($page === '') {
        return $base . '/';
    }
    return $base . '/?' . http_build_query(['p' => $page] + $params);
}

function asset(string $path): string
{
    $file = PUBLIC_DIR . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 0;
    return rtrim((string) config('base_url', ''), '/') . '/assets/' . $path . '?v=' . $v;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Renderuje view unutar layout-a.
 * $layout = false → bez layout-a (npr. login).
 */
function view(string $name, array $data = [], string|false $layout = 'layout'): void
{
    $viewFile = APP_DIR . '/views/' . $name . '.php';
    if (!is_file($viewFile)) {
        throw new RuntimeException("View ne postoji: $name");
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    if ($layout === false) {
        echo $content;
        return;
    }
    require APP_DIR . '/views/' . $layout . '.php';
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Inline SVG ikone (stroke stil, bez emotikona). */
function icon(string $name, int $size = 22): string
{
    static $paths = [
        'projekti' => '<rect x="4" y="4" width="16" height="7" rx="3.5"/><rect x="4" y="13" width="16" height="7" rx="3.5"/>',
        'zadaci'   => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="m3 6 1.5 1.5L7 5M3 12l1.5 1.5L7 11M3 18l1.5 1.5L7 17"/>',
        'plan'     => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'izvestaj' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'podesavanja' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'alert'    => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'cash'     => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        'camera'   => '<path d="M14.5 4h-5L7.5 6.5H4a2 2 0 0 0-2 2V18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8.5a2 2 0 0 0-2-2h-3.5z"/><circle cx="12" cy="13" r="3.5"/>',
        'file'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M12 18v-6M9 15l3-3 3 3"/>',
        'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5L5 20"/>',
        'back'     => '<path d="m15 18-6-6 6-6"/>',
        'video'    => '<rect x="2" y="6" width="14" height="12" rx="2"/><path d="m16 10 6-4v12l-6-4"/>',
        'download' => '<path d="M12 4v12M6 11l6 6 6-6M4 20h16"/>',
        'bell'     => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
        'home'     => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'nav'      => '<path d="m3 11 19-9-9 19-2-8z"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M22 20a7 7 0 0 0-4-6.3"/>',
        'play'     => '<path d="M7 4v16l13-8z"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'     => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'close'    => '<path d="M18 6 6 18M6 6l12 12"/>',
        'chevron'  => '<path d="m9 18 6-6-6-6"/>',
        'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash'    => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
        'phone'    => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

const ROLE_LABELS = [
    'vlasnik' => 'Vlasnik',
    'hala'    => 'Hala',
    'teren'   => 'Teren',
];

/** Trenutna adresa (za povratak posle akcije), samo putanja + upit. */
function here(): string
{
    return (string) ($_SERVER['REQUEST_URI'] ?? '/');
}

/**
 * Dugme koje šalje akciju na api/akcija.php i vraća se na istu stranu.
 * $fields: dodatna skrivena polja; $confirm: tekst potvrde (opciono).
 */
function action_button(string $action, int $id, string $label, string $class = 'btn ghost', array $fields = [], ?string $confirm = null, string $icon = ''): string
{
    $h = '<form method="post" action="' . e(url() . 'api/akcija.php') . '" class="inline-form"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
        . csrf_field() . once_field()
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="back" value="' . e(here()) . '">';
    foreach ($fields as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    return $h . '<button class="' . e($class) . '" type="submit">' . ($icon ? icon($icon, 18) : '') . e($label) . '</button></form>';
}

/** Polje za slanje slika/PDF-a jedna po jedna (JS: data-uploader). */
function uploader(string $name, string $label, bool $multiple = true, string $type = 'foto', bool $required = false, string $hint = '', ?array $ids = null): string
{
    // Fajlovi već poslati u prethodnom pokušaju (forma vraćena zbog greške) ostaju prikazani
    $ids ??= is_post() ? posted_ids($name) : [];
    $list = '';
    foreach ($ids as $id) {
        $list .= '<span class="up-item ok"><input type="hidden" name="' . e($name) . '[]" value="' . (int) $id . '">'
            . ($type === 'pdf' ? '<span class="up-pdf">PDF</span>' : '<img src="' . e(file_url((int) $id, true)) . '" alt="">')
            . '<button type="button" class="up-x" aria-label="Ukloni">×</button></span>';
    }
    return '<div class="uploader" data-uploader data-name="' . e($name) . '" data-type="' . e($type) . '"' . ($multiple ? ' data-multiple' : '') . ($required ? ' data-required' : '') . '>'
        . '<div class="up-head"><span>' . e($label) . ($required ? ' <span class="warn">*</span>' : '') . '</span>' . ($hint ? '<span class="small muted">' . e($hint) . '</span>' : '') . '</div>'
        . '<div class="up-list">' . $list . '</div>'
        . '<label class="up-add">' . icon($type === 'pdf' ? 'file' : 'camera', 22)
        . '<span>' . ($type === 'pdf' ? 'Izaberi PDF' : ($multiple ? 'Kamera ili galerija' : 'Slikaj ili izaberi')) . '</span>'
        . '<input type="file" accept="' . ($type === 'pdf' ? 'application/pdf,.pdf' : 'image/*') . '"' . ($multiple ? ' multiple' : '') . '></label>'
        . '</div>';
}

/** Čitljiv opis zapisa iz istorije (activity_log). */
function activity_label(array $l): string
{
    $d = $l['details'] ? (json_decode((string) $l['details'], true) ?: []) : [];
    $item = fn() => ITEM_LABELS[$d['kind'] ?? ''] ?? ($d['phase'] ?? 'stavka');
    return match ($l['action']) {
        'project_created'   => 'Projekat otvoren',
        'project_updated'   => 'Izmenjen projekat',
        'project_completed' => 'Projekat završen',
        'install_set'       => !empty($d['to']) ? 'Montaža: ' . fmt_dm($d['to']) . (!empty($d['from']) ? ' (bilo ' . fmt_dm($d['from']) . ')' : '') : 'Uklonjen datum montaže',
        'nalog_uploaded', 'order_issued' => 'Dodat nalog (PDF)',
        'photos_added'      => 'Dodate slike' . (!empty($d['n']) ? ' (' . $d['n'] . ')' : ''),
        'item_started', 'phase_started'     => 'Počelo: ' . $item(),
        'item_completed', 'phase_completed' => 'Završeno: ' . $item() . (!empty($d['note']) ? ' — ' . $d['note'] : ''),
        'item_reset'        => 'Vraćeno na „čeka": ' . $item(),
        'stoppage'          => 'Zastoj: ' . ($d['note'] ?? ''),
        'stoppage_resolved' => 'Rešen zastoj' . (!empty($d['note']) ? ': ' . $d['note'] : ''),
        'visit_created', 'visit_updated' => 'Termin montaže' . (!empty($d['date']) ? ' ' . fmt_dm($d['date']) : ''),
        'visit_completed'   => 'Montaža urađena',
        'migration'         => 'Nadogradnja aplikacije',
        default             => str_replace('_', ' ', (string) $l['action']),
    };
}

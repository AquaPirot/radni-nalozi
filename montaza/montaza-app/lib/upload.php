<?php
declare(strict_types=1);

// Upload slika i PDF-ova (tačka 8 speca).
// Slike se UVEK ponovo kodiraju kroz GD: to briše EXIF i eventualni maliciozni sadržaj.

const IMG_MAX_SIDE   = 1600;
const IMG_THUMB_SIDE = 400;
const IMG_MAX_BYTES  = 20 * 1024 * 1024; // sirov upload (ako JS smanjivanje nije radilo)
const PDF_MAX_BYTES  = 15 * 1024 * 1024;
const IMG_MIMES      = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

/** Greška čija se poruka sme prikazati korisniku. */
class UploadError extends RuntimeException {}

function upload_root(): string
{
    return rtrim((string) (config('upload_dir') ?: PUBLIC_DIR . '/uploads'), '/');
}

/** Normalizuje $_FILES[$field] (jedan ili više fajlova) u listu; preskače prazna polja. */
function uploaded_files(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f) {
        return [];
    }
    if (!is_array($f['name'])) {
        return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $name) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i],
            'error' => $f['error'][$i], 'size' => $f['size'][$i],
        ];
    }
    return $out;
}

function upload_check(array $f, int $maxBytes): void
{
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new UploadError(match ($f['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fajl je prevelik.',
            UPLOAD_ERR_PARTIAL => 'Upload je prekinut. Pokušaj ponovo.',
            default => 'Upload nije uspeo.',
        });
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        throw new UploadError('Neispravan upload.');
    }
    if ($f['size'] > $maxBytes) {
        throw new UploadError('Fajl je prevelik (max ' . round($maxBytes / 1048576) . ' MB).');
    }
}

function upload_target(string $ext): array
{
    $sub = date('Y/m');
    $dir = upload_root() . '/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("Ne mogu da napravim folder $dir");
    }
    $base = bin2hex(random_bytes(16));
    return [$sub . '/' . $base . '.' . $ext, $sub . '/' . $base . '_t.' . $ext];
}

/** Snima sliku: max 1600 px, JPEG, + thumbnail 400 px. Vraća podatke za tabelu files. */
function store_image(array $f): array
{
    upload_check($f, IMG_MAX_BYTES);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, IMG_MIMES, true)) {
        throw new UploadError('Dozvoljene su samo slike (JPG, PNG, WEBP).');
    }
    $img = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
    if (!$img) {
        throw new UploadError('Slika je oštećena ili nije podržana.');
    }

    // Ispravi orijentaciju po EXIF-u (pre nego što ga GD obriše)
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($f['tmp_name']);
        $img = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }

    $full  = image_fit($img, IMG_MAX_SIDE);
    $thumb = image_fit($img, IMG_THUMB_SIDE);
    [$rel, $relThumb] = upload_target('jpg');
    $root = upload_root();
    if (!imagejpeg($full, "$root/$rel", 82) || !imagejpeg($thumb, "$root/$relThumb", 75)) {
        @unlink("$root/$rel");
        throw new RuntimeException('Snimanje slike nije uspelo.');
    }
    return [
        'path' => $rel, 'thumb_path' => $relThumb, 'mime' => 'image/jpeg',
        'width' => imagesx($full), 'height' => imagesy($full), 'size' => filesize("$root/$rel"),
    ];
}

/** Smanjuje sliku tako da duža strana bude najviše $max (nikad ne uvećava). Uvek nova slika na beloj pozadini. */
function image_fit(GdImage $src, int $max): GdImage
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // providni PNG → belo
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

function store_pdf(array $f): array
{
    upload_check($f, PDF_MAX_BYTES);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $head = (string) file_get_contents($f['tmp_name'], false, null, 0, 5);
    if ($mime !== 'application/pdf' || $head !== '%PDF-') {
        throw new UploadError('Nalog mora biti PDF fajl.');
    }
    [$rel] = upload_target('pdf');
    if (!move_uploaded_file($f['tmp_name'], upload_root() . '/' . $rel)) {
        throw new RuntimeException('Snimanje PDF-a nije uspelo.');
    }
    return [
        'path' => $rel, 'thumb_path' => null, 'mime' => 'application/pdf',
        'width' => null, 'height' => null, 'size' => (int) $f['size'],
    ];
}

/** Upisuje sačuvan fajl u tabelu files. */
function insert_file(int $projectId, ?int $itemId, string $kind, array $stored, int $userId): int
{
    q(
        'INSERT INTO files (project_id, item_id, kind, path, thumb_path, mime, width, height, size, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$projectId, $itemId, $kind, $stored['path'], $stored['thumb_path'], $stored['mime'],
         $stored['width'], $stored['height'], $stored['size'], $userId]
    );
    return (int) db()->lastInsertId();
}

/** Briše fajlove sa diska (za sačuvane podatke ili red iz tabele files). */
function delete_stored(array $f): void
{
    $root = upload_root();
    foreach (['path', 'thumb_path'] as $k) {
        if (!empty($f[$k]) && !str_contains($f[$k], '..')) {
            @unlink($root . '/' . $f[$k]);
        }
    }
}

function file_url(int $id, bool $thumb = false): string
{
    return rtrim((string) config('base_url', ''), '/') . '/file.php?id=' . $id . ($thumb ? '&t=1' : '');
}

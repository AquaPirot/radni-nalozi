<?php
declare(strict_types=1);

// Automatska nadogradnja baze. Pokreće se pri prvom zahtevu posle ažuriranja fajlova
// (i iz install.php). Verzija se čuva u settings.schema_version.
// Svaka migracija je fajl migrations/NNN_ime.php koji vraća function(PDO $pdo): void.

const SCHEMA_TARGET = 4;

function schema_version(): int
{
    try {
        return (int) (q_val("SELECT `value` FROM settings WHERE `key` = 'schema_version'") ?? 1);
    } catch (PDOException $ex) {
        if ($ex->getCode() === '42S02') {
            return 0; // nema tabela (još nije instalirano)
        }
        throw $ex; // npr. pogrešna lozinka baze — ne sme da se proguta
    }
}

function migrate_if_needed(): void
{
    $v = schema_version();
    if ($v === 0 || $v >= SCHEMA_TARGET) {
        return;
    }
    // Samo jedan zahtev radi nadogradnju; ostali čekaju do 60 s
    if ((int) q_val("SELECT GET_LOCK('montaza_migrate', 60)") !== 1) {
        http_response_code(503);
        exit('Aplikacija se ažurira — pokušaj ponovo za minut.');
    }
    try {
        $v = schema_version();
        foreach (glob(APP_DIR . '/migrations/*.php') as $file) {
            $n = (int) basename($file);
            if ($n <= $v || $n > SCHEMA_TARGET) {
                continue;
            }
            $fn = require $file;
            error_log("migracija $n: počinje");
            $fn(db());
            setting_set('schema_version', (string) $n);
            q("INSERT INTO activity_log (action, details) VALUES ('migration', ?)", [json_encode(['version' => $n])]);
            error_log("migracija $n: gotova");
        }
    } finally {
        q("SELECT RELEASE_LOCK('montaza_migrate')");
    }
}

/** Da li kolona postoji (za migracije koje se mogu ponoviti). */
function column_exists(string $table, string $column): bool
{
    return (bool) q_val(
        'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        [$table, $column]
    );
}

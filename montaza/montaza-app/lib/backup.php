<?php
declare(strict_types=1);

// Rezervna kopija baze bez mysqldump-a (radi na svakom hostingu): montaza-app/backups/montaza-YYYYMMDD-HHMM.sql.gz
// Vraćanje: phpMyAdmin → Import → izabrati .sql.gz fajl.

function backup_dir(): string
{
    $dir = APP_DIR . '/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

/** Pravi kopiju i briše starije od poslednjih $keep. Vraća ime fajla ili null. */
function backup_database(int $keep = 14): ?string
{
    try {
        $dir = backup_dir();
        $name = 'montaza-' . date('Ymd-Hi') . '.sql.gz';
        $tmp = $dir . '/.' . $name . '.tmp';
        $gz = gzopen($tmp, 'wb6');
        if (!$gz) {
            throw new RuntimeException("Ne mogu da pišem u $dir");
        }
        $pdo = db();
        gzwrite($gz, "-- AG Montaža rezervna kopija " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
        foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$table]) {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
            gzwrite($gz, "DROP TABLE IF EXISTS `$table`;\n$create;\n\n");
            $stmt = $pdo->query("SELECT * FROM `$table`", PDO::FETCH_NUM);
            $batch = [];
            $flush = function () use (&$batch, $gz, $table) {
                if ($batch) {
                    gzwrite($gz, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            };
            while ($row = $stmt->fetch()) {
                $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
                if (count($batch) >= 200) {
                    $flush();
                }
            }
            $flush();
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
        gzclose($gz);
        rename($tmp, $dir . '/' . $name);

        $all = glob($dir . '/montaza-*.sql.gz') ?: [];
        rsort($all);
        foreach (array_slice($all, $keep) as $old) {
            @unlink($old);
        }
        return $name;
    } catch (Throwable $ex) {
        error_log('backup: ' . $ex->getMessage());
        if (isset($tmp) && is_file($tmp)) {
            @unlink($tmp);
        }
        return null;
    }
}

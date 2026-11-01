#!/usr/bin/env bash
# Pravi ZIP za cPanel: dist/montaza-YYYYMMDD.zip
# Raspakuje se DIREKTNO u folder subdomena; montaza-app/ unutra je zaštićen .htaccess-om.
# Zatim se otvori https://montaza.aggroup.rs/install.php i sve ostalo uradi instalacija.
set -euo pipefail
cd "$(dirname "$0")"
OUT="dist/montaza-$(date +%Y%m%d).zip"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/root" dist
cp -r public/. "$TMP/root/"
cp -r montaza-app "$TMP/root/montaza-app"
cp README-INSTALACIJA.md "$TMP/root/"

# Nikad u ZIP: lična podešavanja, logovi, slike kupaca
rm -f  "$TMP/root/montaza-app/config.php"
rm -rf "$TMP/root/montaza-app/logs"
find "$TMP/root/uploads" -mindepth 1 ! -name '.htaccess' -exec rm -rf {} +

rm -f dist/montaza-*.zip
(cd "$TMP/root" && zip -qr -X "$OLDPWD/$OUT" . -x '*.DS_Store')
echo "$OUT ($(du -h "$OUT" | cut -f1))"

# SQL za phpMyAdmin: briše SVE tabele u izabranoj bazi i pravi Montažine tabele
SQL="dist/montaza-baza.sql"
{
cat <<'HDR'
-- MONTAŽA — čista baza za phpMyAdmin
--
-- PAŽNJA: briše SVE tabele u bazi koja je izabrana levo u phpMyAdmin-u
-- (stare tabele i podatke), pa pravi tabele nove aplikacije.
-- phpMyAdmin → levo klikni bazu (npr. aggroup_montaza) → Import → ovaj fajl → Go.
-- Nalozi se NE prave ovde: otvori sajt posle importa i instalacija će ih napraviti.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SESSION group_concat_max_len = 1000000;

-- 1) Obriši sve postojeće prikaze (view) i tabele u ovoj bazi
SET @v = (SELECT GROUP_CONCAT(CONCAT('`', table_name, '`')) FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_type = 'VIEW');
SET @q = IF(@v IS NULL, 'DO 0', CONCAT('DROP VIEW ', @v));
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SET @t = (SELECT GROUP_CONCAT(CONCAT('`', table_name, '`')) FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE');
SET @q = IF(@t IS NULL, 'DO 0', CONCAT('DROP TABLE ', @t));
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Tabele nove aplikacije
HDR
grep -v '^SET NAMES\|^SET FOREIGN_KEY_CHECKS' montaza-app/schema.sql | sed '/^-- MONTAŽA — šema baze/,/^-- Uvoz:/d'
echo
echo 'SET FOREIGN_KEY_CHECKS = 1;'
} > "$SQL"
echo "$SQL ($(du -h "$SQL" | cut -f1))"

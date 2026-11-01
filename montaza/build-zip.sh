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

#!/usr/bin/env bash
# Pravi ZIP za cPanel upload: dist/montaza-YYYYMMDD.zip
# Sadrži public/, montaza-app/ (bez config.php, logova i upload-ovanih fajlova) i README-INSTALACIJA.md.
set -euo pipefail
cd "$(dirname "$0")"
OUT="dist/montaza-$(date +%Y%m%d).zip"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/montaza" dist
cp -r public "$TMP/montaza/public"
cp -r montaza-app "$TMP/montaza/montaza-app"
cp README-INSTALACIJA.md "$TMP/montaza/"

# Nikad u ZIP: lična podešavanja, logovi, slike kupaca
rm -f  "$TMP/montaza/montaza-app/config.php"
rm -rf "$TMP/montaza/montaza-app/logs"
find "$TMP/montaza/public/uploads" -mindepth 1 ! -name '.htaccess' -exec rm -rf {} +

rm -f "$OUT"
(cd "$TMP/montaza" && zip -qr -X "$OLDPWD/$OUT" . -x '*.DS_Store')
echo "$OUT ($(du -h "$OUT" | cut -f1))"

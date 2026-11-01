#!/usr/bin/env bash
# 8 scenarija iz predloga v2 — prolazi kroz prave ekrane (HTTP) i proverava bazu.
# Pokretanje (lokalno, probna baza "montaza", korisnici aleksandar/oliver/steva sa PIN 1111/2222/3333):
#   B=http://127.0.0.1:8099 DB=montaza bash tests/scenariji.sh
set -u
B=${B:-http://127.0.0.1:8099}
DB=${DB:-montaza}
DIR=$(cd "$(dirname "$0")" && pwd)
W=$(mktemp -d)
cd "$W" || exit 1
PASS=0; FAIL=0

# ── pomoćne ──
sql() { mysql --default-character-set=utf8mb4 -N "$DB" -e "$1"; }
ok() { if [ "$2" = "$3" ]; then echo "  ✓ $1"; PASS=$((PASS+1)); else echo "  ✗ $1 — očekivano [$3], dobijeno [$2]"; FAIL=$((FAIL+1)); fi; }
tok() { grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login() { local T; T=$(curl -s -c "$1" "$B/?p=login" | tok); curl -s -b "$1" -c "$1" -o /dev/null "$B/?p=login" --data-urlencode "_csrf=$T" -d "username=$2&password=$3"; }
csrf() { curl -s -b "$1" "$B/?p=danas" | tok; }
up() { curl -s -b "$1" "$B/api/upload.php" -H "X-CSRF-Token: $(csrf "$1")" -F "file=@$2" -F "type=${3:-foto}" | python3 -c 'import sys,json; print(json.load(sys.stdin)["id"])'; }
# act KOLAČIĆ akcija id [dodatna -d polja…] → ispisuje tekst poruke (flash)
act() {
  local c=$1 a=$2 id=$3; shift 3
  curl -s -b "$c" -c "$c" -o /dev/null "$B/api/akcija.php" --data-urlencode "_csrf=$(csrf "$c")" -d "action=$a" -d "id=$id" --data-urlencode "back=/?p=danas" "$@"
  curl -s -b "$c" "$B/?p=danas" | grep -oE 'class="alert (ok|warn)">[^<]*' | head -1 | sed 's/class="alert //'
}
# post KOLAČIĆ "?p=…" [polja…] → ispisuje adresu preusmerenja ili poruku greške
post() {
  local c=$1 q=$2; shift 2
  local T; T=$(curl -s -b "$c" "$B/$q" | tok)
  curl -s -b "$c" -c "$c" -o page.html -w '%{redirect_url}' "$B/$q" --data-urlencode "_csrf=$T" "$@"
  grep -oE '(alert warn" role="alert"|field-err")>[^<]*' page.html | head -2 | sed 's/.*>//'
}
php -r '$i = imagecreatetruecolor(800, 600); imagefill($i, 0, 0, imagecolorallocate($i, 40, 120, 200)); imagejpeg($i, "f.jpg");'
printf '%%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%%%EOF\n' > n.pdf
sql "DELETE FROM login_attempts"
login v.txt aleksandar 1111; login h.txt oliver 2222; login t.txt steva 3333
TEAM=$(sql "SELECT id FROM teams WHERE active=1 ORDER BY id LIMIT 1")
type_id() { sql "SELECT id FROM system_types WHERE name='$1'"; }
D() { date -d "$1" +%F; }
TAG=$(date +%H%M%S)

# napravi i pusti projekat: new_project IME iznos [polja sistema…]
new_project() {
  local name=$1 amount=$2; shift 2
  local L N; L=$(up v.txt f.jpg); N=$(up v.txt n.pdf pdf)
  post v.txt "?p=novi" -d "mode=release&work_type=novi&intake=novi" --data-urlencode "customer_name=$name" \
    -d "city=Pirot&phone=0641234567&address=Test 1&deadline=$(D '+30 days')&payment_when=posle&payment_amount=$amount&payment_collector=teren" \
    -d "lokacija_id[]=$L&nalog_id[]=$N" "$@" >/dev/null
  sql "SELECT id FROM projects WHERE customer_name='$name' ORDER BY id DESC LIMIT 1"
}
# hala preuzme nalog i završi sve faze (sa slikom)
produce_all() {
  local pid=$1 oid ph
  oid=$(sql "SELECT id FROM project_orders WHERE project_id=$pid ORDER BY version DESC LIMIT 1")
  act h.txt order_accept "$oid" >/dev/null
  for ph in $(sql "SELECT id FROM production_phases WHERE project_id=$pid AND status<>'zavrseno' ORDER BY system_id, sort"); do
    post h.txt "?p=faza&id=$ph" -d "foto_ids[]=$(up h.txt f.jpg)" -d "note=ok" >/dev/null
  done
}
schedule() { # schedule PID datum dana [polja…] → id termina
  local pid=$1 d=$2 days=$3; shift 3
  post v.txt "?p=termin&project=$pid" -d "kind=montaza&team_id=$TEAM&start_date=$d&start_time=08:00&days=$days&confirmed=1" "$@" >/dev/null
  sql "SELECT id FROM visits WHERE project_id=$pid ORDER BY id DESC LIMIT 1"
}
finish() { # finish VID rezultat [polja…]
  local vid=$1 r=$2; shift 2
  post t.txt "?p=teren_zavrsi&id=$vid" -d "result=$r" -d "celina[]=$(up t.txt f.jpg)&celina[]=$(up t.txt f.jpg)&detalj[]=$(up t.txt f.jpg)" "$@"
}
BIO=$(type_id BIOFIX); ZIP=$(type_id 'ZIP screen'); KLA=$(type_id 'Klasična pergola'); STA=$(type_id Slajding)

echo "1. Jedna pergola, jedna ekipa, završetak u jednom danu"
P1=$(new_project "S1 Jovanović $TAG" 3000 -d "systems[a][type_id]=$KLA&systems[a][qty]=1&systems[a][width]=4&systems[a][length]=3")
ok "pušten u realizaciju" "$(sql "SELECT status FROM projects WHERE id=$P1")" "aktivan"
produce_all "$P1"
ok "sve faze završene" "$(sql "SELECT SUM(status<>'zavrseno') FROM production_phases WHERE project_id=$P1")" "0"
V1=$(schedule "$P1" "$(D '+2 days')" 1)
ok "termin potvrđen bez izuzetka (spremno)" "$(sql "SELECT CONCAT(confirmed, IFNULL(readiness_override,'-')) FROM visits WHERE id=$V1")" "1-"
act t.txt visit_start "$V1" >/dev/null
finish "$V1" celo -d "video=1&handover=1&paid=3000&method=gotovina" >/dev/null
ok "termin završen u celosti" "$(sql "SELECT CONCAT(status,'/',result) FROM visits WHERE id=$V1")" "zavrseno/celo"
ok "projekat završen" "$(sql "SELECT status FROM projects WHERE id=$P1")" "zavrsen"
ok "naplata evidentirana" "$(sql "SELECT ROUND(SUM(amount)) FROM payments WHERE project_id=$P1")" "3000"

echo "2. Projekat sa pergolom, ZIP-ovima i staklenim sistemom"
P2=$(new_project "S2 Mitić $TAG" 9000 \
  -d "systems[a][type_id]=$BIO&systems[a][qty]=1&systems[a][width]=5&systems[a][length]=4&systems[a][ral]=RAL 7016&systems[a][spec][rasveta]=LED" \
  -d "systems[b][type_id]=$ZIP&systems[b][qty]=3&systems[b][width]=3&systems[b][height]=2,5" \
  -d "systems[c][type_id]=$STA&systems[c][qty]=2&systems[c][width]=4&systems[c][height]=2,2&systems[c][spec][broj_krila]=4")
ok "3 sistema, ukupno 6 komada" "$(sql "SELECT CONCAT(COUNT(*),'/',SUM(qty)) FROM project_systems WHERE project_id=$P2")" "3/6"
ok "svaki sistem ima svoje faze" "$(sql "SELECT COUNT(DISTINCT system_id) FROM production_phases WHERE project_id=$P2")" "3"
ok "dimenzije u metrima (2,5 → 2.50)" "$(sql "SELECT height FROM project_systems WHERE project_id=$P2 AND system_type_id=$ZIP")" "2.50"
curl -s -b v.txt "$B/?p=projekat&id=$P2" > p2.html
ok "kartica projekta prikazuje sisteme" "$(grep -c '3× ZIP screen' p2.html | awk '{print ($1>0)}')" "1"

echo "3. Montaža više dana, delimično završena"
produce_all "$P2"
V3=$(schedule "$P2" "$(D '+3 days')" 3)
ok "termin traje 3 dana" "$(sql "SELECT days FROM visits WHERE id=$V3")" "3"
act t.txt visit_start "$V3" >/dev/null
R=$(finish "$V3" delimicno -d "debt_ok=1")
ok "delimično bez opisa ostatka → odbijeno" "$(sql "SELECT status FROM visits WHERE id=$V3")" "u_toku"
finish "$V3" delimicno -d "remaining=fale 2 ZIP-a&debt_ok=1&nedostatak[]=$(up t.txt f.jpg)" >/dev/null
ok "termin delimično završen" "$(sql "SELECT result FROM visits WHERE id=$V3")" "delimicno"
ok "napravljen nastavak (dorada) vezan za termin" "$(sql "SELECT COUNT(*) FROM visits WHERE parent_visit_id=$V3 AND kind='dorada' AND status='planiran'")" "1"
ok "projekat NIJE završen" "$(sql "SELECT status FROM projects WHERE id=$P2")" "aktivan"
ok "dorada traži odluku vlasnika na Danas" "$(curl -s -b v.txt "$B/?p=pregled&f=odluka" | grep -c "S2 Mitić $TAG" | awk '{print ($1>0)}')" "1"

echo "4. Zastoj u materijalu pomera dogovoreni termin"
P4=$(new_project "S4 Ilić $TAG" 4000 -d "systems[a][type_id]=$KLA&systems[a][qty]=1&systems[a][width]=4&systems[a][length]=3")
V4=$(schedule "$P4" "$(D '+4 days')" 1 -d "override=dogovoreno sa kupcem")
OID=$(sql "SELECT id FROM project_orders WHERE project_id=$P4 LIMIT 1"); act h.txt order_accept "$OID" >/dev/null
PH=$(sql "SELECT id FROM production_phases WHERE project_id=$P4 ORDER BY sort LIMIT 1")
post h.txt "?p=zastoj&phase=$PH" -d "reason=materijal&note=čeka platno&owner_id=$(sql "SELECT id FROM users WHERE username='oliver'")&next_step=zvati dobavljača&expected_date=$(D '+10 days')" >/dev/null
ok "faza u zastoju" "$(sql "SELECT status FROM production_phases WHERE id=$PH")" "zastoj"
ok "zastoj ugrožava termin (vidljivo vlasniku)" "$(curl -s -b v.txt "$B/?p=pregled&f=zastoj" | grep -c 'čeka platno — zvati dobavljača — odgovoran Oliver — ugrožen termin montaže')" "1"
R=$(post v.txt "?p=termin&project=$P4&id=$V4" -d "kind=montaza&team_id=$TEAM&start_date=$(D '+12 days')&start_time=08:00&days=1")
ok "pomeranje bez razloga → odbijeno" "$(sql "SELECT start_date FROM visits WHERE id=$V4")" "$(D '+4 days')"
post v.txt "?p=termin&project=$P4&id=$V4" -d "kind=montaza&team_id=$TEAM&start_date=$(D '+12 days')&start_time=08:00&days=1&reason=kasni platno" >/dev/null
ok "termin pomeren" "$(sql "SELECT start_date FROM visits WHERE id=$V4")" "$(D '+12 days')"
ok "razlog upisan u istoriju" "$(sql "SELECT COUNT(*) FROM activity_log WHERE project_id=$P4 AND action='visit_updated' AND details LIKE '%kasni platno%'")" "1"
ok "pomeren termin više nije potvrđen" "$(sql "SELECT confirmed FROM visits WHERE id=$V4")" "0"

echo "5. Reklamacija povezana sa završenim projektom"
post v.txt "?p=novi" -d "mode=release&work_type=reklamacija&intake=novi&parent_project_id=$P1" --data-urlencode "customer_name=S1 Jovanović $TAG" \
  -d "city=Pirot&deadline=$(D '+7 days')&payment_when=ne&payment_collector=teren&description=curi oluk" >/dev/null
P5=$(sql "SELECT id FROM projects WHERE work_type='reklamacija' AND parent_project_id=$P1 ORDER BY id DESC LIMIT 1")
ok "reklamacija napravljena i vezana" "$( [ -n "$P5" ] && echo 1 || echo 0)" "1"
ok "reklamacija aktivna bez naplate" "$(sql "SELECT CONCAT(status,'/',payment_when) FROM projects WHERE id=$P5")" "aktivan/ne"
ok "u filteru Reklamacije" "$(curl -s -b v.txt "$B/?p=projekti&f=reklamacije" | grep -c "S1 Jovanović $TAG" | awk '{print ($1>0)}')" "1"
ok "originalni projekat prikazuje reklamaciju" "$(curl -s -b v.txt "$B/?p=projekat&id=$P1" | grep -c 'Reklamacija <span' | awk '{print ($1>0)}')" "1"

echo "6. Delimična naplata i naknadna predaja novca"
P6=$(new_project "S6 Petković $TAG" 5000 -d "systems[a][type_id]=$KLA&systems[a][qty]=1&systems[a][width]=4&systems[a][length]=3")
produce_all "$P6"
V6=$(schedule "$P6" "$(D '+5 days')" 1)
act t.txt visit_start "$V6" >/dev/null
R=$(finish "$V6" celo -d "video=1&handover=1&paid=2000&method=gotovina")
ok "manje od dogovorenog bez potvrde duga → odbijeno" "$(sql "SELECT status FROM visits WHERE id=$V6")" "u_toku"
finish "$V6" celo -d "video=1&handover=1&paid=2000&method=gotovina&debt_ok=1" >/dev/null
ok "uplata 2.000 primljena (Steva)" "$(sql "SELECT CONCAT(ROUND(amount),'/',received_by) FROM payments WHERE project_id=$P6")" "2000/$(sql "SELECT id FROM users WHERE username='steva'")"
ok "dug ostaje 3.000 — za naplatu" "$(curl -s -b v.txt "$B/?p=pregled&f=naplata" | grep -c "3.000 €" | awk '{print ($1>0)}')" "1"
ok "gotovina kod radnika, nije predata" "$(sql "SELECT handed_at IS NULL FROM payments WHERE project_id=$P6")" "1"
PAY=$(sql "SELECT id FROM payments WHERE project_id=$P6")
act t.txt cash_handover 0 -d "ids[]=$PAY" >/dev/null
ok "Steva ne može sam da potvrdi predaju" "$(sql "SELECT handed_at IS NULL FROM payments WHERE id=$PAY")" "1"
act v.txt cash_handover 0 -d "ids[]=$PAY" >/dev/null
ok "vlasnik potvrdio predaju" "$(sql "SELECT handed_at IS NOT NULL FROM payments WHERE id=$PAY")" "1"
act v.txt payment_add "$P6" -d "amount=3000&method=racun" >/dev/null
ok "ostatak uplaćen na račun → naplaćeno" "$(sql "SELECT ROUND(SUM(amount)) FROM payments WHERE project_id=$P6")" "5000"
ok "više nije za naplatu" "$(curl -s -b v.txt "$B/?p=pregled&f=naplata" | grep -c "S6 Petković $TAG")" "0"

echo "7. Slaba veza tokom dodavanja fotografija"
F1=$(up t.txt f.jpg)
ok "slika poslata odmah i čeka (pending)" "$(sql "SELECT pending FROM files WHERE id=$F1")" "1"
P7=$(new_project "S7 Stanković $TAG" 1000 -d "systems[a][type_id]=$KLA&systems[a][qty]=1&systems[a][width]=3&systems[a][length]=3")
produce_all "$P7"; V7=$(schedule "$P7" "$(D '+6 days')" 1); act t.txt visit_start "$V7" >/dev/null
T=$(curl -s -b t.txt "$B/?p=teren_zavrsi&id=$V7" | tok)
for i in 1 2; do  # isti obrazac poslat dvaput (dupli klik / ponovni pokušaj)
  curl -s -b t.txt -c t.txt -o /dev/null "$B/?p=teren_zavrsi&id=$V7" --data-urlencode "_csrf=$T" -d "_once=ponovi-$TAG" \
    -d "result=celo&video=1&handover=1&paid=1000&method=gotovina&celina[]=$F1&celina[]=$(up t.txt f.jpg)&detalj[]=$(up t.txt f.jpg)"
done
ok "slika zakačena za termin" "$(sql "SELECT CONCAT(pending,'/',visit_id) FROM files WHERE id=$F1")" "0/$V7"
ok "dvostruko slanje ne pravi duplu uplatu" "$(sql "SELECT COUNT(*) FROM payments WHERE project_id=$P7")" "1"
F2=$(up t.txt f.jpg)
ok "tuđa neposlata slika se ne vidi (oliver)" "$(curl -s -b h.txt -o /dev/null -w '%{http_code}' "$B/file.php?id=$F2")" "404"
ok "neposlata slika se ne kači tuđem obrascu" "$(post h.txt "?p=faza&id=0" -d "foto_ids[]=$F2" >/dev/null; sql "SELECT pending FROM files WHERE id=$F2")" "1"

echo "8. Izmena radnog naloga nakon što ga je hala preuzela"
P8=$(new_project "S8 Nikolić $TAG" 2000 -d "systems[a][type_id]=$KLA&systems[a][qty]=1&systems[a][width]=4&systems[a][length]=3")
O1=$(sql "SELECT id FROM project_orders WHERE project_id=$P8 ORDER BY version LIMIT 1")
act h.txt order_accept "$O1" >/dev/null
PH8=$(sql "SELECT id FROM production_phases WHERE project_id=$P8 ORDER BY sort LIMIT 1")
act h.txt phase_start "$PH8" >/dev/null
ok "hala radi po v1" "$(sql "SELECT status FROM production_phases WHERE id=$PH8")" "u_toku"
act v.txt order_issue "$P8" -d "file_id[]=$(up v.txt n.pdf pdf)" -d "note=promenjena širina 4,2 m" >/dev/null
ok "izdata v2" "$(sql "SELECT MAX(version) FROM project_orders WHERE project_id=$P8")" "2"
ok "v2 nije preuzeta" "$(sql "SELECT accepted_at IS NULL FROM project_orders WHERE project_id=$P8 AND version=2")" "1"
ok "hala na Danas vidi nalog za preuzimanje" "$(curl -s -b h.txt "$B/?p=danas" | grep -c "S8 Nikolić $TAG" | awk '{print ($1>0)}')" "1"
PH8b=$(sql "SELECT id FROM production_phases WHERE project_id=$P8 AND status='ceka' ORDER BY sort LIMIT 1")
R=$(act h.txt phase_start "$PH8b")
ok "sledeća faza blokirana dok se v2 ne preuzme" "$(sql "SELECT status FROM production_phases WHERE id=$PH8b")" "ceka"
act h.txt order_accept "$O1" >/dev/null
ok "stara verzija ne može da se preuzme" "$(sql "SELECT accepted_at IS NULL FROM project_orders WHERE project_id=$P8 AND version=2")" "1"
O2=$(sql "SELECT id FROM project_orders WHERE project_id=$P8 AND version=2")
act h.txt order_accept "$O2" >/dev/null
act h.txt phase_start "$PH8b" >/dev/null
ok "posle preuzimanja v2 rad se nastavlja" "$(sql "SELECT status FROM production_phases WHERE id=$PH8b")" "u_toku"
ok "istorija beleži izdavanje i preuzimanje" "$(sql "SELECT COUNT(*) FROM activity_log WHERE project_id=$P8 AND action IN ('order_issued','order_accepted')")" "4"

echo
echo "Rezultat: $PASS prošlo, $FAIL palo"
rm -rf "$W"
[ "$FAIL" -eq 0 ]

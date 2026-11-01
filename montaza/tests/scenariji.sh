#!/usr/bin/env bash
# Scenariji za v3 („jednostavno") — prolazi kroz prave ekrane (HTTP) i proverava bazu.
# Pokretanje (lokalno, baza "montaza", korisnici aleksandar/oliver/steva sa PIN 1111/2222/3333):
#   B=http://127.0.0.1:8099 DB=montaza bash tests/scenariji.sh
set -u
B=${B:-http://127.0.0.1:8099}
DB=${DB:-montaza}
W=$(mktemp -d)
cd "$W" || exit 1
PASS=0; FAIL=0

sql() { mysql --default-character-set=utf8mb4 -N "$DB" -e "$1"; }
ok() { if [ "$2" = "$3" ]; then echo "  ✓ $1"; PASS=$((PASS+1)); else echo "  ✗ $1 — očekivano [$3], dobijeno [$2]"; FAIL=$((FAIL+1)); fi; }
has() { if [ "$2" -gt 0 ]; then echo "  ✓ $1"; PASS=$((PASS+1)); else echo "  ✗ $1 — nije pronađeno"; FAIL=$((FAIL+1)); fi; }
tok() { grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login() { local T; T=$(curl -s -c "$1" "$B/?p=login" | tok); curl -s -b "$1" -c "$1" -o /dev/null "$B/?p=login" --data-urlencode "_csrf=$T" -d "username=$2&password=$3"; }
csrf() { curl -s -b "$1" "$B/?p=projekti" | tok; }
up() { curl -s -b "$1" "$B/api/upload.php" -H "X-CSRF-Token: $(csrf "$1")" -F "file=@$2" -F "type=${3:-foto}" | python3 -c 'import sys,json; print(json.load(sys.stdin)["id"])'; }
act() { local c=$1 a=$2 id=$3; shift 3; curl -s -b "$c" -c "$c" -o /dev/null "$B/api/akcija.php" --data-urlencode "_csrf=$(csrf "$c")" -d "action=$a" -d "id=$id" --data-urlencode "back=/?p=projekti" "$@"; }
post() { local c=$1 q=$2; shift 2; local T; T=$(curl -s -b "$c" "$B/$q" | tok); curl -s -b "$c" -c "$c" -o page.html -w '%{redirect_url}' "$B/$q" --data-urlencode "_csrf=$T" "$@"; }
page() { curl -s -b "$1" "$B/?p=$2"; }
php -r '$i = imagecreatetruecolor(800, 600); imagefill($i, 0, 0, imagecolorallocate($i, 40, 120, 200)); imagejpeg($i, "f.jpg");'
printf '%%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%%%EOF\n' > n.pdf
sql "DELETE FROM login_attempts"
login v.txt aleksandar 1111; login h.txt oliver 2222; login t.txt steva 3333
TAG=$(date +%H%M%S); D() { date -d "$1" +%F; }
item() { sql "SELECT id FROM project_items WHERE project_id=$1 AND kind='$2'"; }
done_item() { # done_item KOLAČIĆ ITEM brojSlika
  local c=$1 it=$2 n=$3 f=""; for i in $(seq 1 "$n"); do f="$f&foto_ids[]=$(up "$c" f.jpg)"; done
  post "$c" "?p=stavka&id=$it" -d "note=ok${f}" >/dev/null
}

echo "1. Vlasnik otvara projekat: kupac, telefon, adresa, dan montaže, PDF nalog, slika lokacije"
N=$(up v.txt n.pdf pdf); L=$(up v.txt f.jpg)
post v.txt "?p=novi" -d "type=novi" --data-urlencode "customer_name=Petrović $TAG" -d "phone=064 123 4567&city=Pirot" --data-urlencode "address=Kneza Miloša 10" \
  -d "install_date=$(D '+3 days')&install_time=08:30&nalog_id[]=$N&lokacija_id[]=$L" \
  --data-urlencode "works[]=Čelik" --data-urlencode "works[]=Aluminijum" --data-urlencode "works[]=Cerada" --data-urlencode "works[]=Pakovanje" >/dev/null
P=$(sql "SELECT id FROM projects WHERE customer_name='Petrović $TAG'")
ok "projekat otvoren" "$( [ -n "$P" ] && echo 1)" "1"
ok "stavke kao v1 (sa čelikom)" "$(sql "SELECT GROUP_CONCAT(kind ORDER BY sort) FROM project_items WHERE project_id=$P")" "nalog,celik,aluminijum,cerada,pakovanje,montaza"
ok "PDF dodat → stavka Nalog završena" "$(sql "SELECT status FROM project_items WHERE project_id=$P AND kind='nalog'")" "zavrseno"
ok "dan i vreme montaže" "$(sql "SELECT CONCAT(install_date,' ',install_time) FROM projects WHERE id=$P")" "$(D '+3 days') 08:30:00"
ok "slika lokacije (PRE)" "$(sql "SELECT COUNT(*) FROM files WHERE project_id=$P AND kind='lokacija_pre'")" "1"
has "u spisku projekata, grupa po danu montaže" "$(page h.txt projekti | grep -c "Petrović $TAG")"
has "na Montažama: telefon i adresa" "$(page t.txt montaze | grep -A12 "Petrović $TAG" | grep -c 'tel:0641234567\|Kneza Miloša 10')"

echo "2. Hala radi stavke jednim dodirom (slika nije potrebna), teren ne može hala-stavke"
AL=$(item "$P" aluminijum); CE=$(item "$P" celik)
act t.txt item_done "$CE"
ok "teren ne može da završi čelik" "$(sql "SELECT status FROM project_items WHERE id=$CE")" "ceka"
act h.txt item_start "$CE"
ok "hala počinje čelik" "$(sql "SELECT status FROM project_items WHERE id=$CE")" "u_toku"
act h.txt item_done "$CE"
ok "čelik završen bez slike" "$(sql "SELECT status FROM project_items WHERE id=$CE")" "zavrseno"
for k in aluminijum cerada pakovanje; do act h.txt item_done "$(item "$P" $k)"; done
ok "sve hala-stavke završene" "$(sql "SELECT COUNT(*) FROM project_items WHERE project_id=$P AND responsible='hala' AND status<>'zavrseno'")" "0"
has "status: Spremno za montažu" "$(page v.txt "projekat&id=$P" | grep -c 'Spremno za montažu')"

echo "3. Zastoj i komentar — svi vide"
act h.txt stop_open "$P" -d "reason=materijal" --data-urlencode "note=čeka platno $TAG"
has "zastoj na kartici (vidi teren)" "$(page t.txt projekti | grep -c "čeka platno $TAG")"
act t.txt stop_resolve "$P"
ok "zastoj rešen" "$(sql "SELECT COUNT(*) FROM stoppages WHERE project_id=$P AND resolved_at IS NULL")" "0"
act t.txt project_comment "$P" --data-urlencode "body=Kupac traži da dođemo posle 9h $TAG"
has "komentar vidi hala" "$(page h.txt "projekat&id=$P" | grep -c "posle 9h $TAG")"

echo "4. Datum montaže menja samo vlasnik"
act t.txt install "$P" -d "install_date=$(D '+9 days')"
ok "teren ne menja datum" "$(sql "SELECT install_date FROM projects WHERE id=$P")" "$(D '+3 days')"
act v.txt install "$P" -d "install_date=$(D '+4 days')&install_time=09:15"
ok "vlasnik pomera montažu" "$(sql "SELECT CONCAT(install_date,' ',install_time) FROM projects WHERE id=$P")" "$(D '+4 days') 09:15:00"
has "istorija beleži pomeranje" "$(sql "SELECT COUNT(*) FROM activity_log WHERE project_id=$P AND action='install_set'")"

echo "5. Teren završava montažu (najmanje 3 slike) → projekat završen"
MO=$(item "$P" montaza)
done_item h.txt "$MO" 3
ok "hala ne može da završi montažu" "$(sql "SELECT status FROM project_items WHERE id=$MO")" "ceka"
done_item t.txt "$MO" 2
ok "sa 2 slike se ne završava" "$(sql "SELECT status FROM project_items WHERE id=$MO")" "ceka"
done_item t.txt "$MO" 3
ok "montaža završena" "$(sql "SELECT status FROM project_items WHERE id=$MO")" "zavrseno"
ok "projekat završen" "$(sql "SELECT status FROM projects WHERE id=$P")" "zavrsen"
ok "slike POSLE zakačene" "$(sql "SELECT COUNT(*) FROM files WHERE item_id=$MO AND kind='posle'")" "3"
ok "nestao sa Montaža" "$(page t.txt montaze | grep -c "Petrović $TAG")" "0"
has "u Završenim" "$(page v.txt "projekti&f=zavrseni" | grep -c "Petrović $TAG")"
has "u izveštaju" "$(page h.txt izvestaj | grep -c "Petrović $TAG")"

echo "6. Reklamacija: opis + slika problema, jedna stavka Servis"
post v.txt "?p=novi" -d "type=reklamacija&parent_project_id=$P" --data-urlencode "customer_name=Petrović $TAG" -d "city=Pirot" >/dev/null
ok "bez opisa problema → odbijeno" "$(sql "SELECT COUNT(*) FROM projects WHERE parent_project_id=$P")" "0"
PR=$(up v.txt f.jpg)
post v.txt "?p=novi" -d "type=reklamacija&parent_project_id=$P" --data-urlencode "customer_name=Petrović $TAG" -d "city=Pirot&problem_id[]=$PR" --data-urlencode "description=curi kod oluka" >/dev/null
R=$(sql "SELECT id FROM projects WHERE parent_project_id=$P")
ok "reklamacija ima samo Servis" "$(sql "SELECT GROUP_CONCAT(kind ORDER BY sort) FROM project_items WHERE project_id=$R")" "servis"
ok "slika problema" "$(sql "SELECT CONCAT(kind,'/',purpose) FROM files WHERE id=$PR")" "foto/nedostatak"
has "status: Reklamacija — čeka servis" "$(page t.txt montaze | grep -c 'Reklamacija — čeka servis\|Petrović')"
has "u filteru Reklamacije" "$(page v.txt "projekti&f=reklamacije" | grep -c "Petrović $TAG")"
has "original pokazuje reklamaciju" "$(page v.txt "projekat&id=$P" | grep -c 'Reklamacija <span')"

echo "7. Slike i nalog kasnije; dupli klik"
F=$(up t.txt f.jpg)
act t.txt photos "$P" -d "foto_ids[]=$F"
ok "slika dodata na projekat" "$(sql "SELECT CONCAT(pending,'/',project_id) FROM files WHERE id=$F")" "0/$P"
F2=$(up t.txt f.jpg)
ok "tuđa neposlata slika se ne vidi" "$(curl -s -b h.txt -o /dev/null -w '%{http_code}' "$B/file.php?id=$F2")" "404"
N2=$(up v.txt n.pdf pdf); act v.txt nalog "$R" -d "file_id[]=$N2"
ok "nalog dodat kasnije" "$(sql "SELECT status FROM project_items WHERE project_id=$R AND kind='nalog'")$(sql "SELECT COUNT(*) FROM files WHERE project_id=$R AND kind='nalog_pdf'")" "1"
T=$(curl -s -b t.txt "$B/?p=projekat&id=$R" | tok)
for i in 1 2; do curl -s -b t.txt -c t.txt -o /dev/null "$B/api/akcija.php" --data-urlencode "_csrf=$T" -d "_once=dupli-$TAG&action=project_comment&id=$R" --data-urlencode "body=dupli $TAG" --data-urlencode "back=/?p=projekti"; done
ok "dupli klik ne pravi dupli komentar" "$(sql "SELECT COUNT(*) FROM project_comments WHERE body='dupli $TAG'")" "1"

echo "8. Stavke po izboru (giljotine bez cerade) i izmena izbora"
post v.txt "?p=novi" -d "type=novi" --data-urlencode "customer_name=Giljotine $TAG" -d "city=Niš" --data-urlencode "works[]=Giljotina" --data-urlencode "works[]=Pakovanje" >/dev/null
G=$(sql "SELECT id FROM projects WHERE customer_name='Giljotine $TAG'")
ok "samo izabrane stavke" "$(sql "SELECT GROUP_CONCAT(COALESCE(label, kind) ORDER BY sort) FROM project_items WHERE project_id=$G")" "nalog,Giljotina,pakovanje,montaza"
act h.txt item_done "$(sql "SELECT id FROM project_items WHERE project_id=$G AND label='Giljotina'")"
post v.txt "?p=izmeni&id=$G" -d "type=novi" --data-urlencode "customer_name=Giljotine $TAG" -d "city=Niš" --data-urlencode "works[]=Staklo" --data-urlencode "works[]=Pakovanje" >/dev/null
ok "izmena: dodato Staklo, završena Giljotina ostaje" "$(sql "SELECT GROUP_CONCAT(COALESCE(label, kind) ORDER BY sort) FROM project_items WHERE project_id=$G")" "nalog,Staklo,Giljotina,pakovanje,montaza"
has "kartica: Giljotina na traci" "$(page v.txt projekti | grep -A8 "Giljotine $TAG" | grep -c 'Giljotina: Završeno')"
G2=$(sql "SELECT id FROM projects WHERE customer_name='Giljotine $TAG'"); T=$(curl -s -b v.txt "$B/?p=izmeni&id=$G2" | tok)
curl -s -b v.txt -c v.txt -o /dev/null "$B/?p=izmeni&id=$G2" --data-urlencode "_csrf=$T" -d "type=novi" --data-urlencode "customer_name=Giljotine $TAG" --data-urlencode "address=https://maps.app.goo.gl/abc123" -d "city=Niš" --data-urlencode "works[]=Staklo"
has "adresa-link: Navigacija vodi na link" "$(page t.txt "projekat&id=$G2" | grep -c 'href="https://maps.app.goo.gl/abc123"')"
ok "link se ne ispisuje kao adresa" "$(page t.txt "projekat&id=$G2" | grep -c '<span>https://maps')" "0"

echo "9. Stare adrese vode na nove ekrane"
ok "?p=danas → projekti" "$(curl -s -b t.txt "$B/?p=danas" | grep -c '<h1 class="h1">Projekti')" "1"
ok "?p=plan → montaže" "$(curl -s -b t.txt "$B/?p=plan" | grep -c '<h1 class="h1">Montaže')" "1"

echo
echo "Rezultat: $PASS prošlo, $FAIL palo"
rm -rf "$W"
[ "$FAIL" -eq 0 ]

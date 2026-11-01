# MONTAŽA — aplikacija za praćenje projekata pergola

**Domen:** https://montaza.aggroup.rs
**Vlasnik:** AG GROUP – ALU PRO, Pirot
**Za:** Claude Code — ovo je kompletno uputstvo. Radi fazu po fazu (dole), posle svake faze stani i pokaži šta je urađeno.

---

## 0. Šta pravimo (ukratko)

Mobilna web aplikacija (PWA) za tri uloge:

| Uloga | Ko | Šta radi |
|---|---|---|
| `vlasnik` | Aleksandar | Ugovara poslove, otvara projekte, kači PDF nalog, planira nedelje, vidi sve i izveštaje |
| `hala` | Šef proizvodnje | Zatvara stavke proizvodnje uz fotografiju, prijavljuje zastoj |
| `teren` | Šef terenske ekipe | Potvrđuje naplatu, zatvara montažu uz slike PRE/POSLE, prijavljuje zastoj |

Svaki **projekat** ima niz **stavki** (izdat nalog → čelična konstrukcija (opciono) → aluminijum → cerada → pakovanje → montaža). Stavka se zatvara jednim klikom uz fotografiju. Tako nastaju: praćenje statusa u realnom vremenu, arhiva svih projekata sa slikama i mesečni izveštaj.

Montaža se **ne planira po danu**, nego po **nedelji** + **krajnji rok**.

---

## 1. Tehnologija — obavezno

- **PHP 8.1+**, **MySQL 8 / MariaDB 10.4+**, PDO sa prepared statements.
- **Bez frameworka i bez Composer-a** — hosting je cPanel, deploy je upload ZIP-a.
- Server-rendered PHP stranice + malo vanilla JS-a. **Bez build koraka** (bez npm, React, Vue).
- Mobile-first, PWA (manifest + service worker samo za ikonicu i keš statike; offline rad nije potreban u v1).
- Ceo UI na **srpskom, latinica**.
- Sve podesive vrednosti u `config.php` (van web root-a ako hosting dozvoljava, inače zaštićen `.htaccess`) i u tabeli `settings` (menja vlasnik iz aplikacije).

### Struktura foldera (predlog)

```
/public_html/montaza/          ← document root subdomena
  index.php                    ← router (?p=projekti, ?p=projekat&id=…)
  api/                         ← POST akcije (vraćaju JSON ili redirect)
  assets/ (css, js, ikone)
  uploads/  (.htaccess: zabrani izvršavanje PHP-a)
  manifest.webmanifest, sw.js
/montaza-app/                  ← VAN web root-a ako je moguće
  config.php
  lib/ (db.php, auth.php, upload.php, telegram.php, rules.php)
  views/
  schema.sql
```

Ako hosting ne dozvoljava folder van `public_html`, stavi `lib/`, `views/` i `config.php` u podfolder zaštićen sa `.htaccess` → `Require all denied`.

---

## 2. Šta Aleksandar radi u cPanel-u (pre i posle Claude Code-a)

1. cPanel → **Domains / Subdomains** → napravi `montaza.aggroup.rs`.
2. cPanel → **SSL/TLS Status** → pokreni AutoSSL za `montaza.aggroup.rs` (mora HTTPS — kamera na telefonu ne radi bez njega).
3. cPanel → **MySQL Databases** → napravi bazu (npr. `xxx_montaza`), korisnika sa jakom lozinkom, dodeli **ALL PRIVILEGES**.
4. **phpMyAdmin** → izaberi bazu → **Import** → `schema.sql` (Claude Code ga pravi u fazi 1).
5. Upload ZIP-a u folder subdomena → Extract.
6. Popuni `config.php` (baza, Telegram token).
7. Otvori `https://montaza.aggroup.rs/install.php` → napravi naloge (vlasnik, hala, teren) → **obriši `install.php`**.
8. Na telefonu svako otvori sajt → „Dodaj na početni ekran".

---

## 3. Baza podataka

Claude Code pravi `schema.sql` (InnoDB, utf8mb4_unicode_ci, strani ključevi). Minimum:

### `users`
`id, name, username (unique), password_hash, role ENUM('vlasnik','hala','teren'), telegram_chat_id NULL, active TINYINT, created_at`

### `projects`
| Polje | Tip | Napomena |
|---|---|---|
| id | INT PK | |
| code | VARCHAR | auto, npr. `2026-214` |
| type | ENUM('novi','zapocet','reklamacija') | |
| parent_project_id | INT NULL | za reklamaciju — veza na originalni projekat (opciono) |
| customer_name, phone, city, address | | |
| description | TEXT | dimenzije, boja, tip pergole — slobodan tekst |
| has_steel | TINYINT | čelična konstrukcija da/ne |
| install_week_start | DATE NULL | **ponedeljak** nedelje montaže; NULL = „nije raspoređeno" |
| deadline | DATE NULL | krajnji rok |
| payment_when | ENUM('pre','posle','ne') | kada teren naplaćuje |
| payment_amount | DECIMAL(10,2) NULL | iznos koji teren naplaćuje (EUR) |
| payment_received | DECIMAL(10,2) NULL | koliko je stvarno naplaćeno |
| payment_confirmed_at, payment_confirmed_by | | |
| status | ENUM('aktivan','zavrsen','otkazan') | |
| video_sent_viber | TINYINT | štikla na kraju montaže |
| handover_done | TINYINT | predat daljinski i uputstvo |
| notes | TEXT | |
| created_by, created_at, completed_at | | |

### `project_items` (stavke)
`id, project_id, kind ENUM('nalog','celik','aluminijum','cerada','pakovanje','montaza','dijagnoza','materijal','servis'), sort, responsible ENUM('vlasnik','hala','teren'), due_date DATE NULL, status ENUM('ceka','u_toku','zavrseno','zastoj'), started_at, completed_at, completed_by, note TEXT, imported TINYINT (zatvoreno pri unosu započetog projekta, bez slika)`

### `stoppages` (zastoji)
`id, project_id, item_id, reason ENUM('materijal','vreme','kupac','ekipa','ostalo'), note, created_by, created_at, resolved_at NULL, resolved_by`

### `files`
`id, project_id, item_id NULL, kind ENUM('lokacija_pre','posle','foto','nalog_pdf'), path, thumb_path, mime, width, height, size, uploaded_by, created_at`

### `settings`
`key, value` — npr. `weekly_capacity=4`, `min_photos_hala=1`, `min_photos_montaza=3`, `telegram_bot_token`, `telegram_owner_chat_id`

### `activity_log`
`id, project_id, item_id NULL, user_id, action, details JSON, created_at` — sve promene statusa, uploadi, naplate. Nikad se ne briše.

---

## 4. Šabloni stavki

Pri otvaranju projekta stavke se kreiraju **automatski** (ne kucaju se ručno):

| Tip | Stavke (redom) | Odgovoran |
|---|---|---|
| `novi` | nalog → *celik (ako has_steel)* → aluminijum → cerada → pakovanje → montaza | vlasnik → hala → hala → hala → hala → teren |
| `zapocet` | isto kao `novi`; vlasnik bira „već urađeno do" i te stavke se upisuju kao `zavrseno` + `imported=1` (bez obaveznih slika) | |
| `reklamacija` | dijagnoza → materijal → servis | teren → hala → teren |

Vlasnik može naknadno da uključi/isključi čeličnu konstrukciju dok stavka nije započeta.

---

## 5. Poslovna pravila (OBAVEZNO u kodu, u `lib/rules.php`, ne samo u UI-ju)

1. **Nalog:** stavka `nalog` se zatvara samo uz upload PDF-a (`nalog_pdf`).
2. **Stavke hale:** zatvaranje traži najmanje `min_photos_hala` fotografija (podrazumevano 1).
3. **Naplata PRE:** ako je `payment_when='pre'`, stavka `montaza` **ne može preći u `u_toku`** dok teren ne potvrdi naplatu (unese primljeni iznos). Dugme na terenu: „Naplaćeno — počinjem".
4. **Naplata POSLE:** ako je `payment_when='posle'`, montaža ne može da se zatvori bez potvrde naplate.
5. **Razlika u iznosu:** ako `payment_received ≠ payment_amount` → odmah Telegram obaveštenje vlasniku.
6. **Zatvaranje montaže** traži:
   - najmanje 1 sliku tipa `posle` (isti ugao kao `lokacija_pre`),
   - ukupno najmanje `min_photos_montaza` slika,
   - štiklu `video_sent_viber` (video se NE uploaduje — samo podsetnik „pošalji video u Viber grupu"),
   - štiklu `handover_done`.
   Dugme „Potvrdi završetak" je zaključano dok sve nije ispunjeno i ispod piše šta fali.
7. **Slika lokacije PRE** je obavezna pri otvaranju projekta tipa `novi` (za `zapocet`/`reklamacija` preporučena, ne obavezna).
8. **Projekat je završen** kada su sve stavke `zavrseno` → `status='zavrsen'`, `completed_at=NOW()`.
9. **Zastoj:** bilo ko prijavljuje zastoj na svojoj stavci (razlog + kratka napomena) → stavka `zastoj`, Telegram vlasniku. Zastoj se zatvara kad se stavka nastavi.
10. **Redosled se ne forsira strogo** (hala može raditi ceradu paralelno sa aluminijumom), ali montaža prikazuje upozorenje ako pakovanje nije zatvoreno.
11. **Kašnjenje:** stavka ili projekat posle `due_date`/`deadline` a nije zatvoren → crvena oznaka „kasni X dana".
12. Svaka promena ide u `activity_log`.

---

## 6. Prava pristupa

| | vlasnik | hala | teren |
|---|---|---|---|
| Otvara/menja/briše projekte | ✅ | ❌ | ❌ |
| Planira nedelje i rokove | ✅ | ❌ | ❌ |
| Vidi sve projekte | ✅ | samo aktivne sa hala stavkama | samo aktivne sa teren stavkama |
| Zatvara stavke | sve | samo `responsible='hala'` | samo `responsible='teren'` |
| Vidi iznose naplate | ✅ | ❌ **nikad** | ✅ (samo iznos za naplatu) |
| Izveštaj | ✅ | ❌ | ❌ |
| Podešavanja i korisnici | ✅ | ❌ | ❌ |

Prava se proveravaju **na serveru** za svaki zahtev, ne samo sakrivanjem dugmića.

---

## 7. Ekrani

Referentne slike makete su u `docs/` (PNG izvoz). Prati ih po rasporedu i stilu.

1. **Projekti** — lista aktivnih grupisana po nedelji montaže („NEDELJA 45 · 3–9. NOV", „3 / 4 montaže"). Kartica: ime kupca, oznaka tipa (NOVI / NOVI + ČELIK / ZAPOČET / REKLAMACIJA), krajnji rok, mesto + kratak opis, traka koraka (zeleno = gotovo, belo = u toku, sivo = čeka), status (narandžasto za zastoj/kašnjenje), oznaka naplate PRE/POSLE. Filteri: Aktivni / Reklamacije / Završeno. Pretraga po imenu/mestu. Plutajuće dugme „+ Novi projekat" (samo vlasnik). Donji meni: Projekti · Plan · Izveštaj.
2. **Plan** — naredne 4 nedelje. Za svaku: broj montaža / kapacitet (traka; prepunjeno = narandžasto), lista projekata. Gore blok **„Nije raspoređeno"** (projekti bez `install_week_start`, npr. započeti i reklamacije). Tap na projekat → izbor nedelje (bottom sheet). Prevlačenje nije potrebno u v1.
3. **Novi projekat** (vlasnik) — tip (segment: Novi / Započet / Reklamacija); kupac, mesto, telefon; **foto lokacije**; opis; stavke kao prekidači (čelična konstrukcija uključiva, ostale fiksne); za „Započet" → „već urađeno do"; nedelja montaže + krajnji rok; naplata (Pre početka / Posle montaže / Ne naplaćuje) + iznos; zakači PDF nalog; „Sačuvaj projekat".
4. **Projekat** — gore slika mesta montaže (oznaka „MESTO MONTAŽE · PRE"), ime + tip, opis, kartice: nedelja · krajnji rok · „Pozovi" (tel: link). Istaknut blok naplate („Naplatiti PRE početka montaže · 1.200 €") — vidljiv vlasniku i terenu. Stavke vertikalno (timeline) sa datumom, ko je zatvorio, prilozima (PDF, broj slika → galerija). Aktivna stavka ima dugmad **„Završi + foto"** i **„Zastoj"**. Na dnu: istorija (activity_log) — sklopljena.
5. **Završi stavku** — za hala stavke: fotografije + napomena. Za montažu: PRE slika levo, „Slikaj POSLE" desno, dodatne slike, štikle (naplata, video poslat u Viber grupu, predat daljinski i uputstvo), napomena, zaključano dugme sa listom šta fali.
6. **Izveštaj** (vlasnik) — izbor meseca. Brojke: završeno projekata (posebno reklamacije), prosečno dana od otvaranja do završetka, broj zastoja i izgubljeni dani, zastoji po razlogu (trake), najsporija stavka (prosek dana od `started_at` do `completed_at`), projekti koji prelaze u sledeći mesec, naplaćeno ukupno. Dugme **„Preuzmi PDF"** (štampa-friendly HTML stranica → print to PDF je dovoljno za v1).
7. **Podešavanja** (vlasnik) — korisnici (dodaj, deaktiviraj, reset lozinke), kapacitet nedelje, min. broj slika, Telegram.
8. **Login** — korisničko ime + lozinka, „zapamti me" 30 dana.

---

## 8. Fotografije i fajlovi

- Na telefonu: `<input type="file" accept="image/*" capture="environment" multiple>`.
- **Pre slanja** smanji sliku u browseru (canvas) na max 1600 px duža strana, JPEG ~80% — štedi mobilni internet.
- Server: proveri pravi MIME (finfo), ponovo enkoduj kroz GD (briše EXIF i maliciozan sadržaj), napravi thumbnail 400 px, čuvaj u `uploads/YYYY/MM/` sa nasumičnim imenom.
- PDF: samo `application/pdf`, max 15 MB.
- `uploads/.htaccess`: zabrani izvršavanje skripti; pristup slikama samo prijavljenim korisnicima (servirati kroz `file.php?id=` sa proverom prava).
- **Isti ugao (v2, opciono):** ekran kamere preko `getUserMedia` sa providnom PRE slikom preko kadra. U v1 dovoljno je da PRE slika stoji pored dugmeta „Slikaj POSLE".

---

## 9. Telegram obaveštenja

Aleksandar već koristi Telegram bota za upite — koristi isti ili novi bot (token u `settings`). Svaki korisnik može imati svoj `telegram_chat_id`.

| Događaj | Kome |
|---|---|
| Novi projekat / projekat raspoređen u nedelju | hala, teren |
| Stavka hale zatvorena | vlasnik |
| Pakovanje zatvoreno → „Spremno za montažu: Jovanović, Niš, ned. 46" | teren, vlasnik |
| Naplata potvrđena (+ razlika ako postoji) | vlasnik |
| Zastoj prijavljen | vlasnik |
| Montaža završena (sa linkom na projekat) | vlasnik |
| Ponedeljkom 7:00 — pregled nedelje (šta se montira, šta kasni) | svi (cron preko cPanel Cron Jobs) |

Slanje ne sme da obori akciju ako Telegram ne odgovori (try/catch + log).

---

## 10. Dizajn

Tamni, moderan, „industrijski" stil — referenca su slike makete u `docs/`. Bez šablonskog izgleda.

```css
:root{
  --bg:#0E0F11; --surface:#17191C; --surface-2:#1F2226; --border:#2A2E33;
  --text:#F2F3F4; --muted:#9BA1A8; --text-2:#C5C9CE;
  --accent:#C6F24E;       /* gotovo, primarne akcije */
  --warn:#FF8A3D;         /* zastoj, kašnjenje, fali */
  --info:#8CC8FF; --info-bg:#1C2F44;  /* reklamacija */
  --radius:16px;
}
```

- Fontovi: **Geist** (tekst) i **Geist Mono** (datumi, brojevi, oznake) sa Google Fonts.
- Dodirne površine min. 44 px; dugmad za glavne akcije 48–54 px.
- Ikone: inline SVG, stroke stil. Bez emotikona.
- Kontrast teksta min. 4.5:1.

---

## 11. Bezbednost

- `password_hash` / `password_verify`; sesije sa `httponly`, `secure`, `samesite=Lax`.
- CSRF token na svakoj POST akciji.
- Samo prepared statements.
- Izlaz uvek kroz `htmlspecialchars`.
- Ograniči pokušaje logovanja (5 / 15 min po IP-u).
- `install.php` odbija rad ako korisnici već postoje.
- Bez `display_errors` u produkciji; greške u log fajl van web root-a.

---

## 12. Faze rada (Claude Code: posle svake faze stani i prikaži)

1. **Temelj** — struktura, `config.php.example`, `schema.sql`, konekcija, login/logout, uloge, `install.php`, osnovni layout + donji meni + dizajn tokeni.
2. **Projekti** — novi projekat (sa šablonima stavki, tipovima, čelikom, započetim), lista po nedeljama, detalj projekta, upload slike lokacije i PDF naloga.
3. **Stavke** — zatvaranje uz fotografije, zastoj, sva pravila iz tačke 5, naplata pre/posle, ekran za završetak montaže.
4. **Plan** — nedelje, kapacitet, „nije raspoređeno", raspoređivanje.
5. **Izveštaj** — mesečne brojke + print/PDF.
6. **Telegram** — obaveštenja + ponedeljni pregled (cron).
7. **PWA i pakovanje** — manifest, ikonice, service worker, testiranje na telefonu, **ZIP spreman za cPanel upload** + kratko `README-INSTALACIJA.md` na srpskom.

---

## 13. Odluke koje vlasnik još treba da potvrdi (pitaj pre faze 2 ako nije upisano)

- [ ] Kapacitet montaža po nedelji: ___
- [ ] Minimalan broj slika po stavci hale: ___ (predlog 1) i za montažu: ___ (predlog 3)
- [ ] Imena i uloge korisnika: ___
- [ ] Da li hala vidi i teren stavke (samo čitanje)? Predlog: da, samo čitanje.
- [ ] Valuta za naplatu: EUR (predlog) ili RSD?

---

## 14. Šta NE radimo u v1

Čet u aplikaciji (postoji Viber), evidencija radnog vremena, magacin/zalihe, upload videa, generisanje naloga (nalog se kači ručno kao PDF), veza sa postojećim ERP-om (erp.aggroup.rs) — možda kasnije.

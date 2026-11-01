# Montaža — instalacija na cPanel (montaza.aggroup.rs)

Ukupno 10–15 minuta. Ne treba ništa da se programira, ni da se dira phpMyAdmin.

---

## 1. Subdomen i HTTPS (cPanel)

1. **Domains** (ili **Subdomains**) → napravi `montaza.aggroup.rs`. Zapamti njegov folder (**Document Root**), npr. `/home/KORISNIK/montaza.aggroup.rs`.
2. **SSL/TLS Status** → označi `montaza.aggroup.rs` → **Run AutoSSL**.
   HTTPS je obavezan: bez njega kamera na telefonu i „Dodaj na početni ekran" ne rade.

## 2. Prazna baza (cPanel → MySQL Databases)

1. **Create New Database** → npr. `montaza`. cPanel doda prefiks, pa se zove npr. `KORISNIK_montaza`.
2. **Add New User** → npr. `mz` (biće `KORISNIK_mz`) → lozinka preko **Password Generator**. **Zapiši je.**
3. **Add User To Database** → izaberi tog korisnika i bazu → **ALL PRIVILEGES** → Make Changes.

## 3. ZIP (cPanel → File Manager)

1. Otvori folder subdomena iz koraka 1.
2. **Upload** → `montaza-YYYYMMDD.zip`.
3. Desni klik na ZIP → **Extract** → u isti folder.
4. Obriši ZIP.

## 4. Instalacija (u browseru)

Otvori **https://montaza.aggroup.rs**. Sam te prebaci na instalaciju:

1. **Korak 1 — baza:** upiši ime baze, korisnika i lozinku iz koraka 2 → **Poveži i napravi tabele**.
   Ako nešto ne valja, piše tačno šta (pogrešna lozinka, baza ne postoji…).
2. **Korak 2 — nalozi:** imena su već upisana (Aleksandar, Oliver, Steva). Svako upiše **svoju lozinku** od 4 cifre → **Napravi naloge**.
3. Na kraju instalacija:
   - sama obriše `install.php`;
   - ispiše komandu za ponedeljni pregled (korak 7). Kopiraj je.

Uradi korak 4 odmah posle raspakivanja: dok instalacija nije završena, `install.php` može da otvori bilo ko.

## 5. Telefoni i obaveštenja

Svako na svom telefonu:
1. Otvori **https://montaza.aggroup.rs** i prijavi se. „Zapamti me" drži prijavu 30 dana.
2. **Instaliraj kao aplikaciju:**
   - **Android (Chrome):** u Montaži se pojavi kartica **„Instaliraj Montažu kao aplikaciju"** → **Instaliraj**. Ako je nema: meni ⋮ → **Instaliraj aplikaciju**. Ne koristi „Dodaj na početni ekran", to pravi samo prečicu. Montaža se zatim pojavi u spisku aplikacija i otvara se bez adresne trake. Dugi pritisak na ikonicu nudi prečice Danas, Zadaci i Novi zadatak.
   - **iPhone (Safari):** dugme Deli (□↑) → **Add to Home Screen**.
3. **Otvori Montažu sa ikonice** → u kartici „Obaveštenja na ovom telefonu" dodirni **Uključi** (ili zvonce gore) → **Dozvoli**.
   Odmah stiže probno obaveštenje „Obaveštenja su uključena ✓", a zvonce postane zeleno.

Obaveštenja stižu kao kod svake aplikacije, i kad je Montaža zatvorena. Dodir na obaveštenje otvara taj projekat ili zadatak.
- **iPhone:** potreban je iOS 16.4 ili noviji, a Montaža mora biti otvorena sa ikonice na početnom ekranu, ne iz Safarija.
- **Android:** radi u Chrome-u. Ako je slučajno kliknuto „Blokiraj": katanac pored adrese → Obaveštenja → Dozvoli.
- U **Podešavanja → Korisnici** vidiš ko ima uključena obaveštenja i na koliko telefona.

Zaštita kratkih lozinki:
- posle 5 pogrešnih pokušaja nalog je zaključan 15 minuta;
- posle 20 pogrešnih pokušaja u toku dana zaključan je 24 sata, a Aleksandar dobija poruku;
- Aleksandar može ranije da otključa nalog: **Podešavanja → korisnik → Otključaj nalog**.

## 6. Telegram (opciono)

Obaveštenja iz koraka 5 su dovoljna. Telegram je dodatni kanal, ako ga želite: na telefonu treba samo aplikacija **Telegram**.

1. Aleksandar: u Telegramu otvori **@BotFather** → `/newbot` → ime npr. „AG Montaža" → dobiješ **token** (`123456789:ABC…`).
   Napravi **novi** bot samo za Montažu. Bot za upite koji već koristiš verovatno ne može da se deli.
2. U aplikaciji Montaža: **Podešavanja → Telegram** → nalepi token → **Sačuvaj podešavanja**.
3. Aleksandar, Oliver i Steva u Telegramu pronađu bota (po imenu iz koraka 1) i pritisnu **Start**, odnosno pošalju bilo koju poruku.
4. **Podešavanja → Pronađi chat ID** → pojave se imena i brojevi. Upiši broj svakom korisniku (otvori korisnika → *Telegram chat ID* → Sačuvaj) → **Pošalji test poruku**.

Ko šta dobija, isto i na telefon i na Telegram:
- **Oliver i Steva:** novi projekat i raspoređivanje („montaža pon 10.11. – pet 14.11.").
- **Steva:** „Spremno za montažu" kad Oliver zatvori pakovanje.
- **Aleksandar:**
  - gotove stavke u hali;
  - naplata, i odmah razlika u iznosu ako je ima;
  - zastoji;
  - završena montaža.
- **Za zadatke:** kome je zadatak dat dobija poruku kad ga dobije, a ko ga je zadao kad je završen ili kad ima zastoj.

## 7. Ponedeljni pregled (cPanel → Cron Jobs)

**Add New Cron Job**:
- Minute `0`, Hour `7`, Day `*`, Month `*`, Weekday `1` (ponedeljak);
- Command: komanda koju je ispisala instalacija, npr.:

```
/usr/local/bin/php /home/KORISNIK/montaza.aggroup.rs/montaza-app/cron/nedeljni-pregled.php
```

Svako ponedeljkom u 7:00 dobije obaveštenje na telefonu i, ako ima Telegram, detaljnu poruku sa ovim:
- montaže te nedelje;
- šta kasni;
- otvoreni zastoji;
- njegovi zadaci.

---

## Pregaziti staru aplikaciju na istom subdomenu i u istoj bazi

1. *(preporuka)* phpMyAdmin → stara baza → **Export** → **Go**, za svaki slučaj.
2. Zapiši korisnika i lozinku baze. Stara aplikacija ih obično drži u `.env` (`DB_USERNAME`, `DB_PASSWORD`). Ako ih nemaš: MySQL Databases → novi korisnik → *Add User To Database* → ALL PRIVILEGES.
3. File Manager → folder subdomena → *Settings → Show Hidden Files* → **Izaberi sve** → skini štiklu sa `.well-known` → **Obriši**.
4. **Otpremi** ZIP → **Izdvoji** → obriši ZIP.
5. **Domeni** → subdomen → **Upravljaj** → *Koren dokumenta* neka bude sam folder subdomena (npr. `montaza.aggroup.rs`, bez `/…/public`).
6. phpMyAdmin → levo **klikni bazu** → **Import** → `montaza-baza.sql` → **Go**. Ovo briše sve stare tabele u **toj** bazi i pravi nove.
7. Otvori sajt → instalacija (korak 4 gore).

## Česti problemi

| Problem | Rešenje |
|---|---|
| Instalacija: „Pogrešan korisnik ili lozinka" | Korak 2.3 — korisnik mora biti dodat na bazu sa ALL PRIVILEGES. |
| Instalacija: „Baza ne postoji" | Upiši puno ime sa prefiksom, npr. `KORISNIK_montaza`. |
| Instalacija: „server ne dozvoljava upis fajla" | Uradi ono što piše na ekranu: napravi `montaza-app/config.php` sa prikazanim tekstom. |
| „Došlo je do greške" | Pogledaj `montaza-app/logs/app.log` u File Manager-u. |
| Slika ili PDF „prevelik" | cPanel → **MultiPHP INI Editor**: `upload_max_filesize` ≥ 20M, `post_max_size` ≥ 64M. |
| Obaveštenja ne stižu | Proveri da je zvonce zeleno. Na iPhone-u otvori Montažu sa ikonice, ne iz Safarija. U podešavanjima telefona obaveštenja za Montažu/Chrome moraju biti uključena. |
| Kamera se ne otvara | Sajt mora biti na **https://** (korak 1, AutoSSL). |
| Zaboravljena lozinka | Aleksandar: **Podešavanja → korisnik → Nova lozinka**. |
| Aleksandar zaboravio svoju lozinku | cPanel **Terminal**: `php -r 'echo password_hash("NOVA", PASSWORD_DEFAULT);'` → phpMyAdmin → tabela `users` → red `aleksandar` → nalepi u `password_hash`. |
| Ponovna instalacija od nule | Obriši `montaza-app/config.php` i sve tabele u bazi, pa ponovo otpakuj ZIP. |

**Ažuriranje (nova verzija):** File Manager → folder subdomena → **Otpremi** `montaza-azuriranje-*.zip` → **Izdvoji** (zameni postojeće) → obriši ZIP. Podešavanja, baza i slike ostaju netaknuti.

**Rezervna kopija:** cPanel → **Backup** → preuzmi bazu (MySQL) i folder `uploads/`, makar jednom mesečno.

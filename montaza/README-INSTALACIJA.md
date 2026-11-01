# Montaža — instalacija na cPanel (montaza.aggroup.rs)

Uputstvo za postavljanje aplikacije na hosting. Ne treba ništa da se programira, samo da se prate koraci redom. Za sve treba otprilike 20–30 minuta.

U ZIP fajlu su dva foldera:

| Folder iz ZIP-a | Šta je | Gde ide |
|---|---|---|
| `public/` | ono što se vidi na sajtu | u folder subdomena (document root) |
| `montaza-app/` | kod, podešavanja i baza | **jedan nivo iznad**, da se ne vidi spolja |

---

## 1. Subdomen i HTTPS

1. cPanel → **Domains** (ili **Subdomains**) → napravi `montaza.aggroup.rs`.
   - Zapiši koji je **Document Root**, na primer `/home/KORISNIK/montaza.aggroup.rs` ili `/home/KORISNIK/public_html/montaza`.
2. cPanel → **SSL/TLS Status** → označi `montaza.aggroup.rs` → **Run AutoSSL**.
   HTTPS je obavezan: bez njega kamera na telefonu i „Dodaj na početni ekran" ne rade.

## 2. Baza

1. cPanel → **MySQL Databases**:
   - napravi bazu, na primer `KORISNIK_montaza`;
   - napravi korisnika sa jakom lozinkom (dugme „Password Generator") i zapiši lozinku;
   - dodaj korisnika na bazu sa **ALL PRIVILEGES**.
2. cPanel → **phpMyAdmin** → levo izaberi novu bazu → **Import** → izaberi `montaza-app/schema.sql` iz ZIP-a → **Import** (Go).
   Pojaviće se tabele `users`, `projects`, `tasks`…

## 3. Fajlovi

1. cPanel → **File Manager** → otvori svoj početni folder (`/home/KORISNIK`) → **Upload** → ZIP → **Extract**.
2. Sadržaj foldera `public/` prebaci u **Document Root** subdomena iz koraka 1. Prebacuju se i skriveni fajlovi `.htaccess` i `.user.ini`: u File Manageru uključi *Settings → Show Hidden Files*.
3. Folder `montaza-app/` ostavi u `/home/KORISNIK/`, pored `public_html`. Aplikacija ga sama pronalazi:
   - ako je Document Root `/home/KORISNIK/montaza.aggroup.rs` → `montaza-app` ide u `/home/KORISNIK/montaza-app`;
   - ako je Document Root `/home/KORISNIK/public_html/montaza` → `montaza-app` takođe ide u `/home/KORISNIK/montaza-app`.

   Ako hosting baš ne dozvoljava folder van `public_html`, stavi `montaza-app/` **unutar** Document Root-a. Njegov `.htaccess` blokira svaki pristup spolja.

## 4. config.php

U `montaza-app/` kopiraj `config.php.example` u **`config.php`** i popuni:

```php
'db' => [
    'host' => 'localhost',
    'name' => 'KORISNIK_montaza',     // iz koraka 2
    'user' => 'KORISNIK_montaza',     // iz koraka 2
    'pass' => 'lozinka iz koraka 2',
],
'base_url' => 'https://montaza.aggroup.rs',
'env'      => 'production',
```

## 5. Nalozi (Aleksandar, Oliver, Steva)

1. Otvori **https://montaza.aggroup.rs/install.php**.
2. Popuni tri naloga:

   | Uloga | Ime | Korisničko ime |
   |---|---|---|
   | Vlasnik | Aleksandar | `aleksandar` |
   | Hala (šef proizvodnje) | Oliver | `oliver` |
   | Teren (šef terenske ekipe) | Steva | `steva` |

   Lozinke (4 cifre) svako kuca **sam** i ne zapisuju se nigde drugde.
3. Klikni **Napravi naloge**, pa **obriši `install.php`** sa servera (File Manager → Delete). Bez njega niko ne može ponovo da pravi naloge.

Zaštita kratkih lozinki:
- posle 5 pogrešnih pokušaja nalog je zaključan 15 minuta;
- posle 20 pogrešnih pokušaja u toku dana zaključan je 24 sata, a Aleksandar dobija poruku;
- Aleksandar može ranije da otključa nalog: **Podešavanja → korisnik → Otključaj nalog**.

## 6. Telefoni

Svako na svom telefonu:
1. Otvori **https://montaza.aggroup.rs** u Chrome-u (Android) ili Safari-ju (iPhone) i prijavi se. „Zapamti me" drži prijavu 30 dana.
2. Dodaj aplikaciju na početni ekran:
   - **Android:** meni ⋮ → **Dodaj na početni ekran** / **Instaliraj aplikaciju**;
   - **iPhone:** dugme Deli (□↑) → **Add to Home Screen**.

## 7. Telegram obaveštenja (preporučeno)

1. Na Telegramu otvori **@BotFather** → `/newbot` → ime npr. „AG Montaža" → dobićeš **token** (`123456789:ABC…`).
   Preporuka je **novi bot samo za Montažu**. Ako postojeći bot za upite koristi webhook, ne može da se koristi i ovde.
2. U aplikaciji: **Podešavanja → Telegram** → nalepi token → **Sačuvaj podešavanja**.
3. Aleksandar, Oliver i Steva na Telegramu pronađu bota i pošalju mu bilo koju poruku, npr. „start".
4. **Podešavanja → Pronađi chat ID** → prikazaće se imena i brojevi. Upiši broj svakom korisniku (otvori korisnika → *Telegram chat ID* → Sačuvaj) i klikni **Pošalji test poruku**.

Ko šta dobija:
- **Oliver i Steva:** novi projekat i raspoređivanje u nedelju.
- **Steva:** „Spremno za montažu" kad Oliver zatvori pakovanje.
- **Aleksandar:**
  - gotove stavke u hali;
  - naplata, i odmah razlika u iznosu ako je ima;
  - zastoji;
  - završena montaža.
- **Za zadatke:** kome je zadatak dat dobija poruku kad ga dobije, a ko ga je zadao kad je završen ili kad ima zastoj.

## 8. Ponedeljni pregled (cron)

cPanel → **Cron Jobs** → *Add New Cron Job*:
- Common Settings: **Once Per Week**, ili ručno: minut `0`, sat `7`, dan u nedelji `1` (ponedeljak);
- Command (zameni `KORISNIK`):

```
/usr/local/bin/php /home/KORISNIK/montaza-app/cron/nedeljni-pregled.php
```

Svako ponedeljkom u 7:00 dobije poruku sa ovim:
- montaže te nedelje;
- šta kasni;
- otvoreni zastoji;
- njegovi zadaci.

Da proveriš šta će poslati bez slanja, u cPanel **Terminal**-u pokreni:
`php ~/montaza-app/cron/nedeljni-pregled.php --dry-run`

---

## Česti problemi

| Problem | Rešenje |
|---|---|
| „Nedostaje config.php" | Korak 4 — `config.php` mora biti u `montaza-app/`. |
| „Ne mogu da pronađem folder montaza-app" | `montaza-app/` nije na mestu iz koraka 3. |
| „Došlo je do greške" | Pogledaj `montaza-app/logs/app.log`. Najčešće je pogrešan podatak za bazu u `config.php`. |
| Slika ili PDF „prevelik" | `.user.ini` mora biti u Document Root-u. Proveri i cPanel → **MultiPHP INI Editor**: `upload_max_filesize` ≥ 20M, `post_max_size` ≥ 64M. |
| Kamera se ne otvara | Sajt mora biti na **https://** (korak 1, AutoSSL). |
| Zaboravljena lozinka | Aleksandar: **Podešavanja → korisnik → Nova lozinka**. |
| Aleksandar zaboravio lozinku | phpMyAdmin → tabela `users` → red `aleksandar` → u polje `password_hash` nalepi rezultat iz cPanel Terminala: `php -r 'echo password_hash("NOVA", PASSWORD_DEFAULT);'` |

**Rezervna kopija:** cPanel → **Backup** → preuzmi bazu (MySQL) i folder `uploads/` iz Document Root-a, makar jednom mesečno.

Tehnički opis aplikacije je u `docs/SPEC-montaza.md` u repozitorijumu.

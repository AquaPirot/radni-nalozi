# Montaža — montaza.aggroup.rs

PHP + MySQL aplikacija za praćenje projekata pergola. Kompletan opis je u [`docs/SPEC-montaza.md`](docs/SPEC-montaza.md), makete ekrana su u `docs/`.

## Raspored foldera

| Folder u repou | Na serveru |
|---|---|
| `public/` | sadržaj foldera subdomena (document root) |
| `montaza-app/` | `montaza-app/` unutar istog foldera — `.htaccess` (`Require all denied`) blokira pristup spolja |

`build-zip.sh` spaja oba u jedan ZIP koji se raspakuje direktno u folder subdomena; `install.php` zatim pravi `config.php`, tabele i naloge. `_init.php` pronalazi `montaza-app/` i ako je premešten van web root-a.

## Status

- [x] Faza 1 — Temelj (šema, login, uloge, instalacija, layout)
- [x] Faza 2 — Projekti (novi, lista po nedeljama, detalj, izmena, brisanje, slike i PDF)
- [x] Faza 3 — Stavke i pravila (završetak uz slike, zastoj, naplata pre/posle, završetak montaže)
- [x] Faza 3b — Zadaci van projekata (popis, alat, servis, nabavka)
- [x] Faza 4 — Plan (4 nedelje, kapacitet, nije raspoređeno, prošle nedelje, raspoređivanje)
- [x] Faza 5 — Izveštaj (mesečne brojke, zastoji, najsporija stavka, naplata, zadaci, PDF sa slikama)
- [x] Faza 6 — Telegram (obaveštenja, ponedeljni pregled), podešavanja i korisnici
- [x] Faza 7 — PWA (ikonica na početnom ekranu), ZIP za cPanel, uputstvo

## Instalacija na hosting

Gotov paket: `dist/montaza-*.zip`, uputstvo korak po korak: [`README-INSTALACIJA.md`](README-INSTALACIJA.md).
Novi ZIP posle izmena: `./build-zip.sh`.

## Lokalno pokretanje

```bash
cp montaza-app/config.php.example montaza-app/config.php   # popuni bazu, base_url=http://127.0.0.1:8099, env=dev
mysql baza < montaza-app/schema.sql
php -S 127.0.0.1:8099 -t public
# otvori http://127.0.0.1:8099/install.php
```

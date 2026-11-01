# Montaža — montaza.aggroup.rs

PHP + MySQL aplikacija za praćenje projekata pergola. Kompletan opis je u [`docs/SPEC-montaza.md`](docs/SPEC-montaza.md), makete ekrana su u `docs/`.

## Raspored foldera

| Folder u repou | Gde ide na serveru |
|---|---|
| `public/` | document root subdomena, npr. `/public_html/montaza/` |
| `montaza-app/` | **van** web root-a, pored `public_html` (npr. `/home/korisnik/montaza-app/`) |

Ako hosting ne dozvoljava folder van `public_html`, stavi `montaza-app/` **unutar** document root-a. Tamošnji `.htaccess` (`Require all denied`) blokira svaki pristup spolja. `public/_init.php` sam pronalazi folder na jednom od ta dva mesta.

## Status

- [x] Faza 1 — Temelj (šema, login, uloge, instalacija, layout)
- [x] Faza 2 — Projekti (novi, lista po nedeljama, detalj, izmena, brisanje, slike i PDF)
- [x] Faza 3 — Stavke i pravila (završetak uz slike, zastoj, naplata pre/posle, završetak montaže)
- [x] Faza 3b — Zadaci van projekata (popis, alat, servis, nabavka)
- [x] Faza 4 — Plan (4 nedelje, kapacitet, nije raspoređeno, prošle nedelje, raspoređivanje)
- [x] Faza 5 — Izveštaj (mesečne brojke, zastoji, najsporija stavka, naplata, zadaci, PDF sa slikama)
- [x] Faza 6 — Telegram (obaveštenja, ponedeljni pregled), podešavanja i korisnici
- [ ] Faza 7 — PWA i ZIP za cPanel

## Lokalno pokretanje

```bash
cp montaza-app/config.php.example montaza-app/config.php   # popuni bazu, base_url=http://127.0.0.1:8099, env=dev
mysql baza < montaza-app/schema.sql
php -S 127.0.0.1:8099 -t public
# otvori http://127.0.0.1:8099/install.php
```

# MONTAŽA v2 — dorada (komandna tabla, sistemi, ekipe, naplata)

Izvor: predlog vlasnika (13 tačaka) i maketa `v2-maketa.png`. Odluke vlasnika:
- faze proizvodnje: početni šabloni iz predloga, menjaju se u Podešavanjima;
- ekipe: prave se u Podešavanjima (početna: „Steva + ekipa");
- postojeći podaci se **prebacuju** u novi model (migracija 002, automatski pri prvom otvaranju);
- gotovinu prima montažer i **predaje firmi** — dve odvojene evidencije.

## Model podataka (novo)

| Tabela | Šta je |
|---|---|
| `system_types` | Vrste sistema (Klasična pergola, BIOFIX, Bioklimatska, ZIP screen, Giljotina, Slajding, Drugo) i koja polja specifikacije imaju |
| `phase_templates` | Faze proizvodnje po vrsti sistema (menja vlasnik) |
| `project_systems` | Stavke projekta: vrsta, količina, mere (m), RAL, specifikacija, napomena, čelik |
| `production_phases` | Faze po sistemu: odgovorna osoba, status, rok, početak/kraj, napomena, slika |
| `project_orders` | Radni nalog sa verzijama: izdat (PDF) ≠ preuzet (hala potvrđuje) |
| `teams` | Montažne ekipe (naziv, vođa, članovi) |
| `visits` | Termini na terenu: vrsta (montaža/servis/dijagnoza/dorada), ekipa, datum+vreme, trajanje u danima, okvirno/potvrđeno, ishod (celo/delimično/ponovni dolazak), šta je ostalo |
| `payments` | Uplate: iznos, način, ko je primio i kada; predato firmi (kome, kada) |
| `task_comments` | Komentari, slike i istorija zadatka; ponovno otvaranje uz razlog |

Izmene: `projects` (vrsta posla, način unosa, nacrt, ko naplaćuje, valuta, arhiva), `stoppages` (odgovorna osoba, sledeći korak, očekivano rešenje), `tasks` (bez roka, vreme roka, preuzet), `files` (faza, termin, svrha slike).
`project_items` iz v1 ostaje u bazi samo kao arhiva — aplikacija je više ne koristi.

## Tri odvojena statusa projekta

| | Vrednosti |
|---|---|
| Faza realizacije | Nacrt → Proizvodnja → Spremno → Montaža → Završeno |
| Problem | otvoren zastoj (razlog · sledeći korak · odgovoran · ugrožen termin) / reklamacija |
| Naplata | Nije dospela / Za naplatu / Delimično / Naplaćeno / Ne naplaćuje se |

Završena montaža ≠ naplaćeno. Projekat u proizvodnji može istovremeno biti u zastoju.

## Faze rada
1. Model podataka i migracija postojećih podataka.
2. Projekat: forma (nacrt / pusti u realizaciju, sistemi) i ekran sa tabovima Pregled · Proizvodnja · Montaža · Dokumenti i istorija.
3. Plan po ekipama, danima i trajanju; podešavanja ekipa, sistema i faza.
4. Danas kao komandna tabla; montažerov „Moj današnji posao"; zadaci v2.
5. Izveštaj v2, dnevni cron (rokovi, sutrašnje montaže, ponedeljni pregled, rezervna kopija).
6. Dizajn (svetla/tamna tema, računar sa bočnim menijem) i pouzdanost (slike jedna po jedna sa ponovnim slanjem, zaštita od duplog unosa, auto-čuvanje, arhiva).
7. Provera kroz 8 scenarija iz predloga.

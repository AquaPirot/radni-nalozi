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

## Provera (tests/scenariji.sh)

Automatski test prolazi kroz prave ekrane, kao Aleksandar, Oliver i Steva, i posle svakog koraka proverava bazu. Pokretanje: `B=http://127.0.0.1:8099 DB=montaza bash tests/scenariji.sh`.

| # | Scenario | Šta se proverava |
|---|---|---|
| 1 | Jedna pergola, jedna ekipa, jedan dan | puštanje → faze → potvrđen termin bez izuzetka → celo → projekat završen, uplata upisana |
| 2 | Pergola + ZIP-ovi + stakleni sistem | 3 sistema i 6 komada, svaki sa svojim fazama, mere u metrima (2,5), prikaz na kartici |
| 3 | Više dana, delimično | trajanje 3 dana; „delimično" bez opisa ostatka se odbija; dorada se pravi sama; projekat ostaje otvoren; vlasnik to vidi kao odluku |
| 4 | Zastoj u materijalu pomera termin | faza u zastoju; tekst „razlog — sledeći korak — odgovoran — ugrožen termin"; pomeranje traži razlog; razlog ide u istoriju; pomeren termin postaje okviran |
| 5 | Reklamacija na završen projekat | veza u oba smera, bez naplate, filter Reklamacije |
| 6 | Delimična naplata i predaja novca | manja uplata traži potvrdu duga; dug ostaje za naplatu; predaju potvrđuje samo vlasnik; ostatak na račun zatvara dug |
| 7 | Slaba veza i slike | slika se šalje odmah (na čekanju); dvostruko slanje ne pravi dvostruku uplatu; tuđa slika na čekanju se ne vidi i ne može da se zakači |
| 8 | Izmena naloga posle preuzimanja | v2 nije preuzeta; hala je vidi na Danas; sledeća faza je blokirana dok se v2 ne preuzme; stara verzija se ne može preuzeti; istorija |

Rezultat: 47/47.

Nadogradnja je proverena i na kopiji v1 podataka (13 projekata):
- dobijeno je 13 sistema, 37 faza, 15 termina, 3 naloga i 2 uplate;
- tri istovremena zahteva pokreću migraciju samo jednom;
- otvoreno je 192 strane za sve tri uloge, bez greške.

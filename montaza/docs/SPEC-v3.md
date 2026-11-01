# MONTAŽA v3 — jednostavno

Dogovor sa vlasnikom posle v2 („previše informacija, nije jednostavno"):

- pregled projekata i njihov status;
- gde i kad je montaža, sa telefonom i adresom kupca;
- podaci o poslu su u **PDF nalogu** (ne kucaju se u aplikaciju);
- slike;
- statusi kao u v1: stavke projekta;
- ostaju **komentari** i **izveštaj**;
- svi vide sve.

## Ekrani
| Ekran | Šta je na njemu |
|---|---|
| **Projekti** | Aktivni / Reklamacije / Završeni. Aktivni su grupisani: montaža kasni → ove nedelje → sledeće nedelje → kasnije → bez datuma. Na kartici: kupac, mesto, traka stavki, status, dan montaže. Pretraga po kupcu, mestu, adresi i telefonu. |
| **Montaže** | Po danima (kasni, danas, sutra…): vreme, kupac, adresa, telefon, Pozovi / Navigacija / Nalog, status. Na dnu: bez datuma. |
| **Projekat** | Jedna strana: adresa, telefon, Pozovi, Navigacija, status, zastoj, montaža (dan i vreme), radni nalog (PDF), stavke, slike, komentari, prijava zastoja, istorija. |
| **Izveštaj** | Mesečno: završeno, montaže, trajanje, zastoji po razlogu, završeni projekti, PDF sa slikama PRE/POSLE. |

## Stavke (kao v1)
- **Novi / započet posao:** Nalog · (Čelik) · Aluminijum · Cerada · Pakovanje · Montaža
- **Reklamacija:** Dijagnoza · Materijal · Servis

Pravila:
- Nalog se sam završava kad se doda PDF.
- Hala-stavke (čelik, aluminijum, cerada, pakovanje, materijal) se završavaju jednim dodirom, bez slike. Slike se mogu dodati na projekat kad god zatreba.
- Montaža i servis traže najmanje 3 slike (broj se menja u Podešavanjima).
- Kad je sve završeno, projekat je završen.

## Ko šta menja
| | Aleksandar | Oliver (hala) | Steva (teren) |
|---|---|---|---|
| Vidi sve, komentariše, dodaje slike, prijavljuje i rešava zastoj | ✓ | ✓ | ✓ |
| Otvara/menja projekat, dan montaže, nalog | ✓ | | |
| Čelik, aluminijum, cerada, pakovanje, materijal | ✓ | ✓ | |
| Montaža, dijagnoza, servis | ✓ | | ✓ |

## Obaveštenja
- Novi projekat → hala i teren.
- Montaža zakazana ili pomerena → teren i hala.
- Spremno za montažu → teren i vlasnik.
- Završeno → svi.
- Zastoj, rešen zastoj i komentar → svi.
- Cron:
  - jutro: današnje montaže, a ponedeljkom i nedelja;
  - veče: sutrašnje montaže, sa oznakom šta nije spremno;
  - svaki dan: rezervna kopija.

## Podaci
Migracija `004_jednostavno`:
- dodaje `projects.install_date` i `install_time`;
- svim projektima vraća v1 stavke sa stanjem iz v2, i to samo naviše;
- nacrte pretvara u aktivne projekte.

Tabele v2 (sistemi, faze, termini, uplate, zadaci) ostaju u bazi netaknute.

Provera je `tests/scenariji.sh`: 35 provera kroz prave ekrane. Pored toga, nadogradnja je proverena na kopiji v1 baze (v1 → v2 → v3).

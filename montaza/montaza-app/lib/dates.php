<?php
declare(strict_types=1);

// Datumi, nedelje, iznosi i šifra projekta (zajedničko za sve).

const MONTHS_FULL = [1 => 'Januar', 'Februar', 'Mart', 'April', 'Maj', 'Jun', 'Jul', 'Avgust', 'Septembar', 'Oktobar', 'Novembar', 'Decembar'];
const MONTHS_SHORT = [1 => 'JAN', 'FEB', 'MAR', 'APR', 'MAJ', 'JUN', 'JUL', 'AVG', 'SEP', 'OKT', 'NOV', 'DEC'];
const DAYS_SHORT = ['N', 'P', 'U', 'S', 'Č', 'P', 'S'];
const DAYS_FULL = ['nedelja', 'ponedeljak', 'utorak', 'sreda', 'četvrtak', 'petak', 'subota'];

// ── Datumi i nedelje ────────────────────────────────────────

function today(): DateTimeImmutable
{
    return new DateTimeImmutable('today');
}

function week_monday(DateTimeInterface|string $d): DateTimeImmutable
{
    $d = is_string($d) ? new DateTimeImmutable($d) : DateTimeImmutable::createFromInterface($d);
    return $d->setTime(0, 0)->modify('monday this week');
}

/** Radna nedelja: "pon 3.11. – pet 7.11." (umesto broja nedelje — svima jasnije). */
function week_label(DateTimeInterface|string $mon, bool $upper = false): string
{
    $mon = week_monday($mon);
    $s = 'pon ' . $mon->format('j.n.') . ' – pet ' . $mon->modify('+4 days')->format('j.n.');
    return $upper ? mb_strtoupper($s) : $s;
}

/** Kratko: "3.–7.11." ili "29.9.–3.10." */
function week_short(DateTimeInterface|string $mon): string
{
    $mon = week_monday($mon);
    $fri = $mon->modify('+4 days');
    return ($mon->format('n') === $fri->format('n') ? $mon->format('j.') : $mon->format('j.n.')) . '–' . $fri->format('j.n.');
}

/** "ova nedelja" / "sledeća nedelja" / "za 2 nedelje" / "prošla nedelja" / "" */
function week_relative(DateTimeInterface|string $mon): string
{
    $diff = (int) round((week_monday($mon)->getTimestamp() - week_monday(today())->getTimestamp()) / (7 * 86400));
    return match (true) {
        $diff === 0 => 'ova nedelja',
        $diff === 1 => 'sledeća nedelja',
        $diff === -1 => 'prošla nedelja',
        $diff > 1 && $diff < 5 => "za $diff nedelje",
        $diff >= 5 => "za $diff nedelja",
        default => '',
    };
}

/** Za padajući meni: "pon 10.11. – pet 14.11. (sledeća nedelja)" */
function week_option_label(DateTimeImmutable $mon): string
{
    $rel = week_relative($mon);
    return week_label($mon) . ($rel !== '' ? " ($rel)" : '');
}

/** Opcije nedelja: tekuća + narednih $count-1; uključuje i $include ako je ranija. */
function week_options(?string $include = null, int $count = 16): array
{
    $mon = week_monday(today());
    $opts = [];
    if ($include && $include < $mon->format('Y-m-d')) {
        $opts[$include] = week_option_label(new DateTimeImmutable($include));
    }
    for ($i = 0; $i < $count; $i++) {
        $w = $mon->modify("+$i weeks");
        $opts[$w->format('Y-m-d')] = week_option_label($w);
    }
    return $opts;
}

/** "7.11" */
function fmt_dm(?string $date): string
{
    return $date ? (new DateTimeImmutable($date))->format('j.n') : '';
}

/** "14.11.2026" */
function fmt_date(?string $date): string
{
    return $date ? (new DateTimeImmutable($date))->format('j.n.Y') : '';
}

function fmt_money(?string $amount): string
{
    if ($amount === null || $amount === '') {
        return '';
    }
    $f = (float) $amount;
    $dec = fmod($f, 1.0) == 0.0 ? 0 : 2;
    return number_format($f, $dec, ',', '.') . ' €';
}

/** Broj dana kašnjenja (0 ako ne kasni). */
function days_late(?string $date): int
{
    if (!$date) {
        return 0;
    }
    $diff = (int) (new DateTimeImmutable($date))->diff(today())->format('%r%a');
    return max(0, $diff);
}

function short_desc(?string $desc, int $len = 60): string
{
    $line = trim(strtok((string) $desc, "\n") ?: '');
    return mb_strlen($line) > $len ? mb_substr($line, 0, $len - 1) . '…' : $line;
}

// ── Šifra projekta ──────────────────────────────────────────

/** Sledeća šifra "2026-214". Pozivati UNUTAR transakcije. */
function next_project_code(): string
{
    $year = date('Y');
    $max = (int) q_val(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(code, 6) AS UNSIGNED)), 0) FROM projects WHERE code LIKE ? FOR UPDATE",
        [$year . '-%']
    );
    return sprintf('%s-%03d', $year, $max + 1);
}

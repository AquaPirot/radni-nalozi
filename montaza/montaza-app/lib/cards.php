<?php
declare(strict_types=1);

// Kartica projekta — ista na listi, na "Danas" i u planu.
// Hijerarhija: 1) kupac · mesto  2) sistemi  3) faza + odgovoran  4) termin  5) upozorenje (samo ako postoji)

/** [tekst, klasa] upozorenja ili null. */
function project_warning(array $b, array $user): ?array
{
    $p = $b['p'];
    $nv = next_visit($b['visits']);
    if ($b['stops']) {
        return [stoppage_text($b['stops'][0], $nv), 'warn'];
    }
    if ($late = project_late($p)) {
        return ['Kasni ' . $late . ' ' . ($late === 1 ? 'dan' : 'dana') . ' (rok ' . fmt_dm($p['deadline']) . ')', 'warn'];
    }
    if ($p['status'] === 'aktivan' && $nv && $nv['start_date'] && $nv['status'] === 'planiran' && $nv['kind'] !== 'dijagnoza') {
        $soon = $nv['start_date'] <= today()->modify('+7 days')->format('Y-m-d');
        if ($soon && $b['phases'] && !phases_done($b['phases']) && !$nv['readiness_override']) {
            return ['Termin ' . visit_when($nv, false) . ', a proizvod nije spreman', 'warn'];
        }
        if ($soon && !$nv['confirmed']) {
            return ['Termin ' . visit_when($nv, false) . ' nije potvrđen sa kupcem', 'info'];
        }
    }
    if ($p['status'] === 'aktivan' && $b['phases']) {
        $o = current_order($b['orders']);
        if (!$o) {
            return ['Nalog nije izdat', 'info'];
        }
        if (!$o['accepted_at']) {
            return ['Nalog v' . $o['version'] . ' nije preuzet u hali', 'info'];
        }
    }
    if (can_see_payment($user)) {
        $pay = payment_info($b);
        if ($pay['due'] && $pay['remaining'] > 0) {
            return ['Za naplatu ' . fmt_money((string) $pay['remaining']) . ($pay['status'] === 'delimicno' ? ' (delimično plaćeno)' : ''), 'pay'];
        }
    }
    return null;
}

function pcard(array $b, array $user, int $tasks = 0): string
{
    $p = $b['p'];
    [$stKey, $stLabel] = project_stage($b);
    [$naText, $naWho] = next_action($b);
    $nv = next_visit($b['visits']);
    $warn = project_warning($b, $user);
    [$wtag, $wcls] = work_tag($p);

    $h = '<a class="pcard" href="' . e(url('projekat', ['id' => $p['id']])) . '">';
    $h .= '<div class="pc-1"><strong>' . e($p['customer_name']) . '</strong>' . ($p['city'] ? '<span> · ' . e($p['city']) . '</span>' : '') . '</div>';
    $sys = systems_summary($b['systems']);
    if ($sys !== '' || $p['work_type'] !== 'novi') {
        $h .= '<div class="pc-2">' . ($p['work_type'] !== 'novi' ? '<span class="tag ' . $wcls . '">' . e($wtag) . '</span> ' : '') . e($sys) . '</div>';
    }
    // Faza + odgovorna osoba (radnja se ne ponavlja ako je već prikazana kao upozorenje)
    $quiet = in_array($stKey, ['nacrt', 'zavrseno', 'otkazan'], true) || $b['stops'];
    $line3 = trim(($quiet ? '' : $naText) . ($naWho !== '' && !in_array($stKey, ['zavrseno', 'otkazan'], true) ? ($quiet ? '' : ' · ') . $naWho : ''));
    $h .= '<div class="pc-3"><span class="pill stage-' . $stKey . '">' . e($stLabel) . '</span><span class="muted">' . e($line3) . '</span></div>';
    if ($nv) {
        $h .= '<div class="pc-4 mono">' . icon('plan', 16) . e(VISIT_KINDS[$nv['kind']] . ' ' . visit_when($nv)) . ($nv['start_date'] ? ($nv['confirmed'] ? ' · potvrđeno' : ' · okvirno') : '') . '</div>';
    } elseif ($p['deadline'] && $stKey !== 'zavrseno') {
        $h .= '<div class="pc-4 mono">' . icon('plan', 16) . 'rok ' . e(fmt_dm($p['deadline'])) . ' · termin nije zakazan</div>';
    }
    if ($warn || $tasks) {
        $h .= '<div class="pc-5">';
        if ($warn) {
            $h .= '<span class="status ' . e($warn[1]) . '"><i></i>' . e($warn[0]) . '</span>';
        }
        if ($tasks) {
            $h .= '<span class="paytag task-count">' . icon('zadaci', 16) . $tasks . ' ' . ($tasks === 1 ? 'zadatak' : 'zadatka') . '</span>';
        }
        $h .= '</div>';
    }
    return $h . '</a>';
}

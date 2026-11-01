<?php
declare(strict_types=1);

// Filtriran spisak sa kartica na "Danas" (?p=pregled&f=odluka|zastoj|kasni|naplata) — vlasnik.

$lists = ['odluka' => 'Traži odluku', 'zastoj' => 'U zastoju', 'kasni' => 'Kasni', 'naplata' => 'Za naplatu'];
$f = isset($lists[$_GET['f'] ?? '']) ? $_GET['f'] : 'odluka';
$items = match ($f) {
    'odluka' => owner_decisions($user),
    'zastoj' => open_stops_list($user),
    'kasni'  => late_list($user),
    default  => due_payments()[0],
};
$cash = [];
if ($f === 'naplata') {
    foreach (q_all("SELECT p.*, pr.customer_name, pr.city FROM payments p JOIN projects pr ON pr.id = p.project_id
                    WHERE p.method = 'gotovina' AND p.handed_at IS NULL ORDER BY p.received_by, p.received_at") as $x) {
        $cash[(int) $x['received_by']][] = $x;
    }
}
view('pregled', [
    'title'    => $lists[$f],
    'eyebrow'  => 'DANAS · PREGLED',
    'tab'      => 'danas',
    'user'     => $user,
    'f'        => $f,
    'lists'    => $lists,
    'items'    => $items,
    'cash'     => $cash,
    'paySum'   => $f === 'naplata' ? due_payments()[1] : 0,
]);

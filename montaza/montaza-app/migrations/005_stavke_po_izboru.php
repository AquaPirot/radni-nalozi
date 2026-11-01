<?php
declare(strict_types=1);

// Stavke se biraju po projektu (giljotine nemaju ceradu…). Reklamacija = opis + slika + jedna stavka „Servis".
// - project_items.kind postaje tekst, dodaje se label (naziv stavke iz spiska u Podešavanjima)
// - montaža/servis idu na kraj (sort 900)
// - kod reklamacija se uklanjaju nezapočete stavke dijagnoza/materijal; svaka reklamacija ima „Servis"

return function (PDO $pdo): void {
    $q = function (string $sql, array $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s; };
    $has = fn(string $t, string $c) => (bool) $q('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$t, $c])->fetchColumn();

    $pdo->exec("ALTER TABLE project_items MODIFY kind VARCHAR(30) NOT NULL");
    if (!$has('project_items', 'label')) {
        $pdo->exec("ALTER TABLE project_items ADD COLUMN label VARCHAR(80) NULL AFTER kind");
    }
    $pdo->exec("UPDATE project_items SET sort = 900 WHERE kind IN ('montaza','servis') AND sort < 900");

    $pdo->exec("DELETE i FROM project_items i JOIN projects p ON p.id = i.project_id
                WHERE p.type = 'reklamacija' AND i.kind IN ('dijagnoza','materijal') AND i.status <> 'zavrseno'");
    foreach ($q("SELECT p.id, p.status FROM projects p WHERE p.type = 'reklamacija'
                 AND NOT EXISTS (SELECT 1 FROM project_items i WHERE i.project_id = p.id AND i.kind = 'servis')")->fetchAll() as $p) {
        $q("INSERT INTO project_items (project_id, kind, sort, responsible, status, completed_at) VALUES (?, 'servis', 900, 'teren', ?, ?)",
            [$p['id'], $p['status'] === 'zavrsen' ? 'zavrseno' : 'ceka', $p['status'] === 'zavrsen' ? date('Y-m-d H:i:s') : null]);
    }

    if (!$q("SELECT 1 FROM settings WHERE `key` = 'item_catalog'")->fetchColumn()) {
        $q("INSERT INTO settings (`key`, `value`) VALUES ('item_catalog', ?)", ["Čelik\nAluminijum\nCerada\nStaklo\nGiljotina\nZIP zavese\nPakovanje"]);
    }
};

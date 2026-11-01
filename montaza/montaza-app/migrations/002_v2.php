<?php
declare(strict_types=1);

// v2: sistemi, faze po sistemu, nalozi sa verzijama, ekipe, termini, uplate, zastoj v2, zadaci v2.
// Postojeći podaci (v1: project_items) se prevode u novi model. Bezbedno za ponovno pokretanje.

return function (PDO $pdo): void {
    $exec = fn(string $sql) => $pdo->exec($sql);
    $T = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    // ── 1. Nove tabele ─────────────────────────────────────
    $exec("CREATE TABLE IF NOT EXISTS system_types (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(80) NOT NULL,
        fields VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'polja specifikacije, zarezom',
        sort SMALLINT NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id)
    ) $T");

    $exec("CREATE TABLE IF NOT EXISTS phase_templates (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        system_type_id INT UNSIGNED NOT NULL,
        name VARCHAR(100) NOT NULL,
        sort SMALLINT NOT NULL DEFAULT 0,
        requires_photo TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY ix_pt_type (system_type_id, sort),
        CONSTRAINT fk_pt_type FOREIGN KEY (system_type_id) REFERENCES system_types(id) ON DELETE CASCADE
    ) $T");

    $exec("CREATE TABLE IF NOT EXISTS project_systems (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        project_id INT UNSIGNED NOT NULL,
        system_type_id INT UNSIGNED NULL,
        qty SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        width DECIMAL(6,2) NULL COMMENT 'širina, m',
        length DECIMAL(6,2) NULL COMMENT 'dubina/izbačaj, m',
        height DECIMAL(6,2) NULL COMMENT 'visina, m',
        ral VARCHAR(60) NULL,
        spec JSON NULL,
        note TEXT NULL,
        with_steel TINYINT(1) NOT NULL DEFAULT 0,
        sort SMALLINT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_ps_project (project_id, sort),
        CONSTRAINT fk_ps_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_ps_type FOREIGN KEY (system_type_id) REFERENCES system_types(id) ON DELETE SET NULL
    ) $T");

    $exec("CREATE TABLE IF NOT EXISTS production_phases (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        project_id INT UNSIGNED NOT NULL,
        system_id INT UNSIGNED NOT NULL,
        name VARCHAR(100) NOT NULL,
        sort SMALLINT NOT NULL DEFAULT 0,
        assignee_id INT UNSIGNED NULL,
        status ENUM('ceka','u_toku','zavrseno','zastoj') NOT NULL DEFAULT 'ceka',
        due_date DATE NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        completed_by INT UNSIGNED NULL,
        note TEXT NULL,
        requires_photo TINYINT(1) NOT NULL DEFAULT 1,
        imported TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY ix_ph_project (project_id, system_id, sort),
        KEY ix_ph_status (status),
        CONSTRAINT fk_ph_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_ph_system FOREIGN KEY (system_id) REFERENCES project_systems(id) ON DELETE CASCADE,
        CONSTRAINT fk_ph_assignee FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_ph_done_by FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
    ) $T");

    $exec("CREATE TABLE IF NOT EXISTS project_orders (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        project_id INT UNSIGNED NOT NULL,
        version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        file_id INT UNSIGNED NULL,
        note VARCHAR(500) NULL,
        issued_by INT UNSIGNED NULL,
        issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        accepted_by INT UNSIGNED NULL,
        accepted_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY ix_po_project (project_id, version),
        CONSTRAINT fk_po_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_po_file FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE SET NULL,
        CONSTRAINT fk_po_issued FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_po_accepted FOREIGN KEY (accepted_by) REFERENCES users(id) ON DELETE SET NULL
    ) $T");

    $exec("CREATE TABLE IF NOT EXISTS teams (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(80) NOT NULL,
        leader_id INT UNSIGNED NULL,
        members VARCHAR(255) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort SMALLINT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        CONSTRAINT fk_team_leader FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE SET NULL
    ) $T");

    $exec("CREATE TABLE IF NOT EXISTS visits (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        project_id INT UNSIGNED NOT NULL,
        kind ENUM('montaza','servis','dijagnoza','dorada') NOT NULL DEFAULT 'montaza',
        team_id INT UNSIGNED NULL,
        start_date DATE NULL,
        start_time TIME NULL,
        days TINYINT UNSIGNED NOT NULL DEFAULT 1,
        confirmed TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = okvirno, 1 = potvrđeno sa kupcem',
        status ENUM('planiran','u_toku','zavrseno','otkazan') NOT NULL DEFAULT 'planiran',
        result ENUM('celo','delimicno','ponovni') NULL,
        remaining TEXT NULL,
        parent_visit_id INT UNSIGNED NULL,
        readiness_override VARCHAR(500) NULL,
        override_by INT UNSIGNED NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        completed_by INT UNSIGNED NULL,
        note TEXT NULL,
        video_sent TINYINT(1) NOT NULL DEFAULT 0,
        handover_done TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_v_project (project_id),
        KEY ix_v_date (start_date, status),
        KEY ix_v_team (team_id, start_date),
        CONSTRAINT fk_v_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_v_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
        CONSTRAINT fk_v_parent FOREIGN KEY (parent_visit_id) REFERENCES visits(id) ON DELETE SET NULL,
        CONSTRAINT fk_v_done_by FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
    ) $T");

    $exec("CREATE TABLE IF NOT EXISTS payments (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        project_id INT UNSIGNED NOT NULL,
        visit_id INT UNSIGNED NULL,
        amount DECIMAL(10,2) NOT NULL,
        method ENUM('gotovina','racun','kartica') NOT NULL DEFAULT 'gotovina',
        received_by INT UNSIGNED NULL,
        received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        note VARCHAR(500) NULL,
        handed_at DATETIME NULL COMMENT 'gotovina predata firmi',
        handed_to INT UNSIGNED NULL,
        PRIMARY KEY (id),
        KEY ix_pay_project (project_id),
        KEY ix_pay_received (received_at),
        CONSTRAINT fk_pay_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_pay_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL,
        CONSTRAINT fk_pay_by FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_pay_to FOREIGN KEY (handed_to) REFERENCES users(id) ON DELETE SET NULL
    ) $T");

    $exec("CREATE TABLE IF NOT EXISTS task_comments (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        task_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NULL,
        kind ENUM('komentar','ponovo_otvoren','sistem') NOT NULL DEFAULT 'komentar',
        body TEXT NULL,
        file_id INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_tc_task (task_id, created_at),
        CONSTRAINT fk_tc_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        CONSTRAINT fk_tc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_tc_file FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE SET NULL
    ) $T");

    // ── 2. Izmene postojećih tabela ────────────────────────
    $add = function (string $table, string $col, string $def) use ($exec) {
        if (!column_exists($table, $col)) {
            $exec("ALTER TABLE $table ADD COLUMN $col $def");
        }
    };
    $add('projects', 'work_type', "ENUM('novi','servis','reklamacija') NOT NULL DEFAULT 'novi' AFTER type");
    $add('projects', 'intake', "ENUM('novi','preuzet') NOT NULL DEFAULT 'novi' AFTER work_type");
    $add('projects', 'payment_collector', "ENUM('teren','vlasnik','racun') NOT NULL DEFAULT 'teren' AFTER payment_amount");
    $add('projects', 'payment_currency', "CHAR(3) NOT NULL DEFAULT 'EUR' AFTER payment_collector");
    $add('projects', 'released_at', 'DATETIME NULL');
    $add('projects', 'archived_at', 'DATETIME NULL');
    $add('projects', 'migrated_v1', 'TINYINT(1) NOT NULL DEFAULT 0');
    $exec("ALTER TABLE projects MODIFY status ENUM('nacrt','aktivan','zavrsen','otkazan') NOT NULL DEFAULT 'aktivan'");
    $exec("ALTER TABLE projects MODIFY type ENUM('novi','zapocet','reklamacija') NULL");

    $add('files', 'phase_id', 'INT UNSIGNED NULL AFTER item_id');
    $add('files', 'visit_id', 'INT UNSIGNED NULL AFTER phase_id');
    $add('files', 'purpose', "ENUM('celina','detalj','nedostatak') NULL AFTER kind");
    $add('files', 'pending', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'poslato, još nije zakačeno'");
    $exec("ALTER TABLE files MODIFY kind ENUM('lokacija_pre','posle','foto','nalog_pdf','dokument') NOT NULL");

    $add('stoppages', 'phase_id', 'INT UNSIGNED NULL AFTER item_id');
    $add('stoppages', 'visit_id', 'INT UNSIGNED NULL AFTER phase_id');
    $add('stoppages', 'owner_id', 'INT UNSIGNED NULL COMMENT \'ko rešava\'');
    $add('stoppages', 'next_step', 'VARCHAR(255) NULL');
    $add('stoppages', 'expected_date', 'DATE NULL');
    $exec("ALTER TABLE stoppages MODIFY reason ENUM('materijal','proizvodnja','kupac','teren','vreme','transport','ekipa','ostalo') NOT NULL");

    $add('tasks', 'no_deadline', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER due_date');
    $add('tasks', 'due_time', 'TIME NULL AFTER due_date');
    $add('tasks', 'accepted_at', 'DATETIME NULL');
    $add('tasks', 'archived_at', 'DATETIME NULL');

    foreach (['files' => ['phase_id' => 'production_phases', 'visit_id' => 'visits'], 'stoppages' => ['phase_id' => 'production_phases', 'visit_id' => 'visits', 'owner_id' => 'users']] as $table => $fks) {
        foreach ($fks as $col => $ref) {
            $name = "fk_{$table}_{$col}";
            $has = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND constraint_name = '$name'")->fetchColumn();
            if (!$has) {
                $onDel = $ref === 'users' ? 'SET NULL' : ($table === 'files' ? 'SET NULL' : 'CASCADE');
                $exec("ALTER TABLE $table ADD CONSTRAINT $name FOREIGN KEY ($col) REFERENCES $ref(id) ON DELETE $onDel");
            }
        }
    }

    // ── 3. Početni katalog: sistemi i faze (vlasnik menja u Podešavanjima) ──
    if (!(int) $pdo->query('SELECT COUNT(*) FROM system_types')->fetchColumn()) {
        $catalog = [
            ['Klasična pergola', 'platno,motor,rasveta,senzori', ['Aluminijum', 'Platno', 'Oprema', 'Kontrola', 'Pakovanje']],
            ['BIOFIX',           'krov,motor,rasveta,odvodnjavanje,senzori', ['Konstrukcija', 'Paneli', 'Odvodnjavanje i rasveta', 'Kontrola', 'Pakovanje']],
            ['Bioklimatska',     'lamele,motor,rasveta,odvodnjavanje,senzori', ['Konstrukcija', 'Lamele', 'Motor i rasveta', 'Kontrola', 'Pakovanje']],
            ['ZIP screen',       'platno,motor,kutija', ['Profili', 'Platno', 'Motor', 'Kontrola', 'Pakovanje']],
            ['Giljotina',        'staklo,motor', ['Profili', 'Mehanizam', 'Staklo', 'Provera rada', 'Pakovanje']],
            ['Slajding',         'staklo,broj_krila', ['Profili', 'Staklo', 'Kompletiranje', 'Pakovanje']],
            ['Drugo',            '', ['Izrada', 'Pakovanje']],
        ];
        $insT = $pdo->prepare('INSERT INTO system_types (name, fields, sort) VALUES (?, ?, ?)');
        $insP = $pdo->prepare('INSERT INTO phase_templates (system_type_id, name, sort) VALUES (?, ?, ?)');
        foreach ($catalog as $i => [$name, $fields, $phases]) {
            $insT->execute([$name, $fields, ($i + 1) * 10]);
            $tid = (int) $pdo->lastInsertId();
            foreach ($phases as $j => $ph) {
                $insP->execute([$tid, $ph, ($j + 1) * 10]);
            }
        }
    }
    $typeId = function (string $name) use ($pdo): int {
        $st = $pdo->prepare('SELECT id FROM system_types WHERE name = ?');
        $st->execute([$name]);
        return (int) $st->fetchColumn();
    };

    // Početna ekipa
    if (!(int) $pdo->query('SELECT COUNT(*) FROM teams')->fetchColumn()) {
        $leader = $pdo->query("SELECT id, name FROM users WHERE role = 'teren' AND active = 1 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare('INSERT INTO teams (name, leader_id, sort) VALUES (?, ?, 10)')
            ->execute([($leader['name'] ?? 'Teren') . ' + ekipa', $leader['id'] ?? null]);
    }
    $teamId = (int) $pdo->query('SELECT id FROM teams ORDER BY sort, id LIMIT 1')->fetchColumn();

    foreach ([['default_currency', 'EUR'], ['plan_days_ahead', '7']] as [$k, $val]) {
        $pdo->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)')->execute([$k, $val]);
    }

    // ── 4. Prevod postojećih projekata (v1 → v2) ───────────
    $labels = ['celik' => 'Čelična konstrukcija', 'aluminijum' => 'Aluminijum', 'cerada' => 'Cerada', 'pakovanje' => 'Pakovanje', 'materijal' => 'Materijal'];
    $guess = function (?string $desc, string $type) use ($typeId): int {
        $d = mb_strtolower((string) $desc);
        foreach (['biofix' => 'BIOFIX', 'bioklim' => 'Bioklimatska', 'zip' => 'ZIP screen', 'giljotin' => 'Giljotina', 'slajd' => 'Slajding', 'sliding' => 'Slajding'] as $k => $name) {
            if (str_contains($d, $k)) {
                return $typeId($name);
            }
        }
        return $typeId($type === 'reklamacija' ? 'Drugo' : 'Klasična pergola');
    };

    $projects = $pdo->query('SELECT * FROM projects WHERE migrated_v1 = 0')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($projects as $p) {
        $pdo->beginTransaction();
        try {
            $pid = (int) $p['id'];
            $pdo->prepare('UPDATE projects SET work_type = ?, intake = ?, released_at = COALESCE(released_at, created_at) WHERE id = ?')
                ->execute([$p['type'] === 'reklamacija' ? 'reklamacija' : 'novi', $p['type'] === 'zapocet' ? 'preuzet' : 'novi', $pid]);

            $st = $pdo->prepare('SELECT * FROM project_items WHERE project_id = ? ORDER BY sort, id');
            $st->execute([$pid]);
            $items = $st->fetchAll(PDO::FETCH_ASSOC);
            $prodStarted = false;
            foreach ($items as $it) {
                if (isset($labels[$it['kind']]) && ($it['status'] !== 'ceka' || $it['imported'])) {
                    $prodStarted = true;
                }
            }

            // Nalog → project_orders v1
            foreach ($items as $it) {
                if ($it['kind'] !== 'nalog' || $it['status'] !== 'zavrseno') {
                    continue;
                }
                $f = $pdo->prepare("SELECT id FROM files WHERE item_id = ? AND kind = 'nalog_pdf' ORDER BY id LIMIT 1");
                $f->execute([$it['id']]);
                $fileId = $f->fetchColumn() ?: null;
                $pdo->prepare('INSERT INTO project_orders (project_id, version, file_id, note, issued_by, issued_at, accepted_by, accepted_at) VALUES (?, 1, ?, ?, ?, ?, ?, ?)')
                    ->execute([$pid, $fileId, $fileId ? null : 'Uneto pri preuzimanju započetog posla', $it['completed_by'], $it['completed_at'] ?? $p['created_at'],
                        $prodStarted ? $it['completed_by'] : null, $prodStarted ? ($it['completed_at'] ?? $p['created_at']) : null]);
            }

            // Proizvodne stavke → jedan sistem sa fazama
            $prodItems = array_filter($items, fn($it) => isset($labels[$it['kind']]));
            if ($prodItems) {
                $pdo->prepare('INSERT INTO project_systems (project_id, system_type_id, qty, note, with_steel, sort) VALUES (?, ?, 1, ?, ?, 10)')
                    ->execute([$pid, $guess($p['description'], (string) $p['type']), $p['description'], (int) $p['has_steel']]);
                $sid = (int) $pdo->lastInsertId();
                foreach ($prodItems as $it) {
                    $pdo->prepare('INSERT INTO production_phases (project_id, system_id, name, sort, status, due_date, started_at, completed_at, completed_by, note, imported)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$pid, $sid, $labels[$it['kind']], $it['sort'], $it['status'], $it['due_date'], $it['started_at'], $it['completed_at'], $it['completed_by'], $it['note'], $it['imported']]);
                    $phId = (int) $pdo->lastInsertId();
                    $pdo->prepare('UPDATE files SET phase_id = ? WHERE item_id = ?')->execute([$phId, $it['id']]);
                    $pdo->prepare('UPDATE stoppages SET phase_id = ? WHERE item_id = ?')->execute([$phId, $it['id']]);
                }
            }

            // Terenske stavke → termini
            foreach ($items as $it) {
                $kind = ['montaza' => 'montaza', 'servis' => 'servis', 'dijagnoza' => 'dijagnoza'][$it['kind']] ?? null;
                if (!$kind) {
                    continue;
                }
                $done = $it['status'] === 'zavrseno';
                $pdo->prepare('INSERT INTO visits (project_id, kind, team_id, start_date, days, confirmed, status, result, started_at, completed_at, completed_by, note, video_sent, handover_done, created_at)
                               VALUES (?, ?, ?, ?, 1, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$pid, $kind, $teamId, $p['install_week_start'],
                        $done ? 'zavrseno' : ($it['status'] === 'u_toku' ? 'u_toku' : 'planiran'), $done ? 'celo' : null,
                        $it['started_at'], $it['completed_at'], $it['completed_by'], $it['note'],
                        $done && $kind === 'montaza' ? (int) $p['video_sent_viber'] : 0, $done && $kind === 'montaza' ? (int) $p['handover_done'] : 0,
                        $p['created_at']]);
                $vid = (int) $pdo->lastInsertId();
                $pdo->prepare("UPDATE files SET visit_id = ?, purpose = CASE kind WHEN 'posle' THEN 'celina' WHEN 'foto' THEN 'detalj' ELSE purpose END WHERE item_id = ?")->execute([$vid, $it['id']]);
                $pdo->prepare('UPDATE stoppages SET visit_id = ? WHERE item_id = ?')->execute([$vid, $it['id']]);
            }

            // Potvrđena naplata → uplata (gotovina, još nije predata firmi)
            if ($p['payment_confirmed_at'] && $p['payment_received'] !== null) {
                $pdo->prepare("INSERT INTO payments (project_id, amount, method, received_by, received_at, note) VALUES (?, ?, 'gotovina', ?, ?, 'preneto iz v1')")
                    ->execute([$pid, $p['payment_received'], $p['payment_confirmed_by'], $p['payment_confirmed_at']]);
            }

            $pdo->prepare('UPDATE projects SET migrated_v1 = 1 WHERE id = ?')->execute([$pid]);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

    // Zastoji iz v1: "ekipa" → "teren"
    $exec("UPDATE stoppages SET reason = 'teren' WHERE reason = 'ekipa'");
    // Zadaci: bez roka ako ga nisu imali; započeti/završeni se smatraju preuzetim
    $exec("UPDATE tasks SET no_deadline = 1 WHERE due_date IS NULL");
    $exec("UPDATE tasks SET accepted_at = COALESCE(started_at, completed_at) WHERE accepted_at IS NULL AND status <> 'ceka'");
};

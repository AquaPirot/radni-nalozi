<?php
declare(strict_types=1);

// v2.1: svi vide sve; komentari na projektu (svi prate i komentarišu).

return function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_comments (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        project_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NULL,
        body TEXT NULL,
        file_id INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_pc_project (project_id, created_at),
        KEY ix_pc_created (created_at),
        CONSTRAINT fk_pc_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        CONSTRAINT fk_pc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_pc_file FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};

-- MONTAŽA — šema baze
-- MySQL 8 / MariaDB 10.4+, InnoDB, utf8mb4_unicode_ci
-- Uvoz: phpMyAdmin → izaberi bazu → Import → ovaj fajl.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(100) NOT NULL,
    username         VARCHAR(50)  NOT NULL,
    password_hash    VARCHAR(255) NOT NULL,
    role             ENUM('vlasnik','hala','teren') NOT NULL,
    telegram_chat_id VARCHAR(50)  NULL,
    active           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Zapamti me" tokeni (30 dana). Čuva se samo hash tokena.
CREATE TABLE IF NOT EXISTS auth_tokens (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    selector    CHAR(24)     NOT NULL,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_tokens_selector (selector),
    KEY ix_auth_tokens_user (user_id),
    CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ograničenje pokušaja logovanja (5 / 15 min po IP-u)
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip         VARCHAR(45)  NOT NULL,
    username   VARCHAR(50)  NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_login_attempts_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                  VARCHAR(20)  NOT NULL,
    type                  ENUM('novi','zapocet','reklamacija') NOT NULL,
    parent_project_id     INT UNSIGNED NULL,
    customer_name         VARCHAR(150) NOT NULL,
    phone                 VARCHAR(50)  NULL,
    city                  VARCHAR(100) NULL,
    address               VARCHAR(255) NULL,
    description           TEXT         NULL,
    has_steel             TINYINT(1)   NOT NULL DEFAULT 0,
    install_week_start    DATE         NULL COMMENT 'ponedeljak nedelje montaže; NULL = nije raspoređeno',
    deadline              DATE         NULL,
    payment_when          ENUM('pre','posle','ne') NOT NULL DEFAULT 'ne',
    payment_amount        DECIMAL(10,2) NULL,
    payment_received      DECIMAL(10,2) NULL,
    payment_confirmed_at  DATETIME     NULL,
    payment_confirmed_by  INT UNSIGNED NULL,
    status                ENUM('aktivan','zavrsen','otkazan') NOT NULL DEFAULT 'aktivan',
    video_sent_viber      TINYINT(1)   NOT NULL DEFAULT 0,
    handover_done         TINYINT(1)   NOT NULL DEFAULT 0,
    notes                 TEXT         NULL,
    created_by            INT UNSIGNED NULL,
    created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at          DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_code (code),
    KEY ix_projects_status_week (status, install_week_start),
    KEY ix_projects_parent (parent_project_id),
    CONSTRAINT fk_projects_parent       FOREIGN KEY (parent_project_id)    REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_pay_by       FOREIGN KEY (payment_confirmed_by) REFERENCES users(id)    ON DELETE SET NULL,
    CONSTRAINT fk_projects_created_by   FOREIGN KEY (created_by)           REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_items (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id   INT UNSIGNED NOT NULL,
    kind         ENUM('nalog','celik','aluminijum','cerada','pakovanje','montaza','dijagnoza','materijal','servis') NOT NULL,
    sort         SMALLINT     NOT NULL DEFAULT 0,
    responsible  ENUM('vlasnik','hala','teren') NOT NULL,
    due_date     DATE         NULL,
    status       ENUM('ceka','u_toku','zavrseno','zastoj') NOT NULL DEFAULT 'ceka',
    started_at   DATETIME     NULL,
    completed_at DATETIME     NULL,
    completed_by INT UNSIGNED NULL,
    note         TEXT         NULL,
    imported     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'zatvoreno pri unosu započetog projekta, bez slika',
    PRIMARY KEY (id),
    KEY ix_items_project_sort (project_id, sort),
    KEY ix_items_resp_status (responsible, status),
    CONSTRAINT fk_items_project      FOREIGN KEY (project_id)   REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_completed_by FOREIGN KEY (completed_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stoppages (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id  INT UNSIGNED NOT NULL,
    item_id     INT UNSIGNED NULL,
    reason      ENUM('materijal','vreme','kupac','ekipa','ostalo') NOT NULL,
    note        VARCHAR(500) NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME     NULL,
    resolved_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY ix_stoppages_project (project_id),
    KEY ix_stoppages_created (created_at),
    CONSTRAINT fk_stop_project     FOREIGN KEY (project_id)  REFERENCES projects(id)      ON DELETE CASCADE,
    CONSTRAINT fk_stop_item        FOREIGN KEY (item_id)     REFERENCES project_items(id) ON DELETE SET NULL,
    CONSTRAINT fk_stop_created_by  FOREIGN KEY (created_by)  REFERENCES users(id)         ON DELETE SET NULL,
    CONSTRAINT fk_stop_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS files (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id  INT UNSIGNED NOT NULL,
    item_id     INT UNSIGNED NULL,
    kind        ENUM('lokacija_pre','posle','foto','nalog_pdf') NOT NULL,
    path        VARCHAR(255) NOT NULL,
    thumb_path  VARCHAR(255) NULL,
    mime        VARCHAR(50)  NOT NULL,
    width       SMALLINT UNSIGNED NULL,
    height      SMALLINT UNSIGNED NULL,
    size        INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_files_project (project_id),
    KEY ix_files_item (item_id),
    CONSTRAINT fk_files_project FOREIGN KEY (project_id)  REFERENCES projects(id)      ON DELETE CASCADE,
    CONSTRAINT fk_files_item    FOREIGN KEY (item_id)     REFERENCES project_items(id) ON DELETE SET NULL,
    CONSTRAINT fk_files_user    FOREIGN KEY (uploaded_by) REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    `key`   VARCHAR(64) NOT NULL,
    `value` TEXT        NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nikad se ne briše. Bez FK na projekat da istorija preživi brisanje projekta.
CREATE TABLE IF NOT EXISTS activity_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NULL,
    item_id    INT UNSIGNED NULL,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(50)  NOT NULL,
    details    JSON         NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_log_project (project_id, created_at),
    KEY ix_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Podrazumevana podešavanja (vlasnik ih menja iz aplikacije)
INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('weekly_capacity',        '5'),
    ('min_photos_hala',        '1'),
    ('min_photos_montaza',     '3'),
    ('currency',               'EUR'),
    ('telegram_bot_token',     ''),
    ('telegram_owner_chat_id', '');

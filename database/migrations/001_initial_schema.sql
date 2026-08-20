-- project.portal — initial schema
-- Data model per docs/notes.docx (2026-08-18 revision).

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Admin accounts. The first account created by the installer is protected:
-- is_protected rows can never be deleted, disabled, or stripped of MFA.
-- ---------------------------------------------------------------------
CREATE TABLE admins (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(64)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    mfa_secret      VARCHAR(255) NULL,          -- encrypted TOTP secret; NULL until enrolled
    mfa_enabled     TINYINT(1)   NOT NULL DEFAULT 0,
    force_mfa_setup TINYINT(1)   NOT NULL DEFAULT 1, -- forced re-enrollment after MFA reset
    is_protected    TINYINT(1)   NOT NULL DEFAULT 0, -- true only for the first admin
    status          ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mfa_recovery_codes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT UNSIGNED NOT NULL,
    code_hash   VARCHAR(255) NOT NULL,
    used_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Single-row settings tables. Row id is always 1; application enforces
-- exactly one row and updates in place rather than inserting.
-- ---------------------------------------------------------------------
CREATE TABLE site_settings (
    id                  TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    is_demo         TINYINT(1)   NOT NULL DEFAULT 0,  -- seeded sample row/value; see "Delete demo content"
    page_title          VARCHAR(255) NOT NULL DEFAULT 'Portal',
    initials            VARCHAR(8)   NOT NULL DEFAULT 'NA',
    display_name        VARCHAR(128) NOT NULL DEFAULT '',
    copyright_year      VARCHAR(4)   NOT NULL DEFAULT '2026',
    copyright_text      VARCHAR(255) NOT NULL DEFAULT '',
    footer_text         VARCHAR(255) NOT NULL DEFAULT '',
    copy_link_text      VARCHAR(255) NOT NULL DEFAULT '',
    copy_link_url       VARCHAR(512) NOT NULL DEFAULT '',
    theme_default        ENUM('dark','light') NOT NULL DEFAULT 'dark',
    -- Recon tile (Circle/Tile 7) — admin-edited location, not a content post.
    recon_location_label VARCHAR(255) NULL,
    recon_lat            DECIMAL(9,6) NULL,
    recon_lng            DECIMAL(9,6) NULL,
    -- Contact tile (Tile 9) — admin-edited copy for the contact CTA.
    contact_main_title   VARCHAR(255) NOT NULL DEFAULT 'Let''s make something',
    contact_sub_title    VARCHAR(255) NOT NULL DEFAULT '',
    contact_button_text  VARCHAR(64)  NOT NULL DEFAULT 'Say hello',
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_site_settings_single_row CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO site_settings (id) VALUES (1);

CREATE TABLE profile (
    id              TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    is_demo         TINYINT(1)   NOT NULL DEFAULT 0,  -- seeded sample row/value; see "Delete demo content"
    headshot_url    VARCHAR(512) NULL,
    logo_url        VARCHAR(512) NULL,
    status_phrase   VARCHAR(255) NOT NULL DEFAULT 'Available for projects',
    bio             TEXT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_profile_single_row CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO profile (id) VALUES (1);

CREATE TABLE social_links (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    is_demo         TINYINT(1)   NOT NULL DEFAULT 0,  -- seeded sample row/value; see "Delete demo content"
    platform    ENUM('x','github','linkedin','youtube','dribbble','other') NOT NULL,
    label       VARCHAR(64) NULL,          -- used when platform = 'other'
    url         VARCHAR(512) NOT NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Tile 2: Latest Projects
-- ---------------------------------------------------------------------
CREATE TABLE projects (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    is_demo         TINYINT(1)   NOT NULL DEFAULT 0,  -- seeded sample row/value; see "Delete demo content"
    title        VARCHAR(128) NOT NULL,
    description  VARCHAR(512) NOT NULL,
    github_url   VARCHAR(512) NULL,
    external_url VARCHAR(512) NULL,
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    published    TINYINT(1) NOT NULL DEFAULT 1,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Tile 3: Toolbox
-- ---------------------------------------------------------------------
CREATE TABLE skills (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    is_demo         TINYINT(1)   NOT NULL DEFAULT 0,  -- seeded sample row/value; see "Delete demo content"
    name        VARCHAR(64) NOT NULL,
    icon_key    VARCHAR(64) NOT NULL,   -- key into the app's icon library
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Published content: News / YouTube / ProjectWork (Tiles 4, 5, 6, 8 +
-- article table/focus views). Unified table, filtered by category.
-- ---------------------------------------------------------------------
CREATE TABLE content_posts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    is_demo         TINYINT(1)   NOT NULL DEFAULT 0,  -- seeded sample row/value; see "Delete demo content"
    category      ENUM('news','youtube','project_work') NOT NULL,
    title         VARCHAR(255) NOT NULL,
    body          MEDIUMTEXT NULL,       -- Markdown/HTML, category-dependent
    link_url      VARCHAR(512) NULL,
    image_url     VARCHAR(512) NULL,
    published_at  DATETIME NOT NULL,     -- auto-populated, overridable
    is_suppressed TINYINT(1) NOT NULL DEFAULT 0, -- unpublish: acts as if it never existed
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category_published (category, is_suppressed, published_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Tile 9 Contact — submissions from the public contact form.
-- ---------------------------------------------------------------------
CREATE TABLE contact_submissions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(128) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    message     TEXT NOT NULL,
    ip_address  VARCHAR(45) NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Security: activity log (admin logins + admin actions).
-- ---------------------------------------------------------------------
CREATE TABLE activity_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT UNSIGNED NULL,
    action      VARCHAR(64) NOT NULL,      -- e.g. 'login_success', 'login_failed', 'post_created'
    detail      VARCHAR(512) NULL,
    ip_address  VARCHAR(45) NOT NULL,
    user_agent  VARCHAR(512) NULL,
    success     TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_created (admin_id, created_at),
    INDEX idx_action_created (action, created_at),
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Analytics: page views. ip is stored hashed (privacy + no PII at rest).
-- Retention policy TBD; created_at is indexed for a future pruning job.
-- ---------------------------------------------------------------------
CREATE TABLE page_views (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    path         VARCHAR(255) NOT NULL,
    ip_hash      CHAR(64) NOT NULL,
    user_agent   VARCHAR(512) NULL,
    referrer     VARCHAR(512) NULL,
    device_type  VARCHAR(20) NULL,       -- 'desktop' | 'mobile' | 'tablet' | 'bot'
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_path_created (path, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

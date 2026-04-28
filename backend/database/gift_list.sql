-- ──────────────────────────────────────────────────────────────
--  GiftList · Esquema SQL
--  Añadir a la base de datos "login_user" existente en adriru.es
--  (El instalador install.php hace esto automáticamente)
-- ──────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS gl_users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    email      VARCHAR(150)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    role       ENUM('admin','user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gl_gifts (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    name           VARCHAR(200) NOT NULL,
    description    TEXT,
    url            VARCHAR(1000),
    price          DECIMAL(10,2) UNSIGNED,
    priority       ENUM('high','medium','low') NOT NULL DEFAULT 'medium',
    priority_order TINYINT NOT NULL DEFAULT 2,
    reserved_by    INT UNSIGNED,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES gl_users(id) ON DELETE CASCADE,
    FOREIGN KEY (reserved_by) REFERENCES gl_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

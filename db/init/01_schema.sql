-- ============================================================
--  Cateeeet — MySQL DDL
--  File ini otomatis dijalankan saat container db pertama kali up
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username       VARCHAR(50)     NOT NULL UNIQUE,
    password_hash  VARCHAR(255)    NOT NULL,
    display_name   VARCHAR(100)    NOT NULL,
    email          VARCHAR(255)    NULL,
    telegram_id    BIGINT UNSIGNED NULL UNIQUE COMMENT 'Telegram chat_id untuk webhook bot',
    created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS families (
    id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name           VARCHAR(100)    NOT NULL,
    master_user_id INT UNSIGNED    NOT NULL,
    created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_families_master FOREIGN KEY (master_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS family_members (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    family_id   INT UNSIGNED    NOT NULL,
    user_id     INT UNSIGNED    NOT NULL,
    joined_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    kicked_at   TIMESTAMP       NULL     DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_family_user (family_id, user_id),
    CONSTRAINT fk_fm_family FOREIGN KEY (family_id) REFERENCES families (id),
    CONSTRAINT fk_fm_user   FOREIGN KEY (user_id)   REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id               INT UNSIGNED              NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED              NOT NULL,
    name             VARCHAR(100)              NOT NULL,
    icon             VARCHAR(10)               NULL,
    type             ENUM('in','out')          NOT NULL DEFAULT 'out',
    shared_to_family TINYINT(1)               NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT fk_cat_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wallets (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED    NOT NULL,
    name             VARCHAR(100)    NOT NULL,
    initial_balance  DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    current_balance  DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    shared_to_family TINYINT(1)      NOT NULL DEFAULT 0,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transactions (
    id            INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED        NOT NULL,
    wallet_id     INT UNSIGNED        NOT NULL,
    category_id   INT UNSIGNED        NULL,
    description   VARCHAR(255)        NOT NULL,
    amount        DECIMAL(15,2)       NOT NULL,
    type          ENUM('in','out')    NOT NULL,
    date          DATE                NOT NULL,
    raw_text      VARCHAR(500)        NULL,
    is_transfer   TINYINT(1)          NOT NULL DEFAULT 0,
    is_balancing  TINYINT(1)          NOT NULL DEFAULT 0,
    created_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_trx_user     FOREIGN KEY (user_id)     REFERENCES users (id),
    CONSTRAINT fk_trx_wallet   FOREIGN KEY (wallet_id)   REFERENCES wallets (id),
    CONSTRAINT fk_trx_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_trx_user_date    ON transactions (user_id, date);
CREATE INDEX idx_trx_wallet       ON transactions (wallet_id);
CREATE INDEX idx_fm_family_active ON family_members (family_id, kicked_at);
CREATE INDEX idx_fm_user          ON family_members (user_id);

SET FOREIGN_KEY_CHECKS = 1;
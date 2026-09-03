-- ============================================================
-- SISTEMA FINANCEIRO
-- Schema - primeira versão
-- ============================================================

CREATE DATABASE IF NOT EXISTS financeiro
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE financeiro;


-- ============================================================
-- 1. USERS
-- ============================================================

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    name VARCHAR(100) NOT NULL,

    email VARCHAR(255) NOT NULL,

    password VARCHAR(255) NOT NULL,

    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',

    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uq_users_email (email),

    INDEX idx_users_role (role),

    INDEX idx_users_active (is_active)
) ENGINE=InnoDB;


-- ============================================================
-- 2. ACCOUNTS
-- ============================================================

CREATE TABLE accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id BIGINT UNSIGNED NOT NULL,

    name VARCHAR(100) NOT NULL,

    type ENUM(
        'checking',
        'savings',
        'cash',
        'credit_card',
        'investment',
        'other'
    ) NOT NULL DEFAULT 'checking',

    initial_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,

    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT fk_accounts_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_accounts_user (user_id),

    INDEX idx_accounts_user_active (user_id, is_active)
) ENGINE=InnoDB;


-- ============================================================
-- 3. CATEGORIES
-- ============================================================

CREATE TABLE categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id BIGINT UNSIGNED NOT NULL,

    name VARCHAR(100) NOT NULL,

    type ENUM('income', 'expense') NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT fk_categories_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY uq_categories_user_name_type
        (user_id, name, type),

    INDEX idx_categories_user (user_id),

    INDEX idx_categories_user_type (user_id, type)
) ENGINE=InnoDB;


-- ============================================================
-- 4. TRANSACTIONS
-- ============================================================

CREATE TABLE transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id BIGINT UNSIGNED NOT NULL,

    account_id BIGINT UNSIGNED NOT NULL,

    category_id BIGINT UNSIGNED NOT NULL,

    type ENUM('income', 'expense') NOT NULL,

    amount DECIMAL(15,2) NOT NULL,

    description VARCHAR(255) NULL,

    transaction_date DATE NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT fk_transactions_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_transactions_account
        FOREIGN KEY (account_id)
        REFERENCES accounts(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_transactions_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_transactions_amount
        CHECK (amount > 0),

    INDEX idx_transactions_user (user_id),

    INDEX idx_transactions_account (account_id),

    INDEX idx_transactions_category (category_id),

    INDEX idx_transactions_user_date
        (user_id, transaction_date),

    INDEX idx_transactions_user_type_date
        (user_id, type, transaction_date)
) ENGINE=InnoDB;


-- ============================================================
-- 5. AUDIT LOGS
-- ============================================================

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    user_id BIGINT UNSIGNED NULL,

    action VARCHAR(100) NOT NULL,

    entity_type VARCHAR(100) NULL,

    entity_id BIGINT UNSIGNED NULL,

    ip_address VARCHAR(45) NULL,

    user_agent VARCHAR(500) NULL,

    details JSON NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_audit_logs_user (user_id),

    INDEX idx_audit_logs_action (action),

    INDEX idx_audit_logs_entity
        (entity_type, entity_id),

    INDEX idx_audit_logs_created
        (created_at)
) ENGINE=InnoDB;

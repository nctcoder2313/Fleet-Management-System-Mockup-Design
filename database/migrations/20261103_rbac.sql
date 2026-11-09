CREATE DATABASE IF NOT EXISTS fleet_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE fleet_management;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('head_admin', 'admin', 'manager', 'accounting') NOT NULL,
    profile_photo VARCHAR(64) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requester_id BIGINT UNSIGNED NOT NULL,
    base_role ENUM('head_admin', 'admin', 'manager', 'accounting') NOT NULL,
    `current_role` ENUM('head_admin', 'admin', 'manager', 'accounting') NOT NULL,
    requested_role ENUM('admin', 'manager', 'accounting') NOT NULL,
    status ENUM('PENDING', 'GRANTED', 'DENIED', 'EXPIRED') NOT NULL DEFAULT 'PENDING',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    review_note VARCHAR(500) NULL,
    active_grant_user_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_role_request_active_grant (active_grant_user_id),
    KEY idx_role_requests_pending (status, requested_at),
    KEY idx_role_requests_requester (requester_id, status, expires_at),
    CONSTRAINT fk_role_requests_requester
        FOREIGN KEY (requester_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_role_requests_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(80) NOT NULL,
    details JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_created_at (created_at),
    KEY idx_audit_logs_event_type (event_type, created_at),
    CONSTRAINT fk_audit_logs_actor
        FOREIGN KEY (actor_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

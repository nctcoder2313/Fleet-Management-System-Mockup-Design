CREATE DATABASE IF NOT EXISTS fleetwise
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE fleetwise;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('accounting', 'manager', 'admin', 'head_admin') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email)
);

INSERT IGNORE INTO users (name, email, password_hash, role)
VALUES (
    'Accounting Demo',
    'ainiemac@gmail.com',
    '$2y$10$GJ0J7cFr/3gznyGS301gN.tNdGfKCMNFxdkHJuTKSlT3MKqcCUwoG',
    'accounting'
);

CREATE TABLE IF NOT EXISTS access_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    requested_role ENUM('accounting', 'manager', 'admin', 'head_admin') NOT NULL,
    reason VARCHAR(1000) NULL,
    password_hash VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY access_requests_email_status (email, status),
    KEY access_requests_status_requested_at (status, requested_at),
    CONSTRAINT access_requests_reviewed_by_fk
        FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON DELETE SET NULL
);

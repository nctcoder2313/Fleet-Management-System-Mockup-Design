USE fleet_management;

CREATE TABLE IF NOT EXISTS account_signup_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL,
    requested_role ENUM('admin', 'manager', 'accounting') NOT NULL,
    reason VARCHAR(1000) NULL,
    password_hash VARCHAR(255) NULL,
    status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    active_email VARCHAR(254)
        GENERATED ALWAYS AS (CASE WHEN status = 'PENDING' THEN email ELSE NULL END) STORED,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_account_signup_pending_email (active_email),
    KEY idx_account_signup_status_requested (status, requested_at),
    CONSTRAINT fk_account_signup_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

ALTER TABLE account_signup_requests
    MODIFY password_hash VARCHAR(255) NULL;

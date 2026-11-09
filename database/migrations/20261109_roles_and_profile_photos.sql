USE fleet_management;

ALTER TABLE users
    MODIFY role ENUM('head_admin', 'admin', 'manager', 'accounting') NOT NULL,
    ADD COLUMN profile_photo VARCHAR(64) NULL AFTER email;

ALTER TABLE role_requests
    MODIFY base_role ENUM('head_admin', 'admin', 'manager', 'accounting') NOT NULL,
    MODIFY `current_role` ENUM('head_admin', 'admin', 'manager', 'accounting') NOT NULL,
    MODIFY requested_role ENUM('admin', 'manager', 'accounting') NOT NULL;

ALTER TABLE account_signup_requests
    MODIFY requested_role ENUM('admin', 'manager', 'accounting') NOT NULL;

USE fleet_management;

ALTER TABLE users
    MODIFY role ENUM('head_admin', 'admin', 'manager', 'user', 'accounting', 'driver') NOT NULL;

ALTER TABLE role_requests
    MODIFY base_role ENUM('head_admin', 'admin', 'manager', 'user', 'accounting', 'driver') NOT NULL,
    MODIFY `current_role` ENUM('head_admin', 'admin', 'manager', 'user', 'accounting', 'driver') NOT NULL,
    MODIFY requested_role ENUM('admin', 'manager', 'user', 'accounting', 'driver') NOT NULL;

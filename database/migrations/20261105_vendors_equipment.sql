USE fleet_management;

CREATE TABLE IF NOT EXISTS accredited_vendors (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    shop_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(150) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    address VARCHAR(500) NOT NULL,
    services_offered TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_accredited_vendors_shop_name (shop_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicle_equipment (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    equipment_type ENUM('GPS Tracker', 'Dashcam', 'RFID Tag', 'Fire Extinguisher') NOT NULL,
    serial_number VARCHAR(100) NOT NULL,
    installation_date DATE NOT NULL,
    status ENUM('Installed', 'Needs Service', 'Removed') NOT NULL DEFAULT 'Installed',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vehicle_equipment_serial_number (serial_number),
    KEY idx_vehicle_equipment_vehicle (vehicle_id),
    KEY idx_vehicle_equipment_status (status),
    CONSTRAINT fk_vehicle_equipment_vehicle
        FOREIGN KEY (vehicle_id) REFERENCES fleet_vehicles (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

USE fleet_management;

CREATE TABLE IF NOT EXISTS maintenance_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    plate_number VARCHAR(20) NOT NULL,
    service_type ENUM('Change Oil', 'Tire Replacement', 'Engine Repair', 'PMS') NOT NULL,
    scheduled_date DATE NOT NULL,
    completion_date DATE NULL DEFAULT NULL,
    mechanic_or_shop VARCHAR(200) NOT NULL,
    parts_replaced TEXT NULL,
    service_status ENUM('Scheduled', 'In-Progress', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Scheduled',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_maintenance_logs_status_date (service_status, scheduled_date),
    KEY idx_maintenance_logs_service_type (service_type),
    KEY idx_maintenance_logs_vehicle (vehicle_id),
    CONSTRAINT fk_maintenance_logs_vehicle
        FOREIGN KEY (vehicle_id) REFERENCES fleet_vehicles (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

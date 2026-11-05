USE fleet_management;

CREATE TABLE IF NOT EXISTS vehicle_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    plate_number VARCHAR(20) NOT NULL,
    document_type ENUM('LTO', 'LTFRB', 'Insurance') NOT NULL,
    document_no VARCHAR(100) NOT NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('Valid', 'Expiring Soon', 'Expired') NOT NULL DEFAULT 'Valid',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_vehicle_documents_type_expiry (document_type, expiry_date),
    KEY idx_vehicle_documents_vehicle (vehicle_id),
    CONSTRAINT fk_vehicle_documents_vehicle
        FOREIGN KEY (vehicle_id) REFERENCES fleet_vehicles (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

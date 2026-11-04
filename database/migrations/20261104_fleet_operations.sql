USE fleet_management;

CREATE TABLE IF NOT EXISTS fleet_vehicles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    plate_number VARCHAR(20) NOT NULL,
    location VARCHAR(200) NOT NULL,
    condition_note VARCHAR(500) NOT NULL DEFAULT '',
    fuel_level_percent TINYINT UNSIGNED NULL,
    status ENUM('on_road', 'idle', 'maintenance') NOT NULL DEFAULT 'idle',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fleet_vehicles_plate_number (plate_number),
    KEY idx_fleet_vehicles_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fleet_trips (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference VARCHAR(40) NOT NULL,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    driver_id BIGINT UNSIGNED NULL,
    origin VARCHAR(200) NOT NULL,
    destination VARCHAR(200) NOT NULL,
    status ENUM('in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'in_progress',
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    override_reason VARCHAR(500) NULL DEFAULT NULL,
    override_action ENUM('force_complete', 'cancel_trip') NULL DEFAULT NULL,
    override_by_user_id BIGINT UNSIGNED NULL,
    active_vehicle_id BIGINT UNSIGNED
        GENERATED ALWAYS AS (CASE WHEN status = 'in_progress' THEN vehicle_id ELSE NULL END) STORED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fleet_trips_reference (reference),
    UNIQUE KEY uq_fleet_trips_active_vehicle (active_vehicle_id),
    KEY idx_fleet_trips_status_started (status, started_at),
    KEY idx_fleet_trips_vehicle_started (vehicle_id, started_at),
    CONSTRAINT fk_fleet_trips_vehicle
        FOREIGN KEY (vehicle_id) REFERENCES fleet_vehicles (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_fleet_trips_driver
        FOREIGN KEY (driver_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_fleet_trips_override_actor
        FOREIGN KEY (override_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

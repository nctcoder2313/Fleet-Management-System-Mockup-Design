USE fleet_management;

CREATE TABLE IF NOT EXISTS geofence_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    zone_name VARCHAR(150) NOT NULL,
    allowed_area_boundary TEXT NOT NULL,
    max_speed_limit_kmh SMALLINT UNSIGNED NOT NULL,
    alert_email_notification TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('Active', 'Disabled') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_geofence_rules_status (status),
    KEY idx_geofence_rules_zone_name (zone_name)
) ENGINE=InnoDB;

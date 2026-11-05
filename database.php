<?php
declare(strict_types=1);

$databasePath = __DIR__ . '/fleet.sqlite';

try {
    $db = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA foreign_keys = ON');

    // Keep existing local mock databases compatible with the expanded manager view.
    $existingTables = $db->query(
        "SELECT name FROM sqlite_master WHERE type = 'table'"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('vehicles', $existingTables, true)) {
        $vehicleInfo = $db->query('PRAGMA table_info(vehicles)')->fetchAll(PDO::FETCH_ASSOC);
        $vehicleColumns = array_column($vehicleInfo, 'name');
        if (!in_array('last_service', $vehicleColumns, true)) {
            $db->exec("ALTER TABLE vehicles ADD COLUMN last_service TEXT NOT NULL DEFAULT '2026-10-01'");
        }
        if (!in_array('fuel_level', $vehicleColumns, true)) {
            $db->exec(
                'ALTER TABLE vehicles ADD COLUMN fuel_level INTEGER DEFAULT NULL
                 CHECK (fuel_level IS NULL OR fuel_level BETWEEN 0 AND 100)'
            );
        }
        $vehicleInfo = $db->query('PRAGMA table_info(vehicles)')->fetchAll(PDO::FETCH_ASSOC);
        $vehicleColumns = array_column($vehicleInfo, 'name');

        $fuelLevelInfo = array_values(array_filter(
            $vehicleInfo,
            static fn (array $column): bool => $column['name'] === 'fuel_level'
        ));
        $fuelLevelIsNotNull = $fuelLevelInfo !== [] && (int) $fuelLevelInfo[0]['notnull'] === 1;
        if ($fuelLevelIsNotNull) {
            $db->exec('PRAGMA foreign_keys = OFF');
            try {
                $db->beginTransaction();
                $db->exec(
                    "CREATE TABLE vehicles_new_fuel_nullable (
                        vehicle_code TEXT PRIMARY KEY,
                        make TEXT NOT NULL,
                        vehicle_type TEXT NOT NULL,
                        status TEXT NOT NULL CHECK (status IN ('active', 'idle', 'maintenance', 'offline')),
                        driver_name TEXT,
                        driver_initials TEXT,
                        driver_color TEXT NOT NULL DEFAULT 'teal' CHECK (driver_color IN ('orange', 'purple', 'teal')),
                        location TEXT NOT NULL,
                        assignment TEXT NOT NULL DEFAULT '',
                        last_service TEXT NOT NULL DEFAULT '2026-10-01',
                        fuel_level INTEGER DEFAULT NULL CHECK (fuel_level IS NULL OR fuel_level BETWEEN 0 AND 100),
                        fuel_level_updated_at TEXT
                    )"
                );
                $updatedAtExpression = in_array('fuel_level_updated_at', $vehicleColumns, true)
                    ? 'fuel_level_updated_at'
                    : 'NULL';
                $db->exec(
                    'INSERT INTO vehicles_new_fuel_nullable
                        (vehicle_code, make, vehicle_type, status, driver_name, driver_initials, driver_color,
                         location, assignment, last_service, fuel_level, fuel_level_updated_at)
                     SELECT vehicle_code, make, vehicle_type, status, driver_name, driver_initials, driver_color,
                            location, assignment, last_service, fuel_level, ' . $updatedAtExpression . '
                     FROM vehicles'
                );
                $db->exec('DROP TABLE vehicles');
                $db->exec('ALTER TABLE vehicles_new_fuel_nullable RENAME TO vehicles');
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $exception;
            } finally {
                $db->exec('PRAGMA foreign_keys = ON');
            }
        } elseif (!in_array('fuel_level_updated_at', $vehicleColumns, true)) {
            $db->exec('ALTER TABLE vehicles ADD COLUMN fuel_level_updated_at TEXT');
        }

        $tripTables = $db->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'trips'"
        )->fetchAll(PDO::FETCH_COLUMN);
        if ($tripTables !== []) {
            $tripColumns = $db->query('PRAGMA table_info(trips)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('trip_date', $tripColumns, true)) {
                $db->beginTransaction();
                try {
                    $db->exec('ALTER TABLE trips ADD COLUMN trip_date TEXT');
                    $db->exec("UPDATE trips SET trip_date = date('now') WHERE status = 'active'");
                    $db->commit();
                } catch (Throwable $exception) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $exception;
                }
            }
        }
    }
    if (in_array('maintenance', $existingTables, true)) {
        $maintenanceColumns = $db->query('PRAGMA table_info(maintenance)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('department', $maintenanceColumns, true)) {
            $db->exec("ALTER TABLE maintenance ADD COLUMN department TEXT NOT NULL DEFAULT 'Operations'");
        }
    }

    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Unable to read schema.sql.');
    }
    $db->exec($schema);
} catch (Throwable $exception) {
    error_log('Fleet dashboard database error: ' . $exception->getMessage());
    http_response_code(500);
    exit('The dashboard could not connect to its SQLite database. Check that PHP has the PDO SQLite extension and the project folder is writable.');
}

return $db;

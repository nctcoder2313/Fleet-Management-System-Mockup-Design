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
        $vehicleColumns = $db->query('PRAGMA table_info(vehicles)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('last_service', $vehicleColumns, true)) {
            $db->exec("ALTER TABLE vehicles ADD COLUMN last_service TEXT NOT NULL DEFAULT '2026-10-01'");
        }
        if (!in_array('fuel_level', $vehicleColumns, true)) {
            $db->exec('ALTER TABLE vehicles ADD COLUMN fuel_level INTEGER NOT NULL DEFAULT 75');
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

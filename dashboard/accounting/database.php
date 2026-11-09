<?php
declare(strict_types=1);

if (!defined('ACCOUNTING_DATABASE_ALLOWED')) {
    http_response_code(404);
    exit;
}

$databasePath = __DIR__ . '/fleet.sqlite';

try {
    $db = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA foreign_keys = ON');

    // Keep existing local databases compatible with the accounting workspace.
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

    if (in_array('expenses', $existingTables, true)) {
        $expenseDefinition = $db->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'expenses'")->fetchColumn();
        if (is_string($expenseDefinition) && !str_contains($expenseDefinition, "'Repairs'")) {
            $db->beginTransaction();
            $db->exec("CREATE TABLE expenses_rebuild (
                id INTEGER PRIMARY KEY,
                expense_date TEXT NOT NULL,
                vehicle_code TEXT NOT NULL REFERENCES vehicles(vehicle_code),
                category TEXT NOT NULL CHECK (category IN ('Fuel', 'Maintenance', 'Repairs', 'Parts', 'Registration', 'Inspection', 'Insurance', 'Tolls', 'Parking', 'Cleaning', 'Permits', 'Fines', 'Other')),
                description TEXT NOT NULL,
                amount_cents INTEGER NOT NULL CHECK (amount_cents > 0),
                status TEXT NOT NULL DEFAULT 'Paid' CHECK (status IN ('Paid', 'Pending'))
            )");
            $db->exec('INSERT INTO expenses_rebuild (id, expense_date, vehicle_code, category, description, amount_cents, status) SELECT id, expense_date, vehicle_code, category, description, amount_cents, status FROM expenses');
            $db->exec('DROP TABLE expenses');
            $db->exec('ALTER TABLE expenses_rebuild RENAME TO expenses');
            $db->commit();
        }
        $expenseColumns = $db->query('PRAGMA table_info(expenses)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('driver_name', $expenseColumns, true)) {
            $db->exec('ALTER TABLE expenses ADD COLUMN driver_name TEXT');
        }
        if (!in_array('recorded_by', $expenseColumns, true)) {
            $db->exec("ALTER TABLE expenses ADD COLUMN recorded_by TEXT NOT NULL DEFAULT 'Accounting team'");
        }
        if (!in_array('source_bill_id', $expenseColumns, true)) {
            $db->exec('ALTER TABLE expenses ADD COLUMN source_bill_id INTEGER');
        }
        $db->exec(
            'UPDATE expenses SET driver_name = (
                SELECT vehicles.driver_name FROM vehicles WHERE vehicles.vehicle_code = expenses.vehicle_code
             ) WHERE driver_name IS NULL'
        );
    }
    if (in_array('cash_advances', $existingTables, true)) {
        $advanceColumns = $db->query('PRAGMA table_info(cash_advances)')->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach ([
            'purpose' => "TEXT NOT NULL DEFAULT 'Trip cash advance'",
            'notes' => "TEXT NOT NULL DEFAULT ''",
            'liquidated_at' => 'TEXT',
            'receipt_reference' => "TEXT NOT NULL DEFAULT ''",
            'liquidation_notes' => "TEXT NOT NULL DEFAULT ''",
        ] as $column => $definition) {
            if (!in_array($column, $advanceColumns, true)) {
                $db->exec("ALTER TABLE cash_advances ADD COLUMN $column $definition");
            }
        }
    }

    $hadLiquidationHistory = in_array('advance_liquidations', $existingTables, true);
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Unable to read schema.sql.');
    }
    $db->exec($schema);
    $liquidationColumns = $db->query('PRAGMA table_info(advance_liquidations)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('liquidated_by', $liquidationColumns, true)) {
        $db->exec("ALTER TABLE advance_liquidations ADD COLUMN liquidated_by TEXT NOT NULL DEFAULT 'Not recorded'");
    }
    if (!in_array('status', $liquidationColumns, true)) {
        $db->exec("ALTER TABLE advance_liquidations ADD COLUMN status TEXT NOT NULL DEFAULT 'Approved'");
    }
    foreach ([
        'receipt_name' => 'TEXT',
        'receipt_mime' => 'TEXT',
        'receipt_data' => 'BLOB',
    ] as $column => $definition) {
        if (!in_array($column, $liquidationColumns, true)) {
            $db->exec("ALTER TABLE advance_liquidations ADD COLUMN $column $definition");
        }
    }
    if (!$hadLiquidationHistory) {
        $db->exec(
            'INSERT INTO advance_liquidations (advance_id, liquidated_at, amount_cents, receipt_reference, notes)
             SELECT id, COALESCE(liquidated_at, advance_date), liquidated_cents, receipt_reference, liquidation_notes
             FROM cash_advances WHERE liquidated_cents > 0'
        );
    }
} catch (Throwable $exception) {
    error_log('Fleet dashboard database error: ' . $exception->getMessage());
    http_response_code(500);
    exit('The dashboard could not connect to its SQLite database. Check that PHP has the PDO SQLite extension and the project folder is writable.');
}

return $db;

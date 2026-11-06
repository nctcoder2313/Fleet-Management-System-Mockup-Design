<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

function validDate(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
        return false;
    }

    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Only POST requests are supported.']);
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    respond(400, ['error' => 'A valid JSON request body is required.']);
}

if (!in_array($payload['action'] ?? '', ['record-expense', 'record-advance', 'liquidate-advance'], true)) {
    respond(404, ['error' => 'That action is not available in the finance workspace.']);
}

try {
    $db = require __DIR__ . '/database.php';

    if (($payload['action'] ?? '') === 'record-expense') {
        $expenseDate = (string) ($payload['expense_date'] ?? '');
        $vehicleCode = trim((string) ($payload['vehicle_code'] ?? ''));
        $category = (string) ($payload['category'] ?? '');
        $description = trim((string) ($payload['description'] ?? ''));
        $amount = filter_var($payload['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $status = (string) ($payload['status'] ?? 'Paid');
        $categories = ['Fuel', 'Maintenance', 'Repairs', 'Parts', 'Registration', 'Inspection', 'Insurance', 'Tolls', 'Parking', 'Cleaning', 'Permits', 'Fines', 'Other'];
        if (!validDate($expenseDate) || $vehicleCode === '' || !in_array($category, $categories, true)
            || $description === '' || strlen($description) > 160 || $amount === false || $amount <= 0
            || $amount > 10000000 || !in_array($status, ['Paid', 'Pending'], true)) {
            respond(422, ['error' => 'Check the expense fields and provide valid values.']);
        }
        $amountCents = (int) round($amount * 100);
        if ($amountCents < 1) {
            respond(422, ['error' => 'Expense amount must be at least one cent.']);
        }
        $vehicleExists = $db->prepare('SELECT driver_name FROM vehicles WHERE vehicle_code = :vehicle_code');
        $vehicleExists->execute(['vehicle_code' => $vehicleCode]);
        $vehicle = $vehicleExists->fetch(PDO::FETCH_ASSOC);
        if (!$vehicle) {
            respond(422, ['error' => 'Choose an existing vehicle.']);
        }
        $statement = $db->prepare(
            'INSERT INTO expenses (expense_date, vehicle_code, driver_name, category, description, amount_cents, status)
             VALUES (:expense_date, :vehicle_code, :driver_name, :category, :description, :amount_cents, :status)'
        );
        $statement->execute([
            'expense_date' => $expenseDate,
            'vehicle_code' => $vehicleCode,
            'driver_name' => $vehicle['driver_name'],
            'category' => $category,
            'description' => $description,
            'amount_cents' => $amountCents,
            'status' => $status,
        ]);
        respond(201, ['message' => 'Expense recorded.']);
    }

    if (($payload['action'] ?? '') === 'record-advance') {
        $advanceDate = (string) ($payload['advance_date'] ?? '');
        $driverName = trim((string) ($payload['driver_name'] ?? ''));
        $vehicleCode = trim((string) ($payload['vehicle_code'] ?? ''));
        $purpose = trim((string) ($payload['purpose'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        $amount = filter_var($payload['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        if (!validDate($advanceDate) || $driverName === '' || $vehicleCode === '' || $purpose === ''
            || strlen($purpose) > 120 || strlen($notes) > 500 || $amount === false || $amount <= 0 || $amount > 10000000) {
            respond(422, ['error' => 'Check the advance fields and provide valid values.']);
        }
        $amountCents = (int) round($amount * 100);
        if ($amountCents < 1) {
            respond(422, ['error' => 'Advance amount must be at least one cent.']);
        }
        $driverExists = $db->prepare('SELECT 1 FROM drivers WHERE name = :driver_name');
        $driverExists->execute(['driver_name' => $driverName]);
        $vehicleExists = $db->prepare('SELECT 1 FROM vehicles WHERE vehicle_code = :vehicle_code');
        $vehicleExists->execute(['vehicle_code' => $vehicleCode]);
        if (!$driverExists->fetchColumn() || !$vehicleExists->fetchColumn()) {
            respond(422, ['error' => 'Choose an existing driver and vehicle.']);
        }
        $statement = $db->prepare(
            'INSERT INTO cash_advances (advance_date, driver_name, vehicle_code, purpose, notes, amount_cents)
             VALUES (:advance_date, :driver_name, :vehicle_code, :purpose, :notes, :amount_cents)'
        );
        $statement->execute([
            'advance_date' => $advanceDate,
            'driver_name' => $driverName,
            'vehicle_code' => $vehicleCode,
            'purpose' => $purpose,
            'notes' => $notes,
            'amount_cents' => $amountCents,
        ]);
        respond(201, ['message' => 'Driver advance recorded.']);
    }

    if (($payload['action'] ?? '') === 'liquidate-advance') {
        $advanceId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
        $liquidatedAt = (string) ($payload['liquidated_at'] ?? '');
        $liquidatedBy = trim((string) ($payload['liquidated_by'] ?? ''));
        $amount = filter_var($payload['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $receiptReference = trim((string) ($payload['receipt_reference'] ?? ''));
        $liquidationNotes = trim((string) ($payload['liquidation_notes'] ?? ''));
        if (!$advanceId || !validDate($liquidatedAt) || $liquidatedBy === '' || strlen($liquidatedBy) > 120
            || $amount === false || $amount <= 0
            || strlen($receiptReference) > 80 || strlen($liquidationNotes) > 500) {
            respond(422, ['error' => 'Check the liquidation fields and provide valid values.']);
        }
        $amountCents = (int) round($amount * 100);
        $db->beginTransaction();
        $advanceStatement = $db->prepare('SELECT amount_cents, liquidated_cents FROM cash_advances WHERE id = :id');
        $advanceStatement->execute(['id' => $advanceId]);
        $advance = $advanceStatement->fetch(PDO::FETCH_ASSOC);
        if (!$advance) {
            $db->rollBack();
            respond(404, ['error' => 'That advance was not found.']);
        }
        $remainingCents = (int) $advance['amount_cents'] - (int) $advance['liquidated_cents'];
        if ($amountCents < 1 || $amountCents > $remainingCents) {
            $db->rollBack();
            respond(422, ['error' => 'Liquidation cannot exceed the outstanding balance.']);
        }
        $statement = $db->prepare(
            'UPDATE cash_advances SET liquidated_cents = liquidated_cents + :amount_cents,
                liquidated_at = :liquidated_at, receipt_reference = :receipt_reference,
                liquidation_notes = :liquidation_notes WHERE id = :id'
        );
        $statement->execute([
            'amount_cents' => $amountCents,
            'liquidated_at' => $liquidatedAt,
            'receipt_reference' => $receiptReference,
            'liquidation_notes' => $liquidationNotes,
            'id' => $advanceId,
        ]);
        $liquidationStatement = $db->prepare(
            'INSERT INTO advance_liquidations (advance_id, liquidated_at, liquidated_by, amount_cents, receipt_reference, notes)
             VALUES (:advance_id, :liquidated_at, :liquidated_by, :amount_cents, :receipt_reference, :notes)'
        );
        $liquidationStatement->execute([
            'advance_id' => $advanceId,
            'liquidated_at' => $liquidatedAt,
            'liquidated_by' => $liquidatedBy,
            'amount_cents' => $amountCents,
            'receipt_reference' => $receiptReference,
            'notes' => $liquidationNotes,
        ]);
        $db->commit();
        respond(200, ['message' => 'Driver advance liquidation saved.']);
    }

    if (($payload['action'] ?? '') === 'report-issue') {
        $vehicleOrTrip = trim((string) ($payload['vehicle'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        if ($vehicleOrTrip === '' || $description === '' || strlen($vehicleOrTrip) > 80 || strlen($description) > 1000) {
            respond(422, ['error' => 'Provide a vehicle or trip and a description (up to 1,000 characters).']);
        }

        $statement = $db->prepare('INSERT INTO issue_reports (vehicle_or_trip, description) VALUES (:vehicle, :description)');
        $statement->execute(['vehicle' => $vehicleOrTrip, 'description' => $description]);
        respond(201, ['message' => 'Issue report submitted.']);
    }

    if (($payload['action'] ?? '') === 'request-maintenance') {
        $maintenanceId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$maintenanceId) {
            respond(422, ['error' => 'A valid maintenance item is required.']);
        }

        $statement = $db->prepare(
            "UPDATE maintenance SET state = 'requested' WHERE id = :id AND state IN ('overdue', 'due soon')"
        );
        $statement->execute(['id' => $maintenanceId]);
        if ($statement->rowCount() !== 1) {
            respond(404, ['error' => 'That maintenance item was not found or was already requested.']);
        }
        respond(200, ['message' => 'Maintenance request submitted.']);
    }

    if (($payload['action'] ?? '') === 'update-vehicle') {
        $vehicleCode = trim((string) ($payload['vehicle_code'] ?? ''));
        $make = trim((string) ($payload['make'] ?? ''));
        $vehicleType = trim((string) ($payload['vehicle_type'] ?? ''));
        $status = (string) ($payload['status'] ?? '');
        $driverName = trim((string) ($payload['driver_name'] ?? ''));
        $location = trim((string) ($payload['location'] ?? ''));
        $assignment = trim((string) ($payload['assignment'] ?? ''));
        $lastService = (string) ($payload['last_service'] ?? '');
        $fuelLevel = filter_var($payload['fuel_level'] ?? null, FILTER_VALIDATE_INT);

        if ($vehicleCode === '' || $make === '' || strlen($make) > 80 || $vehicleType === '' || strlen($vehicleType) > 60
            || !in_array($status, ['active', 'idle', 'maintenance', 'offline'], true)
            || strlen($driverName) > 100 || $location === '' || strlen($location) > 120 || strlen($assignment) > 120
            || !validDate($lastService) || $fuelLevel === false || $fuelLevel < 0 || $fuelLevel > 100) {
            respond(422, ['error' => 'Check the vehicle fields and provide valid values.']);
        }

        $statement = $db->prepare(
            'UPDATE vehicles SET make = :make, vehicle_type = :vehicle_type, status = :status,
                driver_name = :driver_name, location = :location, assignment = :assignment,
                last_service = :last_service, fuel_level = :fuel_level
             WHERE vehicle_code = :vehicle_code'
        );
        $statement->execute([
            'make' => $make,
            'vehicle_type' => $vehicleType,
            'status' => $status,
            'driver_name' => $driverName !== '' ? $driverName : null,
            'location' => $location,
            'assignment' => $assignment,
            'last_service' => $lastService,
            'fuel_level' => $fuelLevel,
            'vehicle_code' => $vehicleCode,
        ]);
        if ($statement->rowCount() === 0) {
            $exists = $db->prepare('SELECT 1 FROM vehicles WHERE vehicle_code = :vehicle_code');
            $exists->execute(['vehicle_code' => $vehicleCode]);
            if (!$exists->fetchColumn()) {
                respond(404, ['error' => 'That vehicle was not found.']);
            }
        }
        respond(200, ['message' => 'Vehicle updated.']);
    }

    if (($payload['action'] ?? '') === 'update-trip') {
        $tripId = trim((string) ($payload['id'] ?? ''));
        $vehicleCode = trim((string) ($payload['vehicle_code'] ?? ''));
        $driverName = trim((string) ($payload['driver_name'] ?? ''));
        $origin = trim((string) ($payload['origin'] ?? ''));
        $destination = trim((string) ($payload['destination'] ?? ''));
        $status = (string) ($payload['status'] ?? '');
        $delayMinutes = filter_var($payload['delay_minutes'] ?? null, FILTER_VALIDATE_INT);
        $eta = trim((string) ($payload['eta'] ?? ''));

        if ($tripId === '' || $vehicleCode === '' || $driverName === '' || strlen($driverName) > 100
            || $origin === '' || strlen($origin) > 120 || $destination === '' || strlen($destination) > 120
            || !in_array($status, ['active', 'upcoming', 'completed'], true)
            || $delayMinutes === false || $delayMinutes < 0 || $eta === '' || strlen($eta) > 30) {
            respond(422, ['error' => 'Check the trip fields and provide valid values.']);
        }
        $vehicleExists = $db->prepare('SELECT 1 FROM vehicles WHERE vehicle_code = :vehicle_code');
        $vehicleExists->execute(['vehicle_code' => $vehicleCode]);
        $driverExists = $db->prepare('SELECT 1 FROM drivers WHERE name = :driver_name');
        $driverExists->execute(['driver_name' => $driverName]);
        if (!$vehicleExists->fetchColumn() || !$driverExists->fetchColumn()) {
            respond(422, ['error' => 'Choose an existing vehicle and driver for this trip.']);
        }

        $statement = $db->prepare(
            'UPDATE trips SET vehicle_code = :vehicle_code, driver_name = :driver_name,
                origin = :origin, destination = :destination, status = :status,
                delay_minutes = :delay_minutes, eta = :eta WHERE id = :id'
        );
        $statement->execute([
            'vehicle_code' => $vehicleCode,
            'driver_name' => $driverName,
            'origin' => $origin,
            'destination' => $destination,
            'status' => $status,
            'delay_minutes' => $delayMinutes,
            'eta' => $eta,
            'id' => $tripId,
        ]);
        if ($statement->rowCount() === 0) {
            $exists = $db->prepare('SELECT 1 FROM trips WHERE id = :id');
            $exists->execute(['id' => $tripId]);
            if (!$exists->fetchColumn()) {
                respond(404, ['error' => 'That trip was not found.']);
            }
        }
        respond(200, ['message' => 'Trip updated.']);
    }

    if (($payload['action'] ?? '') === 'update-driver') {
        $driverId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
        $onDuty = (string) ($payload['on_duty'] ?? '');
        if (!$driverId || !in_array($onDuty, ['0', '1'], true)) {
            respond(422, ['error' => 'Choose a valid driver duty status.']);
        }
        $statement = $db->prepare('UPDATE drivers SET on_duty = :on_duty WHERE id = :id');
        $statement->execute(['on_duty' => (int) $onDuty, 'id' => $driverId]);
        if ($statement->rowCount() === 0) {
            $exists = $db->prepare('SELECT 1 FROM drivers WHERE id = :id');
            $exists->execute(['id' => $driverId]);
            if (!$exists->fetchColumn()) {
                respond(404, ['error' => 'That driver was not found.']);
            }
        }
        respond(200, ['message' => 'Driver updated.']);
    }

    if (($payload['action'] ?? '') === 'update-maintenance') {
        $maintenanceId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
        $vehicleCode = trim((string) ($payload['vehicle_code'] ?? ''));
        $task = trim((string) ($payload['task'] ?? ''));
        $department = trim((string) ($payload['department'] ?? ''));
        $depot = trim((string) ($payload['depot'] ?? ''));
        $dueDate = (string) ($payload['due_date'] ?? '');
        $state = (string) ($payload['state'] ?? '');
        if (!$maintenanceId || $vehicleCode === '' || $task === '' || strlen($task) > 120
            || $department === '' || strlen($department) > 80 || $depot === '' || strlen($depot) > 80
            || !validDate($dueDate) || !in_array($state, ['overdue', 'due soon', 'requested', 'complete'], true)) {
            respond(422, ['error' => 'Check the maintenance fields and provide valid values.']);
        }
        $vehicleExists = $db->prepare('SELECT 1 FROM vehicles WHERE vehicle_code = :vehicle_code');
        $vehicleExists->execute(['vehicle_code' => $vehicleCode]);
        if (!$vehicleExists->fetchColumn()) {
            respond(422, ['error' => 'Choose an existing vehicle for this maintenance item.']);
        }
        $statement = $db->prepare(
            'UPDATE maintenance SET vehicle_code = :vehicle_code, task = :task,
                department = :department, depot = :depot, due_date = :due_date, state = :state
             WHERE id = :id'
        );
        $statement->execute([
            'vehicle_code' => $vehicleCode,
            'task' => $task,
            'department' => $department,
            'depot' => $depot,
            'due_date' => $dueDate,
            'state' => $state,
            'id' => $maintenanceId,
        ]);
        if ($statement->rowCount() === 0) {
            $exists = $db->prepare('SELECT 1 FROM maintenance WHERE id = :id');
            $exists->execute(['id' => $maintenanceId]);
            if (!$exists->fetchColumn()) {
                respond(404, ['error' => 'That maintenance item was not found.']);
            }
        }
        respond(200, ['message' => 'Maintenance record updated.']);
    }

    respond(400, ['error' => 'Unknown action.']);
} catch (Throwable $exception) {
    error_log('Fleet dashboard API error: ' . $exception->getMessage());
    respond(500, ['error' => 'The request could not be saved. Check the PHP error log for details.']);
}

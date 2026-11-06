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

try {
    $db = require __DIR__ . '/database.php';

    if (($payload['action'] ?? '') === 'report-issue') {
        $vehicleOrTrip = trim((string) ($payload['vehicle'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $issueType = trim((string) ($payload['issue_type'] ?? ''));
        $priority = (string) ($payload['priority'] ?? '');
        $validIssueTypes = ['', 'Maintenance', 'Mechanical', 'Tires', 'Electrical', 'Safety', 'Other'];
        $validPriorities = ['', 'low', 'medium', 'high'];
        if ($vehicleOrTrip === '' || $description === '' || strlen($vehicleOrTrip) > 80 || strlen($description) > 1000) {
            respond(422, ['error' => 'Provide a vehicle or trip and a description (up to 1,000 characters).']);
        }
        if (!in_array($issueType, $validIssueTypes, true) || !in_array($priority, $validPriorities, true)) {
            respond(422, ['error' => 'Choose a valid issue type and priority.']);
        }

        $statement = $db->prepare(
            'INSERT INTO issue_reports (vehicle_or_trip, description, issue_type, priority)
             VALUES (:vehicle, :description, :issue_type, :priority)'
        );
        $statement->execute([
            'vehicle' => $vehicleOrTrip,
            'description' => $description,
            'issue_type' => $issueType !== '' ? $issueType : null,
            'priority' => $priority !== '' ? $priority : null,
        ]);
        respond(201, ['message' => 'Issue report submitted and marked Awaiting Review.']);
    }

    if (($payload['action'] ?? '') === 'update-issue') {
        $issueId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($payload['status'] ?? '');
        $dueDate = trim((string) ($payload['due_date'] ?? ''));
        $maintenanceNotes = trim((string) ($payload['maintenance_notes'] ?? ''));
        if (!$issueId || !in_array($status, ['awaiting_review', 'in_progress', 'delayed', 'completed'], true)
            || ($dueDate !== '' && !validDate($dueDate)) || strlen($maintenanceNotes) > 3000) {
            respond(422, ['error' => 'Check the issue status, due date, and maintenance notes.']);
        }

        $currentIssue = $db->prepare('SELECT status FROM issue_reports WHERE id = :id');
        $currentIssue->execute(['id' => $issueId]);
        $currentStatus = $currentIssue->fetchColumn();
        if ($currentStatus === false) {
            respond(404, ['error' => 'That issue report was not found.']);
        }

        $allowedTransitions = [
            'awaiting_review' => ['in_progress'],
            'in_progress' => ['delayed', 'completed'],
            'delayed' => ['in_progress', 'completed'],
            'completed' => [],
        ];
        if ($status !== $currentStatus && !in_array($status, $allowedTransitions[$currentStatus] ?? [], true)) {
            respond(422, ['error' => 'That status change is not allowed in the issue workflow.']);
        }

        // An in-progress issue past its due date is saved as Delayed, not merely styled differently.
        $savedStatus = $status === 'in_progress' && $dueDate !== '' && $dueDate < gmdate('Y-m-d')
            ? 'delayed'
            : $status;
        $statement = $db->prepare(
            'UPDATE issue_reports
             SET status = :status, due_date = :due_date, maintenance_notes = :maintenance_notes
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $savedStatus,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'maintenance_notes' => $maintenanceNotes,
            'id' => $issueId,
        ]);
        respond(200, ['message' => 'Issue updated.']);
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
        $fuelLevelInput = $payload['fuel_level'] ?? null;
        $fuelLevel = null;
        $invalidFuelLevel = false;
        if ($fuelLevelInput !== null && $fuelLevelInput !== '') {
            $fuelLevel = filter_var($fuelLevelInput, FILTER_VALIDATE_INT);
            $invalidFuelLevel = $fuelLevel === false || $fuelLevel < 0 || $fuelLevel > 100;
        }

        if ($vehicleCode === '' || $make === '' || strlen($make) > 80 || $vehicleType === '' || strlen($vehicleType) > 60
            || !in_array($status, ['active', 'idle', 'maintenance', 'offline'], true)
            || strlen($driverName) > 100 || $location === '' || strlen($location) > 120 || strlen($assignment) > 120
            || !validDate($lastService) || $invalidFuelLevel) {
            respond(422, ['error' => 'Check the vehicle fields and provide valid values.']);
        }

        $statement = $db->prepare(
            'UPDATE vehicles SET make = :make, vehicle_type = :vehicle_type, status = :status,
                driver_name = :driver_name, location = :location, assignment = :assignment,
                last_service = :last_service, fuel_level = :fuel_level,
                fuel_level_updated_at = datetime(\'now\')
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
        $tripDate = trim((string) ($payload['trip_date'] ?? ''));

        if ($tripId === '' || $vehicleCode === '' || $driverName === '' || strlen($driverName) > 100
            || $origin === '' || strlen($origin) > 120 || $destination === '' || strlen($destination) > 120
            || !in_array($status, ['active', 'upcoming', 'completed'], true)
            || $delayMinutes === false || $delayMinutes < 0 || $eta === '' || strlen($eta) > 30
            || ($tripDate !== '' && !validDate($tripDate))) {
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
                delay_minutes = :delay_minutes, eta = :eta, trip_date = :trip_date WHERE id = :id'
        );
        $statement->execute([
            'vehicle_code' => $vehicleCode,
            'driver_name' => $driverName,
            'origin' => $origin,
            'destination' => $destination,
            'status' => $status,
            'delay_minutes' => $delayMinutes,
            'eta' => $eta,
            'trip_date' => $tripDate !== '' ? $tripDate : null,
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

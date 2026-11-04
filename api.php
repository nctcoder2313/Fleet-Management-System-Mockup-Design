<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
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

    respond(400, ['error' => 'Unknown action.']);
} catch (Throwable $exception) {
    error_log('Fleet dashboard API error: ' . $exception->getMessage());
    respond(500, ['error' => 'The request could not be saved. Check the PHP error log for details.']);
}

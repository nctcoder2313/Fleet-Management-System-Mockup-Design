<?php
declare(strict_types=1);

$requestedPreview = $_GET['preview'] ?? '';
$previewMode = is_string($requestedPreview) && $requestedPreview === '1';

require_once __DIR__ . '/../../includes/rbac.php';
startSecureSession();

if ($previewMode && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && ($_POST['action'] ?? '') === 'reset_preview'
) {
    requireValidCsrfToken();
    unset($_SESSION['head_admin_preview_data']);
    $_SESSION['flash_message'] = 'Sample preview data restored.';
    header('Location: dashboard.php?preview=1');
    exit;
}

$roleLabels = [
    'head_admin' => 'Head Admin',
    'admin' => 'Admin',
    'manager' => 'Manager',
    'user' => 'User',
    'accounting' => 'Accounting',
    'driver' => 'Driver',
];
$manageableRoles = ['admin', 'manager', 'user', 'accounting', 'driver'];
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

if ($previewMode) {
    if (!isset($_SESSION['head_admin_preview_data'])) {
        $demoUsers = [
            ['id' => 1, 'name' => 'Alex Morgan', 'email' => 'alex.morgan@northstar.example', 'role' => 'head_admin', 'status' => 'active', 'password_hash' => password_hash('DemoFleet2026!', PASSWORD_DEFAULT), 'created_at' => date('Y-m-d H:i:s', strtotime('-180 days'))],
            ['id' => 2, 'name' => 'Jamie Rivera', 'email' => 'jamie.rivera@northstar.example', 'role' => 'admin', 'status' => 'active', 'password_hash' => password_hash('DemoFleet2026!', PASSWORD_DEFAULT), 'created_at' => date('Y-m-d H:i:s', strtotime('-120 days'))],
            ['id' => 3, 'name' => 'Taylor Santos', 'email' => 'taylor.santos@northstar.example', 'role' => 'manager', 'status' => 'active', 'password_hash' => password_hash('DemoFleet2026!', PASSWORD_DEFAULT), 'created_at' => date('Y-m-d H:i:s', strtotime('-90 days'))],
            ['id' => 4, 'name' => 'Casey Reyes', 'email' => 'casey.reyes@northstar.example', 'role' => 'user', 'status' => 'active', 'password_hash' => password_hash('DemoFleet2026!', PASSWORD_DEFAULT), 'created_at' => date('Y-m-d H:i:s', strtotime('-45 days'))],
            ['id' => 5, 'name' => 'Jordan Cruz', 'email' => 'jordan.cruz@northstar.example', 'role' => 'accounting', 'status' => 'active', 'password_hash' => password_hash('DemoFleet2026!', PASSWORD_DEFAULT), 'created_at' => date('Y-m-d H:i:s', strtotime('-30 days'))],
            ['id' => 6, 'name' => 'Avery Brooks', 'email' => 'avery.brooks@northstar.example', 'role' => 'manager', 'status' => 'inactive', 'password_hash' => password_hash('DemoFleet2026!', PASSWORD_DEFAULT), 'created_at' => date('Y-m-d H:i:s', strtotime('-60 days'))],
            ['id' => 7, 'name' => 'Morgan Lee', 'email' => 'morgan.lee@northstar.example', 'role' => 'driver', 'status' => 'active', 'password_hash' => password_hash('DemoFleet2026!', PASSWORD_DEFAULT), 'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours'))],
        ];
        $_SESSION['head_admin_preview_data'] = [
            'users' => $demoUsers,
            'vehicles' => [
                ['id' => 101, 'name' => 'Toyota HiAce', 'plate_number' => 'ABC-2048', 'location' => 'EDSA, Quezon City', 'condition_note' => 'No reported issues', 'fuel_level_percent' => 68, 'status' => 'on_road'],
                ['id' => 102, 'name' => 'Mitsubishi L300', 'plate_number' => 'XYZ-1982', 'location' => 'NLEX, Bocaue', 'condition_note' => 'No reported issues', 'fuel_level_percent' => 18, 'status' => 'on_road'],
                ['id' => 103, 'name' => 'Isuzu N-Series', 'plate_number' => 'DEF-1760', 'location' => 'Northstar Fleet Garage', 'condition_note' => 'Engine warning under inspection', 'fuel_level_percent' => null, 'status' => 'maintenance'],
                ['id' => 104, 'name' => 'Toyota Hilux', 'plate_number' => 'GHI-2251', 'location' => 'Northstar Fleet Garage', 'condition_note' => 'Ready for assignment', 'fuel_level_percent' => 74, 'status' => 'idle'],
                ['id' => 105, 'name' => 'Nissan Navara', 'plate_number' => 'JKL-3091', 'location' => 'Northstar Fleet Garage', 'condition_note' => 'Ready for assignment', 'fuel_level_percent' => 52, 'status' => 'idle'],
                ['id' => 106, 'name' => 'Toyota Innova', 'plate_number' => 'MNO-4187', 'location' => 'Northstar Fleet Garage', 'condition_note' => 'Scheduled brake inspection', 'fuel_level_percent' => 82, 'status' => 'maintenance'],
            ],
            'trips' => [
                ['id' => 9001, 'reference' => 'TR-2026-1104-018', 'vehicle_id' => 101, 'driver_name' => 'Morgan Lee', 'origin' => 'Northstar Quezon City Depot', 'destination' => 'Makati Business District', 'status' => 'in_progress', 'started_at' => date('Y-m-d H:i:s', strtotime('-5 hours')), 'ended_at' => null, 'override_reason' => null, 'override_action' => null, 'overridden_by' => null],
                ['id' => 9002, 'reference' => 'TR-2026-1104-019', 'vehicle_id' => 102, 'driver_name' => 'Miguel Santos', 'origin' => 'Northstar Quezon City Depot', 'destination' => 'Bocaue Distribution Center', 'status' => 'in_progress', 'started_at' => date('Y-m-d H:i:s', strtotime('-40 minutes')), 'ended_at' => null, 'override_reason' => null, 'override_action' => null, 'overridden_by' => null],
            ],
            'pending_requests' => [
                ['id' => 501, 'requester_id' => 4, 'base_role' => 'user', 'current_role' => 'user', 'requested_role' => 'manager', 'requested_at' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'requester_name' => 'Casey Reyes', 'requester_email' => 'casey.reyes@northstar.example', 'status' => 'PENDING'],
                ['id' => 502, 'requester_id' => 5, 'base_role' => 'accounting', 'current_role' => 'accounting', 'requested_role' => 'admin', 'requested_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'requester_name' => 'Jordan Cruz', 'requester_email' => 'jordan.cruz@northstar.example', 'status' => 'PENDING'],
            ],
            'audit_logs' => [
                ['event_type' => 'ROLE_ACCESS_REQUESTED', 'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes')), 'actor_name' => 'Casey Reyes', 'details' => '{"requested_role":"manager","request_id":501}'],
                ['event_type' => 'USER_UPDATED', 'created_at' => date('Y-m-d H:i:s', strtotime('-52 minutes')), 'actor_name' => 'Alex Morgan', 'details' => '{"user_id":3,"change":"Department role confirmed"}'],
                ['event_type' => 'ROLE_ACCESS_GRANTED', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'actor_name' => 'Alex Morgan', 'details' => '{"requested_role":"accounting","expires_in_hours":8}'],
                ['event_type' => 'USER_CREATED', 'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours')), 'actor_name' => 'Jamie Rivera', 'details' => '{"role":"driver","user_id":7,"user_name":"Morgan Lee","user_email":"morgan.lee@northstar.example"}'],
                ['event_type' => 'ROLE_ACCESS_DENIED', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'actor_name' => 'Jamie Rivera', 'details' => '{"requested_role":"admin","request_id":490}'],
                ['event_type' => 'USER_REACTIVATED', 'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')), 'actor_name' => 'Alex Morgan', 'details' => '{"user_id":6}'],
                ['event_type' => 'ROLE_ACCESS_REQUESTED', 'created_at' => date('Y-m-d H:i:s', strtotime('-4 days')), 'actor_name' => 'Jordan Cruz', 'details' => '{"requested_role":"admin","request_id":502}'],
                ['event_type' => 'USER_DEACTIVATED', 'created_at' => date('Y-m-d H:i:s', strtotime('-6 days')), 'actor_name' => 'Alex Morgan', 'details' => '{"user_id":8}'],
                ['event_type' => 'ROLE_ACCESS_GRANTED', 'created_at' => date('Y-m-d H:i:s', strtotime('-10 days')), 'actor_name' => 'Alex Morgan', 'details' => '{"requested_role":"manager","expires_in_hours":8}'],
            ],
        ];
    }

    $headAdmin = ['id' => 0, 'name' => 'Preview Account'];
} else {
    $pdo = require __DIR__ . '/../../config/database.php';
    $headAdmin = requireRole($pdo, 'head_admin');
}

$pendingCount = $previewMode
    ? count(array_filter(
        $_SESSION['head_admin_preview_data']['pending_requests'],
        static fn (array $request): bool => $request['status'] === 'PENDING'
    ))
    : (int) $pdo->query("SELECT COUNT(*) FROM role_requests WHERE status = 'PENDING'")->fetchColumn();
$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

function headAdminUrl(string $page, array $query = []): string
{
    global $previewMode;
    if ($previewMode) {
        $query['preview'] = '1';
    }
    $queryString = http_build_query($query);
    return $page . ($queryString === '' ? '' : '?' . $queryString);
}

function headAdminRedirect(string $url, string $message): never
{
    $_SESSION['flash_message'] = $message;
    header('Location: ' . $url);
    exit;
}

function headAdminRecordAudit(string $eventType, array $details, ?string $actorName = null): void
{
    global $previewMode, $pdo, $headAdmin;
    $encodedDetails = json_encode($details, JSON_THROW_ON_ERROR);

    if ($previewMode) {
        array_unshift($_SESSION['head_admin_preview_data']['audit_logs'], [
            'event_type' => $eventType,
            'created_at' => date('Y-m-d H:i:s'),
            'actor_name' => $actorName ?? $headAdmin['name'],
            'details' => $encodedDetails,
        ]);
        return;
    }

    $statement = $pdo->prepare(
        'INSERT INTO audit_logs (actor_user_id, event_type, details)
         VALUES (:actor_id, :event_type, :details)'
    );
    $statement->execute([
        'actor_id' => $headAdmin['id'],
        'event_type' => $eventType,
        'details' => $encodedDetails,
    ]);
}

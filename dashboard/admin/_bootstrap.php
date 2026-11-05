<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/rbac.php';
startSecureSession();

$requestedPreview = $_GET['preview'] ?? '';
$previewMode = is_string($requestedPreview) && $requestedPreview === '1';
$isLoopbackClient = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);

if ($previewMode && !$isLoopbackClient) {
    http_response_code(403);
    exit('The Admin preview is available only from this machine.');
}

$pdo = null;
if ($previewMode) {
    $admin = ['id' => 0, 'name' => 'Local Admin Preview'];
} else {
    $pdo = require __DIR__ . '/../../config/database.php';
    $admin = requireRole($pdo, 'admin');
}

if ($previewMode && !isset($_SESSION['admin_geofence_preview'])) {
    $_SESSION['admin_geofence_preview'] = [
        ['id' => 1, 'zone_name' => 'Quezon City Depot', 'allowed_area_boundary' => "14.6760, 121.0437\n14.6812, 121.0501\n14.6718, 121.0540\n14.6674, 121.0465", 'max_speed_limit_kmh' => 30, 'alert_email_notification' => 1, 'status' => 'Active', 'created_at' => date('Y-m-d H:i:s')],
        ['id' => 2, 'zone_name' => 'NLEX Distribution Yard', 'allowed_area_boundary' => "14.8392, 120.9271\n14.8448, 120.9322\n14.8380, 120.9390\n14.8331, 120.9304", 'max_speed_limit_kmh' => 20, 'alert_email_notification' => 0, 'status' => 'Disabled', 'created_at' => date('Y-m-d H:i:s')],
    ];
}

if ($previewMode && !isset($_SESSION['admin_maintenance_preview'])) {
    $_SESSION['admin_maintenance_preview'] = [
        [
            'id' => 1,
            'vehicle_id' => 101,
            'vehicle_name' => 'Toyota HiAce',
            'plate_number' => 'ABC-2048',
            'service_type' => 'Change Oil',
            'scheduled_date' => date('Y-m-d', strtotime('+2 days')),
            'completion_date' => null,
            'mechanic_or_shop' => 'Northstar Fleet Garage',
            'parts_replaced' => null,
            'service_status' => 'Scheduled',
            'notes' => 'Routine oil and filter service.',
        ],
        [
            'id' => 2,
            'vehicle_id' => 103,
            'vehicle_name' => 'Isuzu N-Series',
            'plate_number' => 'DEF-1760',
            'service_type' => 'Engine Repair',
            'scheduled_date' => date('Y-m-d', strtotime('-1 day')),
            'completion_date' => null,
            'mechanic_or_shop' => 'Northstar Fleet Garage',
            'parts_replaced' => 'Air filter',
            'service_status' => 'In-Progress',
            'notes' => 'Inspect engine warning and complete diagnostic.',
        ],
    ];
}

if ($previewMode && !isset($_SESSION['admin_preview_vehicles'])) {
    $_SESSION['admin_preview_vehicles'] = [
        ['id' => 101, 'name' => 'Toyota HiAce', 'plate_number' => 'ABC-2048'],
        ['id' => 102, 'name' => 'Mitsubishi L300', 'plate_number' => 'XYZ-1982'],
        ['id' => 103, 'name' => 'Isuzu N-Series', 'plate_number' => 'DEF-1760'],
    ];
}

if ($previewMode && !isset($_SESSION['admin_document_preview'])) {
    $_SESSION['admin_document_preview'] = [
        [
            'id' => 1,
            'vehicle_id' => 101,
            'vehicle_name' => 'Toyota HiAce',
            'plate_number' => 'ABC-2048',
            'document_type' => 'LTO',
            'document_no' => 'LTO-ABC-2048',
            'expiry_date' => date('Y-m-d', strtotime('+18 days')),
            'notes' => 'Renewal appointment to be confirmed.',
        ],
        [
            'id' => 2,
            'vehicle_id' => 103,
            'vehicle_name' => 'Isuzu N-Series',
            'plate_number' => 'DEF-1760',
            'document_type' => 'Insurance',
            'document_no' => 'INS-DEF-1760',
            'expiry_date' => date('Y-m-d', strtotime('-4 days')),
            'notes' => 'Contact provider for updated coverage.',
        ],
    ];
}

if ($previewMode && !isset($_SESSION['admin_vendor_preview'])) {
    $_SESSION['admin_vendor_preview'] = [
        ['id' => 1, 'shop_name' => 'Northstar Fleet Garage', 'contact_person' => 'Marco Dela Cruz', 'phone' => '(02) 8123-4567', 'address' => 'Quezon City', 'services_offered' => 'Preventive maintenance, engine repair'],
        ['id' => 2, 'shop_name' => 'Metro Tire & Auto', 'contact_person' => 'Ana Reyes', 'phone' => '0917-555-0182', 'address' => 'Caloocan City', 'services_offered' => 'Tire replacement, wheel alignment'],
    ];
}

if ($previewMode && !isset($_SESSION['admin_equipment_preview'])) {
    $_SESSION['admin_equipment_preview'] = [
        ['id' => 1, 'vehicle_id' => 101, 'equipment_type' => 'GPS Tracker', 'serial_number' => 'GPS-NS-1001', 'installation_date' => date('Y-m-d', strtotime('-60 days')), 'status' => 'Installed'],
        ['id' => 2, 'vehicle_id' => 102, 'equipment_type' => 'Dashcam', 'serial_number' => 'CAM-NS-2087', 'installation_date' => date('Y-m-d', strtotime('-30 days')), 'status' => 'Needs Service'],
    ];
}

$adminPageUrl = static function (string $page) use ($previewMode): string {
    return $page . ($previewMode ? '?preview=1' : '');
};
$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

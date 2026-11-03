<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/rbac.php';
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

requireValidCsrfToken();
$user = authenticatedUser($pdo);
if (!$user) {
    http_response_code(401);
    exit('Please sign in before requesting access.');
}

$requestedRole = $_POST['requested_role'] ?? '';
if (!is_string($requestedRole)) {
    http_response_code(400);
    exit('Invalid requested role.');
}

$request = createRoleRequest($pdo, $user, $requestedRole);
$_SESSION['flash_message'] = $request['message'];
header('Location: ' . dashboardPathForRole(effectiveRole($pdo, $user)));
exit;

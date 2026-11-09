<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession();
$user = authenticatedUser($pdo);
if (!$user) {
    http_response_code(401);
    exit('Please sign in to view this profile picture.');
}

$filename = $user['profile_photo'] ?? '';
if (!is_string($filename) || !preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $filename)) {
    http_response_code(404);
    exit('Profile picture not found.');
}

$path = __DIR__ . '/storage/profile_photos/' . $filename;
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Profile picture not found.');
}

$extension = pathinfo($filename, PATHINFO_EXTENSION);
$contentTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . $contentTypes[$extension]);
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);

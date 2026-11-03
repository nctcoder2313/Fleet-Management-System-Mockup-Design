<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$name = getenv('HEAD_ADMIN_NAME');
$email = getenv('HEAD_ADMIN_EMAIL');
$password = getenv('HEAD_ADMIN_PASSWORD');

if (!is_string($name) || trim($name) === ''
    || !is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    || !is_string($password) || strlen($password) < 12
) {
    fwrite(STDERR, "Set HEAD_ADMIN_NAME, HEAD_ADMIN_EMAIL, and HEAD_ADMIN_PASSWORD (at least 12 characters).\n");
    exit(1);
}

$pdo = require __DIR__ . '/../config/database.php';
$existingHeadAdmin = $pdo->query(
    "SELECT id FROM users WHERE role = 'head_admin' LIMIT 1"
)->fetchColumn();

if ($existingHeadAdmin) {
    fwrite(STDERR, "A Head Admin account already exists; no account was created.\n");
    exit(1);
}

$statement = $pdo->prepare(
    "INSERT INTO users (name, email, password_hash, role, status)
     VALUES (:name, :email, :password_hash, 'head_admin', 'active')"
);
$statement->execute([
    'name' => trim($name),
    'email' => strtolower(trim($email)),
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
]);

fwrite(STDOUT, "Head Admin account created.\n");

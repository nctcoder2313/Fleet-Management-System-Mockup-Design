<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$database = getenv('DB_NAME') ?: 'fleet_management';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD');

if ($password === false) {
    $password = '';
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $database);

return new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

<?php
declare(strict_types=1);

/**
 * Connects to `fleet_management_db` (shared with C:\xampp\htdocs\fleet_backend)
 * and automatically repairs any legacy columns/indexes on `users` (`email` unique key, `role` enum).
 */
function databaseConnection(): PDO
{
    $host = getenv('DB_HOST') ?: 'localhost';
    $database = getenv('DB_NAME') ?: 'fleet_management_db';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD');
    $passwordStr = $password === false ? '' : $password;

    $pdo = new PDO(
        "mysql:host={$host};charset=utf8mb4",
        $username,
        $passwordStr,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$database}`");

    // 1. Ensure `users` table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `user_id` INT(11) NOT NULL AUTO_INCREMENT,
            `fname` VARCHAR(100) NOT NULL,
            `mname` VARCHAR(100) DEFAULT NULL,
            `lname` VARCHAR(100) NOT NULL,
            `official_email` VARCHAR(150) NOT NULL UNIQUE,
            `personal_email` VARCHAR(150) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` ENUM('head-admin', 'admin', 'manager', 'accounting') NOT NULL DEFAULT 'admin',
            `otp_code` VARCHAR(10) DEFAULT NULL,
            `otp_expires_at` DATETIME DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Clean up legacy `email` column / unique index if present from an older schema
    $colsStmt = $pdo->query("SHOW COLUMNS FROM `users`");
    $cols = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_map( static fn(array $c): string => strtolower((string) $c['Field']), $cols);

    if (in_array('email', $colNames, true)) {
        // Drop any unique index on legacy `email` column that causes "Duplicate entry '' for key 'email'"
        $idxStmt = $pdo->query("SHOW INDEX FROM `users` WHERE Column_name = 'email'");
        $indexes = $idxStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($indexes as $idx) {
            $keyName = (string) $idx['Key_name'];
            if ($keyName !== 'PRIMARY') {
                try {
                    $pdo->exec("ALTER TABLE `users` DROP INDEX `{$keyName}`");
                } catch (PDOException $ignored) {
                }
            }
        }
        // Make legacy `email` column nullable with DEFAULT NULL
        try {
            $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(254) NULL DEFAULT NULL");
        } catch (PDOException $ignored) {
        }
    }

    // 3. Ensure `role` column supports all 4 backend roles ('head-admin', 'admin', 'manager', 'accounting')
    try {
        $pdo->exec("
            ALTER TABLE `users`
            MODIFY COLUMN `role` ENUM('head-admin', 'head_admin', 'admin', 'manager', 'accounting')
            NOT NULL DEFAULT 'admin'
        ");
        // Fix any blank role on headadmin@company.com
        $pdo->exec("UPDATE `users` SET `role` = 'head-admin' WHERE `official_email` = 'headadmin@company.com' AND (`role` = '' OR `role` IS NULL)");
        $pdo->exec("UPDATE `users` SET `role` = 'admin' WHERE `official_email` = 'admin@company.com' AND (`role` = '' OR `role` IS NULL)");
    } catch (PDOException $ignored) {
    }

    // 4. Seed default test accounts if headadmin@company.com does not exist yet
    $check = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `official_email` = :email");
    $check->execute([':email' => 'headadmin@company.com']);
    if ((int) $check->fetchColumn() === 0) {
        $seed = $pdo->prepare("
            INSERT IGNORE INTO `users` (`fname`, `mname`, `lname`, `official_email`, `personal_email`, `password_hash`, `role`, `is_active`)
            VALUES (:fname, :mname, :lname, :official_email, :personal_email, :password_hash, :role, 1)
        ");
        $seed->execute([
            ':fname'          => 'Ainie',
            ':mname'          => null,
            ':lname'          => 'Admin',
            ':official_email' => 'headadmin@company.com',
            ':personal_email' => 'ainie.personal@gmail.com',
            ':password_hash'  => password_hash('Admin@123', PASSWORD_DEFAULT),
            ':role'           => 'head-admin',
        ]);
        $seed->execute([
            ':fname'          => 'Raisa',
            ':mname'          => null,
            ':lname'          => 'Operations',
            ':official_email' => 'admin@company.com',
            ':personal_email' => 'raisa.personal@gmail.com',
            ':password_hash'  => password_hash('Admin@123', PASSWORD_DEFAULT),
            ':role'           => 'admin',
        ]);
        $seed->execute([
            ':fname'          => 'Accounting',
            ':mname'          => null,
            ':lname'          => 'Demo',
            ':official_email' => 'ainiemac@gmail.com',
            ':personal_email' => 'ainiemac.recovery@gmail.com',
            ':password_hash'  => password_hash('1234', PASSWORD_DEFAULT),
            ':role'           => 'accounting',
        ]);
    }

    return $pdo;
}

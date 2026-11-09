<?php
declare(strict_types=1);

$authPdo = require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/rbac.php';
startSecureSession();
$accountingUser = requireRole($authPdo, 'accounting');
define('ACCOUNTING_DATABASE_ALLOWED', true);

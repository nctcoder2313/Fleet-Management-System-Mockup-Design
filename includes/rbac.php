<?php
declare(strict_types=1);

const RBAC_ROLES = ['head_admin', 'admin', 'manager', 'user', 'accounting', 'driver'];
const ROLE_ELEVATION_HOURS_DEFAULT = 8;

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function csrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function requireValidCsrfToken(): void
{
    startSecureSession();
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals(csrfToken(), $submittedToken)) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }
}

function authenticatedUser(PDO $pdo): ?array
{
    startSecureSession();
    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT id, name, email, role, status
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    if (!$user || $user['status'] !== 'active' || !in_array($user['role'], RBAC_ROLES, true)) {
        $_SESSION = [];
        session_destroy();
        return null;
    }

    return $user;
}

function activeElevation(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare(
        "SELECT id, requested_role, expires_at
         FROM role_requests
         WHERE requester_id = :user_id
           AND status = 'GRANTED'
           AND expires_at > CURRENT_TIMESTAMP
         ORDER BY reviewed_at DESC
         LIMIT 1"
    );
    $statement->execute(['user_id' => $userId]);
    $grant = $statement->fetch();

    return $grant ?: null;
}

function completeLogin(PDO $pdo, int $userId, ?string $selectedRole = null): array
{
    $statement = $pdo->prepare(
        "SELECT id, name, email, role, status
         FROM users
         WHERE id = :id AND status = 'active'
         LIMIT 1"
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();
    if (!$user || !in_array($user['role'], RBAC_ROLES, true)) {
        throw new RuntimeException('Cannot start a session for an inactive or invalid account.');
    }

    startSecureSession();
    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int) $user['id']];
    csrfToken();

    $assignedEffectiveRole = effectiveRole($pdo, $user);
    if ($selectedRole !== null && $selectedRole !== $assignedEffectiveRole) {
        $request = createRoleRequest($pdo, $user, $selectedRole);
        $_SESSION['flash_message'] = $request['message'];
    }

    return [
        'user' => $user,
        'effective_role' => $assignedEffectiveRole,
        'dashboard' => dashboardPathForRole($assignedEffectiveRole),
    ];
}

function dashboardPathForRole(string $role): string
{
    return match ($role) {
        'head_admin' => '/Fleet-Management-System-Mockup-Design/dashboard/head_admin/dashboard.php',
        'admin' => '/Fleet-Management-System-Mockup-Design/dashboard/admin/dashboard.php',
        'manager' => '/Fleet-Management-System-Mockup-Design/dashboard/manager/dashboard.php',
        'user' => '/Fleet-Management-System-Mockup-Design/dashboard/user/dashboard.php',
        'accounting' => '/Fleet-Management-System-Mockup-Design/dashboard/accounting/dashboard.php',
        'driver' => '/Fleet-Management-System-Mockup-Design/dashboard/driver/dashboard.php',
        default => throw new InvalidArgumentException('Unknown application role.'),
    };
}

function effectiveRole(PDO $pdo, array $user): string
{
    $grant = activeElevation($pdo, (int) $user['id']);
    return $grant['requested_role'] ?? $user['role'];
}

function createRoleRequest(PDO $pdo, array $user, string $requestedRole): array
{
    if (!in_array($requestedRole, RBAC_ROLES, true) || $requestedRole === 'head_admin') {
        return ['created' => false, 'message' => 'That role cannot be requested through access elevation.'];
    }

    $currentRole = effectiveRole($pdo, $user);
    if ($currentRole === $requestedRole) {
        return ['created' => false, 'message' => 'This is already your active role.'];
    }

    if ($user['role'] === 'head_admin') {
        return ['created' => false, 'message' => 'Head Admin access cannot be elevated or switched.'];
    }

    if (activeElevation($pdo, (int) $user['id'])) {
        return ['created' => false, 'message' => 'Your temporary role elevation is active. You cannot request another role until it expires.'];
    }

    $pdo->beginTransaction();
    try {
        $lockUser = $pdo->prepare('SELECT id FROM users WHERE id = :id FOR UPDATE');
        $lockUser->execute(['id' => $user['id']]);

        $activeGrant = $pdo->prepare(
            "UPDATE role_requests
             SET status = 'EXPIRED', active_grant_user_id = NULL
             WHERE requester_id = :user_id
               AND status = 'GRANTED'
               AND expires_at <= CURRENT_TIMESTAMP"
        );
        $activeGrant->execute(['user_id' => $user['id']]);

        $existing = $pdo->prepare(
            "SELECT id
             FROM role_requests
             WHERE requester_id = :user_id
               AND requested_role = :requested_role
               AND status = 'PENDING'
             LIMIT 1"
        );
        $existing->execute([
            'user_id' => $user['id'],
            'requested_role' => $requestedRole,
        ]);
        $requestId = $existing->fetchColumn();

        if (!$requestId) {
            $insert = $pdo->prepare(
                "INSERT INTO role_requests
                    (requester_id, base_role, `current_role`, requested_role, status)
                 VALUES
                    (:requester_id, :base_role, :current_role, :requested_role, 'PENDING')"
            );
            $insert->execute([
                'requester_id' => $user['id'],
                'base_role' => $user['role'],
                'current_role' => $currentRole,
                'requested_role' => $requestedRole,
            ]);
            $requestId = (int) $pdo->lastInsertId();

            $audit = $pdo->prepare(
                "INSERT INTO audit_logs (actor_user_id, event_type, details)
                 VALUES (:actor_id, 'ROLE_ACCESS_REQUESTED', :details)"
            );
            $audit->execute([
                'actor_id' => $user['id'],
                'details' => json_encode([
                    'request_id' => $requestId,
                    'base_role' => $user['role'],
                    'requested_role' => $requestedRole,
                ], JSON_THROW_ON_ERROR),
            ]);
        }

        $pdo->commit();
        return [
            'created' => !$existing->rowCount(),
            'request_id' => (int) $requestId,
            'message' => 'Access request sent to Head Admin for approval.',
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function denyRoleAccess(PDO $pdo, string $requestedRole): void
{
    http_response_code(403);
    $message = 'You do not have permission to access this area.';
    $user = authenticatedUser($pdo);

    if ($user && in_array($requestedRole, RBAC_ROLES, true)) {
        $request = createRoleRequest($pdo, $user, $requestedRole);
        $message = $request['message'];
    }

    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $homeUrl = htmlspecialchars('/Fleet-Management-System-Mockup-Design/dashboard/head_admin/dashboard.php', ENT_QUOTES, 'UTF-8');
    exit(
        '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Access denied</title><body style="font:16px system-ui;background:#eff6ff;color:#0f172a;padding:3rem">'
        . '<main style="max-width:36rem;margin:10vh auto;background:white;padding:2rem;border-radius:1rem">'
        . '<h1>Access denied</h1><p role="alert">' . $safeMessage . '</p>'
        . '<a href="' . $homeUrl . '">Return to dashboard</a></main></body></html>'
    );
}

function requireRole(PDO $pdo, string $requiredRole): array
{
    if (!in_array($requiredRole, RBAC_ROLES, true)) {
        throw new InvalidArgumentException('Unknown application role.');
    }

    $user = authenticatedUser($pdo);
    if (!$user) {
        http_response_code(401);
        exit('Please sign in before accessing this page.');
    }

    if (effectiveRole($pdo, $user) !== $requiredRole) {
        denyRoleAccess($pdo, $requiredRole);
    }

    return $user;
}

function elevationHours(): int
{
    $configured = getenv('ROLE_ELEVATION_HOURS');
    if ($configured === false || $configured === '') {
        return ROLE_ELEVATION_HOURS_DEFAULT;
    }

    $hours = filter_var($configured, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 24],
    ]);
    if ($hours === false) {
        throw new RuntimeException('ROLE_ELEVATION_HOURS must be an integer from 1 to 24.');
    }

    return $hours;
}

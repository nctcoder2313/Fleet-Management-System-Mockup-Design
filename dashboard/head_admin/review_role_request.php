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
$reviewer = requireRole($pdo, 'head_admin');
$requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
$decision = $_POST['decision'] ?? '';

if (!$requestId || !in_array($decision, ['grant', 'deny'], true)) {
    http_response_code(400);
    exit('Invalid access request action.');
}

$pdo->beginTransaction();
try {
    $requestOwner = $pdo->prepare('SELECT requester_id FROM role_requests WHERE id = :id');
    $requestOwner->execute(['id' => $requestId]);
    $requesterId = $requestOwner->fetchColumn();
    if (!$requesterId) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = 'This request no longer exists.';
        header('Location: access_requests.php');
        exit;
    }

    $lockRequester = $pdo->prepare('SELECT id FROM users WHERE id = :id FOR UPDATE');
    $lockRequester->execute(['id' => $requesterId]);

    $statement = $pdo->prepare(
        "SELECT id, requester_id, base_role, requested_role
         FROM role_requests
         WHERE id = :id AND status = 'PENDING'
         FOR UPDATE"
    );
    $statement->execute(['id' => $requestId]);
    $request = $statement->fetch();

    if (!$request) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = 'This request has already been reviewed or no longer exists.';
        header('Location: access_requests.php');
        exit;
    }

    if ((int) $request['requester_id'] === (int) $reviewer['id']) {
        throw new RuntimeException('A user cannot approve their own access request.');
    }

    if ($request['base_role'] === 'head_admin' || $request['requested_role'] === 'head_admin') {
        throw new RuntimeException('Head Admin access cannot be elevated through this workflow.');
    }

    $reviewerHours = elevationHours();
    $newStatus = $decision === 'grant' ? 'GRANTED' : 'DENIED';
    if ($decision === 'grant') {
        $expireOldGrants = $pdo->prepare(
            "UPDATE role_requests
             SET status = 'EXPIRED', active_grant_user_id = NULL
             WHERE requester_id = :requester_id
               AND status = 'GRANTED'
               AND expires_at <= CURRENT_TIMESTAMP"
        );
        $expireOldGrants->execute(['requester_id' => $request['requester_id']]);

        $activeGrant = $pdo->prepare(
            "SELECT id
             FROM role_requests
             WHERE requester_id = :requester_id
               AND status = 'GRANTED'
               AND expires_at > CURRENT_TIMESTAMP
             LIMIT 1"
        );
        $activeGrant->execute(['requester_id' => $request['requester_id']]);
        if ($activeGrant->fetchColumn()) {
            throw new RuntimeException('This requester already has an active role elevation.');
        }
    }
    $reviewSql = $decision === 'grant'
        ? "UPDATE role_requests
           SET status = 'GRANTED',
               active_grant_user_id = :requester_id,
               reviewed_by = :reviewer_id,
               reviewed_at = CURRENT_TIMESTAMP,
               expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL {$reviewerHours} HOUR)
           WHERE id = :id AND status = 'PENDING'"
        : "UPDATE role_requests
           SET status = 'DENIED',
               active_grant_user_id = NULL,
               reviewed_by = :reviewer_id,
               reviewed_at = CURRENT_TIMESTAMP
           WHERE id = :id AND status = 'PENDING'";
    $update = $pdo->prepare($reviewSql);
    $updateParams = [
        'reviewer_id' => $reviewer['id'],
        'id' => $requestId,
    ];
    if ($decision === 'grant') {
        $updateParams['requester_id'] = $request['requester_id'];
    }
    $update->execute($updateParams);

    $audit = $pdo->prepare(
        'INSERT INTO audit_logs (actor_user_id, event_type, details)
         VALUES (:actor_id, :event_type, :details)'
    );
    $audit->execute([
        'actor_id' => $reviewer['id'],
        'event_type' => $decision === 'grant' ? 'ROLE_ACCESS_GRANTED' : 'ROLE_ACCESS_DENIED',
        'details' => json_encode([
            'request_id' => $requestId,
            'requester_id' => (int) $request['requester_id'],
            'requested_role' => $request['requested_role'],
            'status' => $newStatus,
            'expires_in_hours' => $decision === 'grant' ? $reviewerHours : null,
        ], JSON_THROW_ON_ERROR),
    ]);

    $pdo->commit();
    $_SESSION['flash_message'] = $decision === 'grant'
        ? "Access granted for {$reviewerHours} hours. The requester will use only the elevated role during that period."
        : 'Access request denied.';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

header('Location: access_requests.php');
exit;

<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'account_requests';
$pageTitle = 'Account requests';
$pageError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
    $decision = $_POST['decision'] ?? '';
    if (!$requestId || !is_string($decision) || !in_array($decision, ['approve', 'reject'], true)) {
        http_response_code(400);
        exit('Invalid account request decision.');
    }

    if ($previewMode) {
        $requestIndex = null;
        foreach ($_SESSION['head_admin_preview_data']['account_signup_requests'] as $index => $request) {
            if ((int) $request['id'] === $requestId && $request['status'] === 'PENDING') {
                $requestIndex = $index;
                break;
            }
        }
        if ($requestIndex === null) {
            headAdminRedirect(headAdminUrl('account_requests.php'), 'This sample request was already reviewed or no longer exists.');
        }
        $_SESSION['head_admin_preview_data']['account_signup_requests'][$requestIndex]['status'] =
            $decision === 'approve' ? 'APPROVED' : 'REJECTED';
        $_SESSION['head_admin_preview_data']['account_signup_requests'][$requestIndex]['reviewed_at'] = date('Y-m-d H:i:s');
        headAdminRecordAudit(
            $decision === 'approve' ? 'ACCOUNT_REQUEST_APPROVED' : 'ACCOUNT_REQUEST_REJECTED',
            [
                'request_id' => $requestId,
                'email' => $_SESSION['head_admin_preview_data']['account_signup_requests'][$requestIndex]['email'],
                'role' => $_SESSION['head_admin_preview_data']['account_signup_requests'][$requestIndex]['requested_role'],
            ]
        );
        headAdminRedirect(
            headAdminUrl('account_requests.php'),
            $decision === 'approve'
                ? 'Sample account request approved in preview only.'
                : 'Sample account request rejected in preview only.'
        );
    }

    try {
        $pdo->beginTransaction();
        $requestQuery = $pdo->prepare(
            "SELECT id, name, email, requested_role, password_hash
             FROM account_signup_requests
             WHERE id = :id AND status = 'PENDING'
             FOR UPDATE"
        );
        $requestQuery->execute(['id' => $requestId]);
        $request = $requestQuery->fetch();
        if (!$request) {
            $pdo->rollBack();
            headAdminRedirect(headAdminUrl('account_requests.php'), 'This request was already reviewed or no longer exists.');
        }

        if ($decision === 'approve') {
            if (!is_string($request['password_hash']) || $request['password_hash'] === '') {
                throw new RuntimeException('This request has no stored password hash and cannot be approved.');
            }
            $existingUser = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1 FOR UPDATE');
            $existingUser->execute(['email' => $request['email']]);
            if ($existingUser->fetchColumn()) {
                throw new RuntimeException('An account already uses this email. Resolve the duplicate before approving this request.');
            }

            $createUser = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash, role, status)
                 VALUES (:name, :email, :password_hash, :role, 'active')"
            );
            $createUser->execute([
                'name' => $request['name'],
                'email' => $request['email'],
                'password_hash' => $request['password_hash'],
                'role' => $request['requested_role'],
            ]);
        }

        $status = $decision === 'approve' ? 'APPROVED' : 'REJECTED';
        $review = $pdo->prepare(
            "UPDATE account_signup_requests
             SET status = :status,
                 password_hash = CASE WHEN :password_status = 'REJECTED' THEN NULL ELSE password_hash END,
                 reviewed_by = :reviewed_by,
                 reviewed_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $review->execute([
            'status' => $status,
            'password_status' => $status,
            'reviewed_by' => $headAdmin['id'],
            'id' => $requestId,
        ]);
        headAdminRecordAudit(
            $decision === 'approve' ? 'ACCOUNT_REQUEST_APPROVED' : 'ACCOUNT_REQUEST_REJECTED',
            ['request_id' => $requestId, 'email' => $request['email'], 'role' => $request['requested_role']]
        );
        $pdo->commit();
        headAdminRedirect(
            headAdminUrl('account_requests.php'),
            $decision === 'approve' ? 'Account created and request approved.' : 'Account request rejected.'
        );
    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pageError = $exception->getMessage();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Head Admin account request review error: ' . $exception->getMessage());
        $pageError = 'The request could not be reviewed. Check that the account request migration is applied and try again.';
    }
}

if ($previewMode) {
    $requests = array_values(array_filter(
        $_SESSION['head_admin_preview_data']['account_signup_requests'],
        static fn (array $request): bool => $request['status'] === 'PENDING'
    ));
} else {
    $statement = $pdo->query(
        "SELECT id, name, email, requested_role, reason, requested_at
         FROM account_signup_requests
         WHERE status = 'PENDING'
         ORDER BY requested_at ASC
         LIMIT 100"
    );
    $requests = $statement->fetchAll();
}

require __DIR__ . '/_layout_start.php';
?>
<section class="rounded-xl border border-blue-100 bg-white p-5 shadow-sm sm:p-6">
    <p class="text-sm font-medium text-slate-500">New account access</p>
    <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Review account requests</h2>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Approving a request creates an active account with the requested base role. Head Admin access must always be provisioned separately. Submitted passwords are stored as hashes.</p>
</section>

<?php if ($pageError !== ''): ?>
    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900"><?= $escape($pageError) ?></div>
<?php endif; ?>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
        <h2 class="font-bold text-slate-900">Pending account requests</h2>
        <p class="mt-1 text-sm text-slate-500"><?= count($requests) ?> request<?= count($requests) === 1 ? '' : 's' ?> awaiting review.</p>
    </div>
    <?php if (!$requests): ?>
        <p class="p-8 text-center text-sm text-slate-500">There are no account requests to review.</p>
    <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($requests as $request): ?>
                <article class="p-5 sm:px-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-slate-900"><?= $escape($request['name']) ?></h3>
                            <p class="mt-1 text-sm text-slate-600"><?= $escape($request['email']) ?></p>
                            <p class="mt-2 inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">
                                Requested role: <?= $escape($roleLabels[$request['requested_role']] ?? $request['requested_role']) ?>
                            </p>
                            <?php if (($request['reason'] ?? '') !== ''): ?>
                                <p class="mt-3 max-w-2xl whitespace-pre-wrap text-sm text-slate-600"><?= $escape($request['reason']) ?></p>
                            <?php endif; ?>
                            <time class="mt-3 block text-xs text-slate-500" datetime="<?= $escape(date(DATE_ATOM, strtotime($request['requested_at']))) ?>">
                                Submitted <?= $escape(date('M j, Y \a\t g:i A', strtotime($request['requested_at']))) ?>
                            </time>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <form method="post" class="confirm-action" data-confirm="Approve this request and create an active account for <?= $escape($request['email']) ?>?">
                                <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-800" name="decision" value="approve">Approve</button>
                            </form>
                            <form method="post" class="confirm-action" data-confirm="Reject this account request?">
                                <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" name="decision" value="reject">Reject</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_layout_end.php'; ?>

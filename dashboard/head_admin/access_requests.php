<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'access_requests';
$pageTitle = 'Access approvals';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$previewMode) {
        http_response_code(405);
        header('Allow: GET');
        exit('Method not allowed.');
    }

    requireValidCsrfToken();
    $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
    $decision = $_POST['decision'] ?? '';
    if (!$requestId || !is_string($decision) || !in_array($decision, ['grant', 'deny'], true)) {
        http_response_code(400);
        exit('Invalid preview access request decision.');
    }

    $requestIndex = null;
    foreach ($_SESSION['head_admin_preview_data']['pending_requests'] as $index => $request) {
        if ((int) $request['id'] === $requestId && $request['status'] === 'PENDING') {
            $requestIndex = $index;
            break;
        }
    }
    if ($requestIndex === null) {
        headAdminRedirect(headAdminUrl('access_requests.php'), 'This sample request was already reviewed or no longer exists.');
    }

    $request = &$_SESSION['head_admin_preview_data']['pending_requests'][$requestIndex];
    $request['status'] = $decision === 'grant' ? 'GRANTED' : 'DENIED';
    $request['reviewed_at'] = date('Y-m-d H:i:s');
    unset($request);
    $eventType = $decision === 'grant' ? 'ROLE_ACCESS_GRANTED' : 'ROLE_ACCESS_DENIED';
    headAdminRecordAudit($eventType, [
        'request_id' => $requestId,
        'requested_role' => $_SESSION['head_admin_preview_data']['pending_requests'][$requestIndex]['requested_role'],
        'status' => $decision === 'grant' ? 'GRANTED' : 'DENIED',
        'expires_in_hours' => $decision === 'grant' ? 8 : null,
    ]);
    headAdminRedirect(
        headAdminUrl('access_requests.php'),
        $decision === 'grant'
            ? 'Sample access request granted for eight hours in the preview.'
            : 'Sample access request denied in the preview.'
    );
}

if ($previewMode) {
    $requests = array_values(array_filter(
        $_SESSION['head_admin_preview_data']['pending_requests'],
        static fn (array $request): bool => $request['status'] === 'PENDING'
    ));
} else {
    $statement = $pdo->query(
        "SELECT rr.id, rr.base_role, rr.`current_role`, rr.requested_role, rr.requested_at,
                u.name AS requester_name, u.email AS requester_email
         FROM role_requests rr
         INNER JOIN users u ON u.id = rr.requester_id
         WHERE rr.status = 'PENDING'
         ORDER BY rr.requested_at ASC
         LIMIT 50"
    );
    $requests = $statement->fetchAll();
}

require __DIR__ . '/_layout_start.php';
?>
<section>
    <p class="text-sm font-medium text-slate-500">Temporary role elevation</p>
    <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Review access requests</h2>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Review each request against the employee’s current access needs. A grant temporarily replaces the requester’s base role and expires automatically.</p>
</section>

<section class="grid gap-4 sm:grid-cols-3">
    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">Awaiting review</p>
        <p class="mt-2 text-3xl font-bold text-slate-900"><?= count($requests) ?></p>
        <p class="mt-1 text-xs text-slate-500">Requests shown below</p>
    </article>
    <article class="rounded-xl border border-blue-100 bg-blue-50 p-5">
        <p class="text-sm font-medium text-blue-800">Grant duration</p>
        <p class="mt-2 text-3xl font-bold text-blue-950"><?= $previewMode ? 8 : elevationHours() ?> hours</p>
        <p class="mt-1 text-xs text-blue-800">Role elevation expires automatically</p>
    </article>
    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">Access safeguards</p>
        <p class="mt-2 text-base font-bold text-slate-900">Exclusive and temporary</p>
        <p class="mt-1 text-xs text-slate-500">Head Admin access cannot be requested</p>
    </article>
</section>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
        <h2 class="font-bold text-slate-900">Pending requests</h2>
        <p class="mt-1 text-sm text-slate-500">Oldest submissions appear first to support timely review.</p>
    </div>
    <?php if (!$requests): ?>
        <div class="p-10 text-center">
            <p class="font-semibold text-slate-800">You’re all caught up</p>
            <p class="mt-1 text-sm text-slate-500">New role elevation requests will appear here.</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($requests as $request): ?>
                <article class="flex flex-col gap-5 p-5 sm:p-6 xl:flex-row xl:items-center xl:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-900"><?= $escape($request['requester_name']) ?></h3>
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Pending review</span>
                        </div>
                        <a href="<?= $escape(headAdminUrl('users.php', ['q' => $request['requester_email']])) ?>" class="mt-1 inline-block break-all text-sm text-blue-700 hover:underline"><?= $escape($request['requester_email']) ?></a>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">Base role: <?= $escape($roleLabels[$request['base_role']] ?? $request['base_role']) ?></span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">Current role: <?= $escape($roleLabels[$request['current_role']] ?? $request['current_role']) ?></span>
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-800">Requested role: <?= $escape($roleLabels[$request['requested_role']] ?? $request['requested_role']) ?></span>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">Submitted <?= $escape(date('M j, Y \a\t g:i A', strtotime($request['requested_at']))) ?></p>
                    </div>
                    <?php if ($previewMode): ?>
                        <div class="flex shrink-0 gap-2">
                            <form method="post" class="confirm-action" data-confirm="Grant this sample role request for eight hours?">
                                <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                <input type="hidden" name="decision" value="grant">
                                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">Grant access</button>
                            </form>
                            <form method="post" class="confirm-action" data-confirm="Deny this sample role request?">
                                <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                <input type="hidden" name="decision" value="deny">
                                <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Deny</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="flex shrink-0 gap-2">
                            <form method="post" action="review_role_request.php" class="confirm-action" data-confirm="Grant this role elevation request for <?= (int) elevationHours() ?> hours?">
                                <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                <input type="hidden" name="decision" value="grant">
                                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">Grant access</button>
                            </form>
                            <form method="post" action="review_role_request.php" class="confirm-action" data-confirm="Deny this role elevation request?">
                                <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                <input type="hidden" name="decision" value="deny">
                                <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Deny</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_layout_end.php'; ?>

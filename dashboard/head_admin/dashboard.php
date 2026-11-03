<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/rbac.php';
startSecureSession();
$headAdmin = requireRole($pdo, 'head_admin');

$roleLabels = [
    'head_admin' => 'Head Admin',
    'admin' => 'Admin',
    'manager' => 'Manager',
    'user' => 'User',
    'accounting' => 'Accounting',
];
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$userCounts = array_fill_keys(array_keys($roleLabels), 0);
$roleCounts = $pdo->query(
    "SELECT role, COUNT(*) AS total
     FROM users
     WHERE status = 'active'
     GROUP BY role"
)->fetchAll();
foreach ($roleCounts as $row) {
    if (array_key_exists($row['role'], $userCounts)) {
        $userCounts[$row['role']] = (int) $row['total'];
    }
}

$totalActiveUsers = array_sum($userCounts);
$adminCount = $userCounts['head_admin'] + $userCounts['admin'];
$userDirectory = $pdo->query(
    'SELECT name, email, role, status, created_at
     FROM users
     ORDER BY name ASC
     LIMIT 20'
)->fetchAll();
$pendingCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM role_requests WHERE status = 'PENDING'"
)->fetchColumn();
$todayAuditCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM audit_logs WHERE created_at >= CURRENT_DATE'
)->fetchColumn();

$pendingRequests = $pdo->query(
    "SELECT rr.id, rr.base_role, rr.`current_role`, rr.requested_role, rr.requested_at,
            u.name AS requester_name, u.email AS requester_email
     FROM role_requests rr
     INNER JOIN users u ON u.id = rr.requester_id
     WHERE rr.status = 'PENDING'
     ORDER BY rr.requested_at ASC
     LIMIT 20"
)->fetchAll();

$requestedAuditPeriod = $_GET['audit_period'] ?? 'week';
$auditPeriod = is_string($requestedAuditPeriod) && in_array($requestedAuditPeriod, ['today', 'week', 'month'], true)
    ? $requestedAuditPeriod
    : 'week';
$auditWindow = match ($auditPeriod) {
    'today' => 'created_at >= CURRENT_DATE',
    'week' => 'created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 7 DAY)',
    'month' => 'YEAR(created_at) = YEAR(CURRENT_DATE) AND MONTH(created_at) = MONTH(CURRENT_DATE)',
};
$auditLogs = $pdo->query(
    "SELECT al.event_type, al.details, al.created_at, u.name AS actor_name
     FROM audit_logs al
     LEFT JOIN users u ON u.id = al.actor_user_id
     WHERE {$auditWindow}
     ORDER BY al.created_at DESC
     LIMIT 10"
)->fetchAll();

$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
$initials = strtoupper(substr((string) $headAdmin['name'], 0, 1));
$events = [
    'ROLE_ACCESS_REQUESTED' => 'Access request submitted',
    'ROLE_ACCESS_GRANTED' => 'Temporary access granted',
    'ROLE_ACCESS_DENIED' => 'Access request denied',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Oversight | Fleet Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="min-h-screen lg:flex">
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:static lg:translate-x-0">
            <div class="flex h-20 items-center gap-3 border-b border-slate-100 px-6">
                <div class="grid h-10 w-10 place-items-center rounded-xl bg-blue-700 text-sm font-bold text-white">FM</div>
                <div>
                    <p class="font-bold text-slate-900">Fleet Management</p>
                    <p class="text-xs text-slate-500">System oversight</p>
                </div>
            </div>
            <nav class="space-y-1 px-4 py-6 text-sm">
                <a href="#overview" class="flex items-center rounded-lg bg-blue-50 px-3 py-2.5 font-semibold text-blue-800">Overview</a>
                <a href="#users" class="flex items-center rounded-lg px-3 py-2.5 text-slate-600 transition hover:bg-slate-100">User management</a>
                <a href="#access-requests" class="flex items-center justify-between rounded-lg px-3 py-2.5 text-slate-600 transition hover:bg-slate-100">
                    <span>Access approvals</span>
                    <?php if ($pendingCount > 0): ?>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="#audit-logs" class="flex items-center rounded-lg px-3 py-2.5 text-slate-600 transition hover:bg-slate-100">Audit logs</a>
            </nav>
            <div class="mt-auto border-t border-slate-100 p-4">
                <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3">
                    <div class="grid h-10 w-10 place-items-center rounded-full bg-blue-100 font-bold text-blue-800"><?= $escape($initials) ?></div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold"><?= $escape($headAdmin['name']) ?></p>
                        <p class="text-xs text-slate-500">Head Admin</p>
                    </div>
                </div>
            </div>
        </aside>

        <div id="sidebar-backdrop" class="fixed inset-0 z-20 hidden bg-slate-950/40 lg:hidden"></div>
        <main class="min-w-0 flex-1">
            <header class="sticky top-0 z-10 flex h-20 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button id="sidebar-toggle" type="button" class="rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-50" aria-label="Toggle navigation" aria-expanded="false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">System oversight</p>
                        <h1 class="text-lg font-bold text-slate-900 sm:text-xl">Head Admin Dashboard</h1>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="hidden rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800 sm:inline-flex">Head Admin</span>
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-blue-700 font-semibold text-white"><?= $escape($initials) ?></span>
                    <form method="post" action="../../logout.php">
                        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                        <button type="submit" class="text-sm font-semibold text-slate-600 transition hover:text-blue-700">Sign out</button>
                    </form>
                </div>
            </header>

            <div class="mx-auto max-w-7xl space-y-8 p-4 sm:p-6 lg:p-8">
                <?php if ($flashMessage !== ''): ?>
                    <div role="status" class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-900">
                        <?= $escape($flashMessage) ?>
                    </div>
                <?php endif; ?>

                <section id="overview">
                    <div class="mb-5">
                        <h2 class="text-xl font-bold text-slate-900">System overview</h2>
                        <p class="mt-1 text-sm text-slate-500">Account access, approvals, and system activity at a glance.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-medium text-slate-500">Active system users</p>
                            <p class="mt-3 text-3xl font-bold text-slate-900"><?= $totalActiveUsers ?></p>
                            <p class="mt-2 text-xs text-slate-500">Across all assigned roles</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-medium text-slate-500">Admins</p>
                            <p class="mt-3 text-3xl font-bold text-slate-900"><?= $adminCount ?></p>
                            <p class="mt-2 text-xs text-slate-500">Head Admin and Admin accounts</p>
                        </article>
                        <article class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                            <p class="text-sm font-medium text-amber-800">Pending access requests</p>
                            <p class="mt-3 text-3xl font-bold text-amber-950"><?= $pendingCount ?></p>
                            <a href="#access-requests" class="mt-2 inline-flex text-xs font-semibold text-amber-900 underline underline-offset-2">Review requests</a>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-sm font-medium text-slate-500">Audit events today</p>
                            <p class="mt-3 text-3xl font-bold text-slate-900"><?= $todayAuditCount ?></p>
                            <p class="mt-2 text-xs text-slate-500">Recorded system actions</p>
                        </article>
                    </div>
                </section>

                <section id="users" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="mb-5">
                        <h2 class="text-lg font-bold text-slate-900">User accounts by role</h2>
                        <p class="mt-1 text-sm text-slate-500">Active account distribution from the user directory.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        <?php foreach ($roleLabels as $role => $label): ?>
                            <?php $count = $userCounts[$role]; $percentage = $totalActiveUsers > 0 ? (int) round($count / $totalActiveUsers * 100) : 0; ?>
                            <article class="rounded-lg border border-slate-100 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-slate-700"><?= $escape($label) ?></h3>
                                    <span class="text-sm font-bold text-slate-900"><?= $count ?></span>
                                </div>
                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full rounded-full bg-blue-600" style="width: <?= $percentage ?>%"></div>
                                </div>
                                <p class="mt-2 text-xs text-slate-500"><?= $percentage ?>% of active accounts</p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-7">
                        <div class="mb-3">
                            <h3 class="font-semibold text-slate-900">User directory</h3>
                            <p class="mt-1 text-sm text-slate-500">Latest 20 accounts, including inactive accounts.</p>
                        </div>
                        <div class="overflow-x-auto rounded-lg border border-slate-100">
                            <table class="w-full min-w-[640px] text-left text-sm">
                                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 font-semibold">Name</th>
                                        <th scope="col" class="px-4 py-3 font-semibold">Email</th>
                                        <th scope="col" class="px-4 py-3 font-semibold">Role</th>
                                        <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                                        <th scope="col" class="px-4 py-3 font-semibold">Joined</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php if (!$userDirectory): ?>
                                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No user accounts found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($userDirectory as $account): ?>
                                            <tr class="hover:bg-slate-50">
                                                <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-800"><?= $escape($account['name']) ?></td>
                                                <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= $escape($account['email']) ?></td>
                                                <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= $escape($roleLabels[$account['role']] ?? $account['role']) ?></td>
                                                <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $account['status'] === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>"><?= $escape(ucfirst($account['status'])) ?></span></td>
                                                <td class="whitespace-nowrap px-4 py-3 text-slate-500"><?= $escape(date('M j, Y', strtotime($account['created_at']))) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section id="access-requests" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Pending access requests</h2>
                            <p class="mt-1 text-sm text-slate-500">Review role elevation requests. Approved access is temporary and exclusive.</p>
                        </div>
                        <span class="w-fit rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800"><?= $pendingCount ?> pending</span>
                    </div>
                    <?php if (!$pendingRequests): ?>
                        <div class="p-8 text-center">
                            <p class="font-semibold text-slate-700">No pending requests</p>
                            <p class="mt-1 text-sm text-slate-500">New access requests will appear here for review.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($pendingRequests as $request): ?>
                                <article class="flex flex-col gap-4 p-5 sm:p-6 xl:flex-row xl:items-center xl:justify-between">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900"><?= $escape($request['requester_name']) ?></p>
                                        <p class="mt-0.5 break-all text-sm text-slate-500"><?= $escape($request['requester_email']) ?></p>
                                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">Base: <?= $escape($roleLabels[$request['base_role']] ?? $request['base_role']) ?></span>
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">Current: <?= $escape($roleLabels[$request['current_role']] ?? $request['current_role']) ?></span>
                                            <span class="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-800">Requested: <?= $escape($roleLabels[$request['requested_role']] ?? $request['requested_role']) ?></span>
                                        </div>
                                        <p class="mt-3 text-xs text-slate-500">Submitted <?= $escape(date('M j, Y \a\t g:i A', strtotime($request['requested_at']))) ?></p>
                                    </div>
                                    <div class="flex shrink-0 gap-2">
                                        <form method="post" action="review_role_request.php" class="review-request-form">
                                            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                                            <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                            <input type="hidden" name="decision" value="grant">
                                            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">Grant access</button>
                                        </form>
                                        <form method="post" action="review_role_request.php" class="review-request-form">
                                            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                                            <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                                            <input type="hidden" name="decision" value="deny">
                                            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Deny</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section id="audit-logs" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Recent audit activity</h2>
                            <p class="mt-1 text-sm text-slate-500">Latest access requests and approval decisions.</p>
                        </div>
                        <nav class="flex flex-wrap gap-2" aria-label="Filter audit activity">
                            <?php foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $period => $label): ?>
                                <a href="?audit_period=<?= $escape($period) ?>#audit-logs" class="rounded-lg px-3 py-2 text-xs font-semibold transition <?= $auditPeriod === $period ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>" <?= $auditPeriod === $period ? 'aria-current="page"' : '' ?>><?= $escape($label) ?></a>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                    <?php if (!$auditLogs): ?>
                        <p class="p-8 text-center text-sm text-slate-500">No audit activity has been recorded yet.</p>
                    <?php else: ?>
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($auditLogs as $log): ?>
                                <div class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800"><?= $escape($events[$log['event_type']] ?? ucwords(strtolower(str_replace('_', ' ', $log['event_type'])))) ?></p>
                                        <p class="mt-1 text-xs text-slate-500">By <?= $escape($log['actor_name'] ?? 'System') ?></p>
                                    </div>
                                    <time class="text-xs text-slate-500" datetime="<?= $escape(date(DATE_ATOM, strtotime($log['created_at']))) ?>"><?= $escape(date('M j, Y \a\t g:i A', strtotime($log['created_at']))) ?></time>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <p class="text-center text-xs text-slate-500">System oversight only. Operational fleet, fuel, maintenance, and accounting data are not displayed here.</p>
            </div>
        </main>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarBackdrop = document.getElementById('sidebar-backdrop');
        const desktopViewport = window.matchMedia('(min-width: 1024px)');

        function setSidebarOpen(open) {
            sidebar.classList.toggle('-translate-x-full', !open);
            sidebarBackdrop.classList.toggle('hidden', !open);
            sidebarToggle.setAttribute('aria-expanded', String(open));
        }

        sidebarToggle.setAttribute('aria-expanded', String(desktopViewport.matches));
        sidebarToggle.addEventListener('click', () => {
            if (desktopViewport.matches) {
                const collapsed = sidebar.classList.toggle('lg:hidden');
                sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
                return;
            }
            setSidebarOpen(sidebar.classList.contains('-translate-x-full'));
        });
        sidebarBackdrop.addEventListener('click', () => setSidebarOpen(false));
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            if (desktopViewport.matches) {
                sidebar.classList.add('lg:hidden');
                sidebarToggle.setAttribute('aria-expanded', 'false');
            } else {
                setSidebarOpen(false);
            }
        });
        document.querySelectorAll('.review-request-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!window.confirm('Confirm this access request decision?')) event.preventDefault();
            });
        });
    </script>
</body>
</html>

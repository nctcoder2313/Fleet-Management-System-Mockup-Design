<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'dashboard';
$pageTitle = 'System overview';

if ($previewMode) {
    $users = $_SESSION['head_admin_preview_data']['users'];
    $userCounts = array_fill_keys(array_keys($roleLabels), 0);
    foreach ($users as $account) {
        if ($account['status'] === 'active' && array_key_exists($account['role'], $userCounts)) {
            $userCounts[$account['role']]++;
        }
    }
    $auditLogs = $_SESSION['head_admin_preview_data']['audit_logs'];
    $todayAuditCount = count(array_filter(
        $auditLogs,
        static fn (array $log): bool => date('Y-m-d', strtotime($log['created_at'])) === date('Y-m-d')
    ));
    $recentActivity = array_slice(array_values(array_filter(
        $auditLogs,
        static fn (array $log): bool => strtotime($log['created_at']) >= strtotime('-7 days')
    )), 0, 5);
} else {
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
    $todayAuditCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM audit_logs WHERE created_at >= CURRENT_DATE'
    )->fetchColumn();
    $recentActivity = $pdo->query(
        'SELECT al.event_type, al.details, al.created_at, u.name AS actor_name
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.actor_user_id
         WHERE al.created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 7 DAY)
         ORDER BY al.created_at DESC
         LIMIT 5'
    )->fetchAll();
}

$totalActiveUsers = array_sum($userCounts);
$adminCount = $userCounts['admin'];
$eventLabels = [
    'ROLE_ACCESS_REQUESTED' => 'Access request submitted',
    'ROLE_ACCESS_GRANTED' => 'Temporary access granted',
    'ROLE_ACCESS_DENIED' => 'Access request denied',
    'USER_CREATED' => 'User account created',
    'USER_UPDATED' => 'User account updated',
    'USER_DEACTIVATED' => 'User account deactivated',
    'USER_REACTIVATED' => 'User account reactivated',
    'USER_PASSWORD_RESET' => 'User password reset',
    'TRIP_FORCE_COMPLETED' => 'Trip force completed',
    'TRIP_CANCELLED' => 'Trip cancelled',
];

require __DIR__ . '/_layout_start.php';
?>
<section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-sm font-medium text-slate-500"><?= $escape(date('l, F j, Y')) ?></p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Welcome, <?= $escape($headAdmin['name']) ?></h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-600">Monitor account access, review role requests, and keep a clear record of system administration.</p>
    </div>
    <a href="<?= $escape(headAdminUrl('users.php', ['action' => 'create'])) ?>" class="inline-flex w-fit items-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">
        Add user account
    </a>
</section>

<a href="<?= $escape(headAdminUrl('fleet_overview.php')) ?>" class="group flex flex-col gap-3 rounded-xl border border-blue-200 bg-blue-50 p-5 transition hover:border-blue-300 hover:bg-blue-100 sm:flex-row sm:items-center sm:justify-between">
    <span>
        <span class="block font-bold text-blue-950">Fleet operations workspace</span>
        <span class="mt-1 block text-sm text-blue-800">Open vehicle status summaries and the dedicated emergency trip override tools.</span>
    </span>
    <span class="shrink-0 text-sm font-semibold text-blue-800 group-hover:underline">Open fleet workspace <span aria-hidden="true">&rarr;</span></span>
</a>

<section aria-label="System account summary" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <a href="<?= $escape(headAdminUrl('users.php', ['status' => 'active'])) ?>" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md">
        <p class="text-sm font-medium text-slate-500">Active user accounts</p>
        <p class="mt-3 text-3xl font-bold text-slate-900"><?= $totalActiveUsers ?></p>
        <span class="mt-2 inline-flex text-xs font-semibold text-blue-700 group-hover:underline">Open user directory <span aria-hidden="true">&rarr;</span></span>
    </a>
    <a href="<?= $escape(headAdminUrl('users.php', ['role' => 'admin', 'status' => 'active'])) ?>" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md">
        <p class="text-sm font-medium text-slate-500">Active Admin accounts</p>
        <p class="mt-3 text-3xl font-bold text-slate-900"><?= $adminCount ?></p>
        <span class="mt-2 inline-flex text-xs font-semibold text-blue-700 group-hover:underline">Review Admin accounts <span aria-hidden="true">&rarr;</span></span>
    </a>
    <a href="<?= $escape(headAdminUrl('access_requests.php')) ?>" class="group rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm transition hover:border-amber-300 hover:shadow-md">
        <p class="text-sm font-medium text-amber-800">Pending access requests</p>
        <p class="mt-3 text-3xl font-bold text-amber-950"><?= $pendingCount ?></p>
        <span class="mt-2 inline-flex text-xs font-semibold text-amber-900 group-hover:underline">Review requests <span aria-hidden="true">&rarr;</span></span>
    </a>
    <a href="<?= $escape(headAdminUrl('audit_logs.php', ['period' => 'today'])) ?>" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md">
        <p class="text-sm font-medium text-slate-500">Audit events today</p>
        <p class="mt-3 text-3xl font-bold text-slate-900"><?= $todayAuditCount ?></p>
        <span class="mt-2 inline-flex text-xs font-semibold text-blue-700 group-hover:underline">View audit history <span aria-hidden="true">&rarr;</span></span>
    </a>
</section>

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Active accounts by role</h2>
            <p class="mt-1 text-sm text-slate-500">Select a role to open the corresponding filtered user directory.</p>
        </div>
        <a href="<?= $escape(headAdminUrl('users.php')) ?>" class="text-sm font-semibold text-blue-700 hover:underline">View all accounts</a>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <?php foreach ($roleLabels as $role => $label): ?>
            <?php $count = $userCounts[$role]; $percentage = $totalActiveUsers > 0 ? (int) round($count / $totalActiveUsers * 100) : 0; ?>
            <a href="<?= $escape(headAdminUrl('users.php', ['role' => $role, 'status' => 'active'])) ?>" class="rounded-lg border border-slate-100 bg-slate-50 p-4 transition hover:border-blue-200 hover:bg-blue-50">
                <span class="flex items-center justify-between gap-2">
                    <span class="text-sm font-semibold text-slate-700"><?= $escape($label) ?></span>
                    <span class="text-sm font-bold text-slate-900"><?= $count ?></span>
                </span>
                <span class="mt-3 block h-2 overflow-hidden rounded-full bg-slate-200">
                    <span class="block h-full rounded-full bg-blue-600" style="width: <?= $percentage ?>%"></span>
                </span>
                <span class="mt-2 block text-xs text-slate-500"><?= $percentage ?>% of active accounts</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
        <div>
            <h2 class="font-bold text-slate-900">Recent system activity</h2>
            <p class="mt-1 text-sm text-slate-500">Administrative events recorded during the past seven days.</p>
        </div>
        <a href="<?= $escape(headAdminUrl('audit_logs.php', ['period' => 'week'])) ?>" class="text-sm font-semibold text-blue-700 hover:underline">View full history</a>
    </div>
    <?php if (!$recentActivity): ?>
        <p class="p-8 text-center text-sm text-slate-500">No recent administrative activity.</p>
    <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($recentActivity as $log): ?>
                <div class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <p class="text-sm font-semibold text-slate-800"><?= $escape($eventLabels[$log['event_type']] ?? ucwords(strtolower(str_replace('_', ' ', $log['event_type'])))) ?></p>
                        <p class="mt-1 text-xs text-slate-500">By <?= $escape($log['actor_name'] ?? 'System') ?></p>
                    </div>
                    <time class="text-xs text-slate-500" datetime="<?= $escape(date(DATE_ATOM, strtotime($log['created_at']))) ?>"><?= $escape(date('M j, Y \a\t g:i A', strtotime($log['created_at']))) ?></time>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_layout_end.php'; ?>

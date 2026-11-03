<?php
$today = date('l, F j, Y');
$roles = [
    'Head Admin' => ['count' => 1, 'color' => 'bg-blue-800'],
    'Admin' => ['count' => 8, 'color' => 'bg-blue-600'],
    'Manager' => ['count' => 12, 'color' => 'bg-sky-500'],
    'Accounting' => ['count' => 6, 'color' => 'bg-sky-400'],
    'User' => ['count' => 25, 'color' => 'bg-sky-300'],
];
$auditLogs = [
    ['date' => date('Y-m-d'), 'time' => date('g:i A'), 'actor' => 'Maria Santos', 'action' => 'Approved vehicle inspection', 'detail' => 'Toyota HiAce · ABC 2048'],
    ['date' => date('Y-m-d', strtotime('-1 day')), 'time' => '3:42 PM', 'actor' => 'Juan Dela Cruz', 'action' => 'Updated vehicle status', 'detail' => 'Isuzu N-Series · DEF 1760'],
    ['date' => date('Y-m-d', strtotime('-4 days')), 'time' => '10:18 AM', 'actor' => 'Ana Reyes', 'action' => 'Submitted repair request', 'detail' => 'Nissan Navara · JKL 3091'],
    ['date' => date('Y-m-d', strtotime('-12 days')), 'time' => '9:05 AM', 'actor' => 'Maria Santos', 'action' => 'Added a user account', 'detail' => 'Fleet operations'],
];
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Head Admin Dashboard | FleetFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <div class="min-h-screen lg:flex">
        <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-950/40 lg:hidden" aria-hidden="true"></div>
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-all duration-200 lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:translate-x-0">
            <div class="flex h-20 shrink-0 items-center justify-between border-b border-slate-100 px-5">
                <a href="dashboard.php" class="flex items-center gap-3" aria-label="FleetFlow dashboard">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-700 text-white">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.5V18a1 1 0 0 0 1 1h1m15-5.5V18a1 1 0 0 1-1 1h-1M3 13.5l1.5-5A2 2 0 0 1 6.4 7h11.2a2 2 0 0 1 1.9 1.5l1.5 5M3 13.5h18M6 19h12M7 16h.01M17 16h.01M8 7l1-3h6l1 3" /></svg>
                    </span>
                    <span class="sidebar-copy"><span class="block font-bold tracking-tight">Fleet<span class="text-blue-700">Flow</span></span><span class="block text-xs text-slate-500">Management System</span></span>
                </a>
                <button id="mobile-sidebar-close" type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Close navigation">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-6" aria-label="Main navigation">
                <p class="sidebar-copy px-3 pb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Organization</p>
                <a href="dashboard.php" aria-current="page" class="sidebar-link flex items-center gap-3 rounded-lg bg-blue-50 px-3 py-2.5 text-sm font-semibold text-blue-800" title="Dashboard">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10Zm10 8h8V11h-8v10Zm0-12h8V3h-8v6ZM3 21h8v-6H3v6Z" /></svg><span class="sidebar-copy">Dashboard</span>
                </a>
                <a href="fleet.php" class="sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100" title="Fleet monitoring">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.5V18a1 1 0 0 0 1 1h1m15-5.5V18a1 1 0 0 1-1 1h-1M3 13.5l1.5-5A2 2 0 0 1 6.4 7h11.2a2 2 0 0 1 1.9 1.5l1.5 5M3 13.5h18M6 19h12M7 16h.01M17 16h.01" /></svg><span class="sidebar-copy">Fleet monitoring</span>
                </a>
                <a href="#users" class="sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100" title="User access">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 19h5v-1a4 4 0 0 0-5.7-3.6M16 19H8m8 0v-1a5 5 0 0 0-10 0v1m2-11a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z" /></svg><span class="sidebar-copy">User access</span>
                </a>
                <a href="#audit-logs" class="sidebar-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100" title="Audit logs">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg><span class="sidebar-copy">Audit logs</span>
                </a>
            </nav>

            <div class="border-t border-slate-100 p-3">
                <div class="sidebar-profile flex items-center gap-3 rounded-xl bg-slate-50 p-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-800">HA</span>
                    <span class="sidebar-copy min-w-0 flex-1"><span class="block truncate text-sm font-semibold">Head Administrator</span><span class="mt-1 inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-semibold text-blue-800">Head Admin</span></span>
                </div>
            </div>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="sticky top-0 z-20 flex h-20 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button id="mobile-sidebar-open" type="button" aria-label="Open navigation" class="rounded-lg border border-slate-200 p-2.5 text-slate-600 hover:bg-slate-50 lg:hidden">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <button id="desktop-sidebar-toggle" type="button" aria-label="Collapse sidebar" class="hidden rounded-lg border border-slate-200 p-2.5 text-slate-600 hover:bg-slate-50 lg:block">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM9 5v14" /></svg>
                    </button>
                    <div><p class="text-xs text-slate-500 sm:text-sm"><?= $escape($today) ?></p><h1 class="text-base font-bold tracking-tight sm:text-xl">Head Admin Dashboard</h1></div>
                </div>
                <div class="flex items-center gap-3">
                    <button id="notifications-button" type="button" aria-label="View notifications" class="relative rounded-lg border border-slate-200 p-2.5 text-slate-600 transition hover:bg-slate-50">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12h4" /></svg>
                        <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-rose-500"></span>
                    </button>
                    <div class="hidden items-center gap-2 sm:flex"><span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-800">HA</span><span><span class="block text-sm font-semibold">Head Admin</span><span class="block text-xs text-slate-500">Administrator</span></span></div>
                </div>
            </header>

            <div class="mx-auto max-w-[1600px] space-y-8 p-4 sm:p-6 lg:space-y-10 lg:p-8">
                <section aria-labelledby="overview-heading">
                    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                        <div><h2 id="overview-heading" class="text-xl font-bold sm:text-2xl">Organization overview</h2><p class="mt-1 text-sm text-slate-600">Fleet, user access, expenses, and approvals at a glance.</p></div>
                        <span class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Dashboard figures are sample data</span>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2 2xl:grid-cols-4">
                        <a href="fleet.php?filter=all" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:p-6">
                            <div class="flex items-center justify-between"><span class="text-sm font-medium text-slate-500">Total fleet size</span><span class="rounded-lg bg-blue-50 p-2.5 text-blue-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.5V18a1 1 0 0 0 1 1h1m15-5.5V18a1 1 0 0 1-1 1h-1M3 13.5l1.5-5A2 2 0 0 1 6.4 7h11.2a2 2 0 0 1 1.9 1.5l1.5 5M3 13.5h18M6 19h12M7 16h.01M17 16h.01" /></svg></span></div>
                            <p class="mt-5 text-3xl font-bold">51 <span class="text-sm font-medium text-slate-500">vehicles</span></p><div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs"><span class="font-semibold text-emerald-700">● 44 active</span><span class="font-semibold text-slate-500">● 7 inactive</span></div><p class="mt-4 text-xs font-semibold text-blue-700 group-hover:underline">Open fleet monitoring →</p>
                        </a>

                        <button type="button" data-open-modal="users-modal" class="text-left group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:p-6">
                            <div class="flex items-center justify-between"><span class="text-sm font-medium text-slate-500">Active system users</span><span class="rounded-lg bg-sky-50 p-2.5 text-sky-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 19h5v-1a4 4 0 0 0-5.7-3.6M16 19H8m8 0v-1a5 5 0 0 0-10 0v1m2-11a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z" /></svg></span></div>
                            <p class="mt-5 text-3xl font-bold">48 <span class="text-sm font-medium text-slate-500">of 52</span></p><p class="mt-2 text-xs text-slate-500">8 Admins · 40 other roles</p><p class="mt-4 text-xs font-semibold text-blue-700 group-hover:underline">View users by role →</p>
                        </button>

                        <button type="button" data-open-modal="expenses-modal" class="text-left group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:p-6">
                            <div class="flex items-center justify-between"><span class="text-sm font-medium text-slate-500">Monthly expenses</span><span class="rounded-lg bg-indigo-50 p-2.5 text-indigo-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m5-14H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg></span></div>
                            <p class="mt-5 text-3xl font-bold">₱280,700</p><p class="mt-2 text-xs text-slate-500">Fuel ₱184,500 · Maintenance ₱96,200</p><p class="mt-4 text-xs font-semibold text-blue-700 group-hover:underline">View expense breakdown →</p>
                        </button>

                        <button type="button" data-open-modal="approvals-modal" class="text-left group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:p-6">
                            <div class="flex items-center justify-between"><span class="text-sm font-medium text-slate-500">Pending approvals</span><span class="rounded-lg bg-amber-50 p-2.5 text-amber-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 2.7 17a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg></span></div>
                            <p class="mt-5 text-3xl font-bold">4 <span class="text-sm font-medium text-slate-500">requests</span></p><p class="mt-2 text-xs text-slate-500">High-cost repairs · ₱248,000 total</p><p class="mt-4 text-xs font-semibold text-blue-700 group-hover:underline">Review budget requests →</p>
                        </button>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-5" aria-label="Fleet analytics and system health">
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-3">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-bold">Fleet availability</h2><p class="mt-1 text-sm text-slate-500">Current status across all 51 vehicles</p></div><a href="fleet.php?filter=all" class="text-sm font-semibold text-blue-700 hover:underline">View fleet →</a></div>
                        <div class="mt-6 space-y-5">
                            <div><div class="mb-2 flex justify-between text-sm"><span class="font-medium">On trip</span><span class="font-semibold">32 <span class="font-normal text-slate-500">(63%)</span></span></div><div class="h-2.5 rounded-full bg-slate-100"><div class="h-2.5 rounded-full bg-blue-600" style="width:62.7%"></div></div></div>
                            <div><div class="mb-2 flex justify-between text-sm"><span class="font-medium">Available</span><span class="font-semibold">12 <span class="font-normal text-slate-500">(24%)</span></span></div><div class="h-2.5 rounded-full bg-slate-100"><div class="h-2.5 rounded-full bg-sky-500" style="width:23.5%"></div></div></div>
                            <div><div class="mb-2 flex justify-between text-sm"><span class="font-medium">Maintenance</span><span class="font-semibold">5 <span class="font-normal text-slate-500">(10%)</span></span></div><div class="h-2.5 rounded-full bg-slate-100"><div class="h-2.5 rounded-full bg-amber-400" style="width:9.8%"></div></div></div>
                            <div><div class="mb-2 flex justify-between text-sm"><span class="font-medium">Damaged</span><span class="font-semibold">2 <span class="font-normal text-slate-500">(4%)</span></span></div><div class="h-2.5 rounded-full bg-slate-100"><div class="h-2.5 rounded-full bg-rose-400" style="width:3.9%"></div></div></div>
                        </div>
                        <p class="mt-5 border-t border-slate-100 pt-4 text-xs text-slate-500">Inactive vehicles include units undergoing maintenance or damage inspection.</p>
                    </article>

                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-2">
                        <div class="flex items-start justify-between"><div><h2 class="font-bold">System health</h2><p class="mt-1 text-sm text-slate-500">Service status overview</p></div><span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Operational</span></div>
                        <div class="mt-6 space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3"><span class="text-sm text-slate-600">Fleet data sync</span><span class="text-xs font-semibold text-emerald-700">Connected</span></div>
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3"><span class="text-sm text-slate-600">User access service</span><span class="text-xs font-semibold text-emerald-700">Operational</span></div>
                            <div class="flex items-center justify-between"><span class="text-sm text-slate-600">Last system check</span><span class="text-xs font-medium text-slate-500">Today, <?= $escape(date('g:i A')) ?></span></div>
                        </div>
                        <p class="mt-5 rounded-lg bg-amber-50 px-3 py-2.5 text-xs leading-5 text-amber-800">Demo status only. Live health checks require backend monitoring.</p>
                    </article>
                </section>

                <section id="users" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="users-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 id="users-heading" class="font-bold">Users by role</h2><p class="mt-1 text-sm text-slate-500">Account distribution across the system</p></div><button type="button" data-open-modal="users-modal" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Manage users</button></div>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
                        <?php foreach ($roles as $role => $data): ?>
                            <div><div class="mb-2 flex justify-between text-sm"><span class="font-medium text-slate-700"><?= $escape($role) ?></span><span class="font-semibold"><?= (int) $data['count'] ?></span></div><div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full <?= $escape($data['color']) ?>" style="width:<?= ((int) $data['count'] / 52) * 100 ?>%"></div></div></div>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-5 text-xs text-slate-500">52 accounts total · 48 currently active</p>
                </section>

                <section class="grid gap-6 xl:grid-cols-5" aria-label="Quick actions and recent activity">
                    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-2">
                        <h2 class="font-bold">Quick actions</h2><p class="mt-1 text-sm text-slate-500">Common Head Admin tasks</p>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <button type="button" data-open-modal="users-modal" class="flex min-h-11 items-center justify-center gap-2 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition duration-200 hover:bg-blue-800"><span aria-hidden="true">＋</span>Add New User/Admin</button>
                            <button type="button" data-open-modal="approvals-modal" class="flex min-h-11 items-center justify-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-800 transition duration-200 hover:bg-blue-100">Approve Budget Requests</button>
                            <button type="button" data-open-modal="audit-modal" class="flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition duration-200 hover:bg-slate-50">View System Audit Logs</button>
                            <button id="export-report" type="button" class="flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition duration-200 hover:bg-slate-50">Export Fleet Report</button>
                        </div>
                    </article>

                    <article id="audit-logs" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-3">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-bold">Recent activity</h2><p class="mt-1 text-sm text-slate-500">Latest system audit log entries</p></div><label class="flex items-center gap-2 text-xs font-medium text-slate-600">Period<select id="audit-period" class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-700 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"><option value="today">Today</option><option value="week" selected>This week</option><option value="month">This month</option></select></label></div>
                        <div id="audit-log-list" class="mt-5 divide-y divide-slate-100">
                            <?php foreach ($auditLogs as $index => $log): ?>
                                <div class="audit-log-item flex flex-wrap items-start justify-between gap-x-4 gap-y-2 py-4 first:pt-0 last:pb-0" data-log-date="<?= $escape($log['date']) ?>">
                                    <div class="flex min-w-0 gap-3"><span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-blue-500"></span><div class="min-w-0"><p class="text-sm font-semibold"><?= $escape($log['action']) ?></p><p class="mt-1 text-xs text-slate-500"><?= $escape($log['actor']) ?> · <?= $escape($log['detail']) ?></p></div></div>
                                    <time datetime="<?= $escape($log['date']) ?>" class="pl-5 text-xs text-slate-500"><?= $escape($log['date'] === date('Y-m-d') ? 'Today, ' . $log['time'] : date('M j', strtotime($log['date'])) . ', ' . $log['time']) ?></time>
                                </div>
                            <?php endforeach; ?>
                            <p id="audit-empty-state" class="hidden py-6 text-center text-sm text-slate-500">No activity for this period.</p>
                        </div>
                        <p class="mt-4 text-xs text-slate-500">Sample audit entries. Connect the audit log service for real account activity.</p>
                    </article>
                </section>

                <footer class="pb-2 text-center text-xs text-slate-500">FleetFlow Head Admin Dashboard · Sample figures and activity</footer>
            </div>
        </main>
    </div>

    <div id="users-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="users-modal-title">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl sm:p-7">
            <div class="flex items-start justify-between gap-4"><div><h2 id="users-modal-title" class="text-lg font-bold">Add a system user</h2><p class="mt-1 text-sm text-slate-500">Create an account request for an organization member.</p></div><button type="button" data-close-modal aria-label="Close dialog" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">✕</button></div>
            <form id="add-user-form" class="mt-6 space-y-4">
                <label class="block"><span class="mb-1.5 block text-sm font-medium text-slate-700">Full name</span><input name="name" required autocomplete="name" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"></label>
                <label class="block"><span class="mb-1.5 block text-sm font-medium text-slate-700">Email address</span><input name="email" type="email" required autocomplete="email" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"></label>
                <label class="block"><span class="mb-1.5 block text-sm font-medium text-slate-700">Role</span><select name="role" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"><option value="Admin">Admin</option><option value="Manager">Manager</option><option value="Accounting">Accounting</option><option value="User">User</option></select></label>
                <p class="rounded-lg bg-amber-50 p-3 text-xs leading-5 text-amber-800">Demo only: this form does not create an account until connected to your user database and authentication flow.</p>
                <div class="flex justify-end gap-3 pt-1"><button type="button" data-close-modal class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button><button type="submit" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Submit request</button></div>
            </form>
        </div>
    </div>

    <div id="approvals-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="approvals-modal-title">
        <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl sm:p-7">
            <div class="flex items-start justify-between gap-4"><div><h2 id="approvals-modal-title" class="text-lg font-bold">High-cost repair requests</h2><p class="mt-1 text-sm text-slate-500">Four sample requests awaiting review · ₱248,000 total</p></div><button type="button" data-close-modal aria-label="Close dialog" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">✕</button></div>
            <div class="mt-5 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4"><div><p class="text-sm font-semibold">Engine overhaul · DEF 1760</p><p class="mt-1 text-xs text-slate-500">Requested budget: ₱98,000</p></div><button type="button" data-approve class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-800">Approve</button></div>
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4"><div><p class="text-sm font-semibold">Transmission repair · JKL 3091</p><p class="mt-1 text-xs text-slate-500">Requested budget: ₱72,000</p></div><button type="button" data-approve class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-800">Approve</button></div>
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4"><div><p class="text-sm font-semibold">Body repair · GHI 2251</p><p class="mt-1 text-xs text-slate-500">Requested budget: ₱48,000</p></div><button type="button" data-approve class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-800">Approve</button></div>
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4"><div><p class="text-sm font-semibold">Brake replacement · PQR 1106</p><p class="mt-1 text-xs text-slate-500">Requested budget: ₱30,000</p></div><button type="button" data-approve class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-800">Approve</button></div>
            </div>
            <p class="mt-4 rounded-lg bg-amber-50 p-3 text-xs leading-5 text-amber-800">Demo only: approval clicks show a confirmation but do not change a real request.</p>
        </div>
    </div>

    <div id="expenses-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="expenses-modal-title">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl sm:p-7">
            <div class="flex items-start justify-between gap-4"><div><h2 id="expenses-modal-title" class="text-lg font-bold">Monthly expenditure</h2><p class="mt-1 text-sm text-slate-500">Sample breakdown for this month</p></div><button type="button" data-close-modal aria-label="Close dialog" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">✕</button></div>
            <div class="mt-6 space-y-4"><div class="flex justify-between border-b border-slate-100 pb-4 text-sm"><span class="text-slate-600">Fuel</span><strong>₱184,500</strong></div><div class="flex justify-between border-b border-slate-100 pb-4 text-sm"><span class="text-slate-600">Maintenance</span><strong>₱96,200</strong></div><div class="flex justify-between text-sm"><span class="font-semibold">Total</span><strong>₱280,700</strong></div></div>
            <p class="mt-5 rounded-lg bg-amber-50 p-3 text-xs leading-5 text-amber-800">Demo figures only. Connect accounting records for actual expenditure.</p>
        </div>
    </div>

    <div id="audit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="audit-modal-title">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl sm:p-7">
            <div class="flex items-start justify-between gap-4"><div><h2 id="audit-modal-title" class="text-lg font-bold">System audit logs</h2><p class="mt-1 text-sm text-slate-500">Recent account and fleet changes are shown on the dashboard.</p></div><button type="button" data-close-modal aria-label="Close dialog" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">✕</button></div>
            <p class="mt-5 rounded-lg bg-blue-50 p-4 text-sm leading-6 text-blue-900">Use the Recent activity section to filter and review sample entries for today, this week, or this month. A real audit trail requires backend event logging.</p>
            <button type="button" data-close-modal class="mt-5 w-full rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Got it</button>
        </div>
    </div>

    <div id="toast" class="pointer-events-none fixed bottom-4 right-4 z-[60] hidden max-w-sm rounded-xl bg-slate-900 px-4 py-3 text-sm font-medium text-white shadow-lg sm:bottom-6 sm:right-6" role="status" aria-live="polite"></div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarBackdrop = document.getElementById('sidebar-backdrop');
        const mobileSidebarOpen = document.getElementById('mobile-sidebar-open');
        const mobileSidebarClose = document.getElementById('mobile-sidebar-close');
        const desktopSidebarToggle = document.getElementById('desktop-sidebar-toggle');

        function setMobileSidebar(isOpen) {
            sidebar.classList.toggle('-translate-x-full', !isOpen);
            sidebarBackdrop.classList.toggle('hidden', !isOpen);
            document.body.classList.toggle('overflow-hidden', isOpen);
        }

        mobileSidebarOpen.addEventListener('click', () => setMobileSidebar(true));
        mobileSidebarClose.addEventListener('click', () => setMobileSidebar(false));
        sidebarBackdrop.addEventListener('click', () => setMobileSidebar(false));

        desktopSidebarToggle.addEventListener('click', () => {
            const isCollapsed = sidebar.classList.toggle('lg:w-20');
            sidebar.classList.toggle('lg:w-64', !isCollapsed);
            sidebar.querySelectorAll('.sidebar-copy').forEach((item) => item.classList.toggle('lg:hidden', isCollapsed));
            sidebar.querySelectorAll('.sidebar-link').forEach((item) => item.classList.toggle('lg:justify-center', isCollapsed));
            sidebar.querySelector('.sidebar-profile').classList.toggle('lg:justify-center', isCollapsed);
            desktopSidebarToggle.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
        });

        let toastTimer;
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.remove('hidden');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => toast.classList.add('hidden'), 3200);
        }

        let lastFocusedElement;
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            lastFocusedElement = document.activeElement;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            modal.querySelector('input, select, button')?.focus();
        }

        function closeModal(modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            lastFocusedElement?.focus();
        }

        document.querySelectorAll('[data-open-modal]').forEach((button) => {
            button.addEventListener('click', () => openModal(button.dataset.openModal));
        });
        document.querySelectorAll('[data-close-modal]').forEach((button) => {
            button.addEventListener('click', () => closeModal(button.closest('[role="dialog"]')));
        });
        document.querySelectorAll('[role="dialog"]').forEach((modal) => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal) closeModal(modal);
            });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                const openDialog = document.querySelector('[role="dialog"]:not(.hidden)');
                if (openDialog) closeModal(openDialog);
                setMobileSidebar(false);
            }
        });

        document.getElementById('add-user-form').addEventListener('submit', (event) => {
            event.preventDefault();
            const name = new FormData(event.currentTarget).get('name').toString().trim();
            closeModal(document.getElementById('users-modal'));
            event.currentTarget.reset();
            showToast(`Demo request submitted for ${name}. No account was created.`);
        });

        document.querySelectorAll('[data-approve]').forEach((button) => {
            button.addEventListener('click', () => {
                button.disabled = true;
                button.textContent = 'Approved (demo)';
                button.classList.replace('bg-blue-700', 'bg-emerald-600');
                button.classList.replace('hover:bg-blue-800', 'hover:bg-emerald-700');
                showToast('Demo approval recorded in this page only.');
            });
        });

        document.getElementById('notifications-button').addEventListener('click', () => {
            showToast('You have 4 sample budget requests awaiting review.');
        });

        const auditPeriod = document.getElementById('audit-period');
        const auditItems = Array.from(document.querySelectorAll('.audit-log-item'));
        const auditEmptyState = document.getElementById('audit-empty-state');
        auditPeriod.addEventListener('change', () => {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const daysByPeriod = { today: 0, week: 7, month: 30 };
            const cutoff = new Date(today);
            cutoff.setDate(today.getDate() - daysByPeriod[auditPeriod.value]);
            let visibleCount = 0;

            auditItems.forEach((item) => {
                const logDate = new Date(`${item.dataset.logDate}T00:00:00`);
                const isVisible = logDate >= cutoff && logDate <= today;
                item.classList.toggle('hidden', !isVisible);
                if (isVisible) visibleCount += 1;
            });

            auditEmptyState.classList.toggle('hidden', visibleCount > 0);
        });

        document.getElementById('export-report').addEventListener('click', () => {
            const rows = [
                ['Metric', 'Value'],
                ['Total fleet size', '51'],
                ['Active vehicles', '44'],
                ['Inactive vehicles', '7'],
                ['Active system users', '48 of 52'],
                ['Monthly fuel expenditure (PHP)', '184500'],
                ['Monthly maintenance expenditure (PHP)', '96200'],
                ['Pending high-cost approvals', '4'],
                ['Pending approval amount (PHP)', '248000'],
            ];
            const csv = rows.map((row) => row.map((value) => `"${value.replaceAll('"', '""')}"`).join(',')).join('\r\n');
            const downloadUrl = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = 'fleet-report-sample.csv';
            link.click();
            URL.revokeObjectURL(downloadUrl);
            showToast('Sample fleet report exported as CSV.');
        });
    </script>
</body>
</html>

<?php
declare(strict_types=1);

$navItems = [
    'dashboard' => ['Overview', 'dashboard.php'],
    'fleet_overview' => ['Fleet operations', 'fleet_overview.php'],
    'vehicles' => ['Vehicle directory', 'vehicles.php'],
    'emergency_overrides' => ['Emergency overrides', 'emergency_overrides.php'],
    'users' => ['User management', 'users.php'],
    'account_requests' => ['Account requests', 'account_requests.php'],
    'access_requests' => ['Access approvals', 'access_requests.php'],
    'audit_logs' => ['Audit history', 'audit_logs.php'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($pageTitle) ?> | Fleet Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../shared-ui.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="min-h-screen lg:flex">
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:static lg:translate-x-0">
            <a href="<?= $escape(headAdminUrl('dashboard.php')) ?>" class="flex h-20 items-center border-b border-slate-100 px-6">
                <img src="../../assets/paragon.webp" alt="Paragon Communications Corp." class="h-auto w-40 object-contain">
            </a>
            <nav class="space-y-1 px-4 py-6 text-sm" aria-label="Head Admin navigation">
                <?php foreach ($navItems as $key => [$label, $page]): ?>
                    <a href="<?= $escape(headAdminUrl($page)) ?>" <?= $activePage === $key ? 'aria-current="page"' : '' ?> class="flex items-center justify-between rounded-lg px-3 py-2.5 <?= $activePage === $key ? 'bg-blue-50 font-semibold text-blue-800' : 'text-slate-600 transition hover:bg-slate-100' ?>">
                        <span><?= $escape($label) ?></span>
                        <?php if ($key === 'account_requests' && $pendingAccountSignupCount > 0): ?>
                            <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800"><?= (int) $pendingAccountSignupCount ?></span>
                        <?php endif; ?>
                        <?php if ($key === 'access_requests' && $pendingCount > 0): ?>
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800"><?= (int) $pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="mt-auto border-t border-slate-100 p-4">
                <a href="../../profile.php" class="flex items-center gap-3 rounded-xl bg-slate-50 p-3 transition hover:bg-blue-50">
                    <?php if (!empty($headAdmin['profile_photo'])): ?>
                        <img class="h-10 w-10 rounded-full object-cover" src="../../profile-photo.php" alt="">
                    <?php else: ?>
                        <span class="grid h-10 w-10 place-items-center rounded-full bg-blue-100 font-bold text-blue-800"><?= $escape(strtoupper(substr((string) $headAdmin['name'], 0, 1))) ?></span>
                    <?php endif; ?>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold"><?= $escape($headAdmin['name']) ?></span>
                        <span class="block text-xs text-slate-500"><?= $previewMode ? 'Interactive preview' : 'Head Admin' ?></span>
                    </span>
                </a>
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
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Head Admin · System oversight</p>
                        <h1 class="text-lg font-bold text-slate-900 sm:text-xl"><?= $escape($pageTitle) ?></h1>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="hidden rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800 sm:inline-flex"><?= $previewMode ? 'Preview mode' : 'Head Admin' ?></span>
                    <?php if ($previewMode): ?>
                        <form method="post" class="confirm-action" data-confirm="Restore the original sample records and discard this preview session’s changes?">
                            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                            <input type="hidden" name="action" value="reset_preview">
                            <button type="submit" class="text-sm font-semibold text-slate-600 hover:text-blue-700">Reset preview</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="../../logout.php">
                            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                            <button type="submit" class="text-sm font-semibold text-slate-600 transition hover:text-blue-700">Sign out</button>
                        </form>
                    <?php endif; ?>
                </div>
            </header>
            <div class="dashboard-content mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <?php if ($previewMode): ?>
                    <div role="status" class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                        Interactive preview: changes affect temporary sample data in this browser session only.
                    </div>
                <?php endif; ?>
                <?php if ($flashMessage !== ''): ?>
                    <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"><?= $escape($flashMessage) ?></div>
                <?php endif; ?>

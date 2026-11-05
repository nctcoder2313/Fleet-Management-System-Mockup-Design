<?php
declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$navItems = $previewMode
    ? [
        'dashboard' => ['Overview', 'dashboard.php'],
        'maintenance' => ['Maintenance & service logs', 'maintenance.php'],
        'geofencing' => ['Geofencing & Speed Setup', 'geofencing.php'],
        'vendors_equipment' => ['Vendors & Equipment', 'vendors_equipment.php'],
    ]
    : [
        'dashboard' => ['Overview', 'dashboard.php'],
        'compliance' => ['Document Compliance', 'compliance.php'],
        'maintenance' => ['Maintenance & service logs', 'maintenance.php'],
        'geofencing' => ['Geofencing & Speed Setup', 'geofencing.php'],
        'vendors_equipment' => ['Vendors & Equipment', 'vendors_equipment.php'],
    ];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($pageTitle) ?> | Fleet Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="min-h-screen lg:flex">
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:static lg:translate-x-0">
            <a href="<?= $escape($adminPageUrl('dashboard.php')) ?>" class="flex h-20 items-center gap-3 border-b border-slate-100 px-6">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-700 text-sm font-bold text-white">FM</span>
                <span>
                    <span class="block font-bold text-slate-900">Fleet Management</span>
                    <span class="block text-xs text-slate-500">Admin workspace</span>
                </span>
            </a>
            <nav class="space-y-1 px-4 py-6 text-sm" aria-label="Admin navigation">
                <?php foreach ($navItems as $key => [$label, $page]): ?>
                    <a href="<?= $escape($adminPageUrl($page)) ?>" <?= $activePage === $key ? 'aria-current="page"' : '' ?> class="flex items-center rounded-lg px-3 py-2.5 <?= $activePage === $key ? 'bg-blue-50 font-semibold text-blue-800' : 'text-slate-600 transition hover:bg-slate-100' ?>">
                        <?= $escape($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="mt-auto border-t border-slate-100 p-4">
                <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-blue-100 font-bold text-blue-800"><?= $escape(strtoupper(substr((string) $admin['name'], 0, 1))) ?></span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold"><?= $escape($admin['name']) ?></span>
                        <span class="block text-xs text-slate-500">Admin</span>
                    </span>
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
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Admin workspace</p>
                        <h1 class="text-lg font-bold text-slate-900 sm:text-xl"><?= $escape($pageTitle) ?></h1>
                    </div>
                </div>
                <?php if ($previewMode): ?>
                    <span class="rounded-full bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-900">Local preview</span>
                <?php else: ?>
                    <form method="post" action="../../logout.php">
                        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                        <button type="submit" class="text-sm font-semibold text-slate-600 transition hover:text-blue-700">Sign out</button>
                    </form>
                <?php endif; ?>
            </header>
            <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
                <?php if ($previewMode): ?>
                    <div role="status" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                        Local preview only. Changes are temporary to this browser session and are not saved to the company database.
                    </div>
                <?php endif; ?>
                <?php if (isset($formError) && $formError !== ''): ?>
                    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900"><?= $escape($formError) ?></div>
                <?php endif; ?>
                <?php if ($flashMessage !== ''): ?>
                    <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"><?= $escape($flashMessage) ?></div>
                <?php endif; ?>

<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/rbac.php';
startSecureSession();
$manager = requireRole($pdo, 'manager');
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manager Dashboard | Fleet Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-blue-50 p-5 text-slate-800 sm:p-8">
    <main class="mx-auto max-w-5xl space-y-6">
        <?php if ($flashMessage !== ''): ?>
            <p role="status" class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-900"><?= $escape($flashMessage) ?></p>
        <?php endif; ?>
        <header class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <div>
                <p class="text-sm font-semibold text-blue-700">Fleet operations</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Welcome, <?= $escape($manager['name']) ?></h1>
                <p class="mt-2 text-sm text-slate-600">Manager dashboard</p>
            </div>
            <span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800">Manager</span>
        </header>
        <section class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Fleet monitoring</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Open the fleet monitoring view to review vehicle status, reported condition, fuel, and last known location. The current vehicle records are illustrative sample data, not live telemetry.</p>
            <a href="../head_admin/fleet.php" class="mt-5 inline-flex rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">Open fleet monitoring</a>
        </section>
        <form method="post" action="../../logout.php" class="text-right">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <button type="submit" class="text-sm font-semibold text-blue-700 hover:underline">Sign out</button>
        </form>
    </main>
</body>
</html>

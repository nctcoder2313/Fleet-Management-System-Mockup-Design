<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/rbac.php';
startSecureSession();
$driver = requireRole($pdo, 'driver');
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Driver Workspace | Fleet Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 p-4 text-slate-800 sm:p-8">
    <main class="mx-auto max-w-3xl space-y-5">
        <?php if ($flashMessage !== ''): ?>
            <p role="status" class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-900"><?= $escape($flashMessage) ?></p>
        <?php endif; ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-sm font-semibold text-blue-700">Fleet Management · Driver workspace</p>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Welcome, <?= $escape($driver['name']) ?></h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Your Driver account is active. Trip assignment and driver mobile workflows will be connected here when the trip-management module is introduced.</p>
        </section>
        <form method="post" action="../../logout.php" class="text-right">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <button type="submit" class="text-sm font-semibold text-blue-700 hover:underline">Sign out</button>
        </form>
    </main>
</body>
</html>

<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/rbac.php';
startSecureSession();
$admin = requireRole($pdo, 'admin');
$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | Fleet Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 p-6 text-slate-800 sm:p-10">
    <main class="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <?php if ($flashMessage !== ''): ?>
            <p role="status" class="mb-5 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-900"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <p class="text-sm font-semibold text-blue-700">Admin dashboard</p>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">Welcome, <?= htmlspecialchars($admin['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="mt-3 text-slate-600">This page is restricted to Admin accounts. Admin dashboard features can be added here.</p>
    </main>
    <form method="post" action="../../logout.php" class="mx-auto mt-4 max-w-3xl text-right">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="text-sm font-semibold text-blue-700 hover:underline">Sign out</button>
    </form>
</body>
</html>

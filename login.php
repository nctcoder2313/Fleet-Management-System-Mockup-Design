<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession();

if (authenticatedUser($pdo)) {
    $currentUser = authenticatedUser($pdo);
    header('Location: ' . dashboardPathForRole(effectiveRole($pdo, $currentUser)));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $selectedRole = $_POST['role'] ?? '';

    if (!is_string($email) || !is_string($password) || !is_string($selectedRole)) {
        http_response_code(400);
        exit('Invalid sign-in form data.');
    }

    $statement = $pdo->prepare(
        "SELECT id, password_hash
         FROM users
         WHERE email = :email AND status = 'active'
         LIMIT 1"
    );
    $statement->execute(['email' => trim($email)]);
    $account = $statement->fetch();

    if (!$account || !password_verify($password, $account['password_hash'])) {
        $error = 'Email or password is incorrect.';
    } elseif (!in_array($selectedRole, RBAC_ROLES, true)) {
        $error = 'Select a valid role.';
    } else {
        $login = completeLogin($pdo, (int) $account['id'], $selectedRole);
        header('Location: ' . $login['dashboard']);
        exit;
    }
}

$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$roles = [
    'head_admin' => 'Head Admin',
    'admin' => 'Admin',
    'manager' => 'Manager',
    'user' => 'User',
    'accounting' => 'Accounting',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | Fleet Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="grid min-h-screen place-items-center bg-gradient-to-br from-blue-100 via-sky-50 to-blue-200 p-4 text-slate-800">
    <main class="w-full max-w-md rounded-2xl border border-white/80 bg-white p-6 shadow-xl shadow-blue-900/10 sm:p-8">
        <div class="mb-7">
            <div class="grid h-12 w-12 place-items-center rounded-xl bg-blue-700 font-bold text-white">FM</div>
            <p class="mt-5 text-sm font-semibold text-blue-700">Fleet Management System</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Sign in</h1>
            <p class="mt-2 text-sm text-slate-500">Use your account credentials and assigned role.</p>
        </div>

        <?php if ($flashMessage !== ''): ?>
            <div role="alert" class="mb-5 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-900"><?= $escape($flashMessage) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div role="alert" class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"><?= $escape($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-slate-700">Email address</span>
                <input type="email" name="email" value="<?= $escape(is_string($_POST['email'] ?? null) ? $_POST['email'] : '') ?>" required autocomplete="username" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-slate-700">Password</span>
                <input type="password" name="password" required autocomplete="current-password" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-sm font-medium text-slate-700">Role</span>
                <select name="role" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <?php foreach ($roles as $value => $label): ?>
                        <option value="<?= $escape($value) ?>" <?= ($_POST['role'] ?? 'head_admin') === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="mt-1.5 block text-xs text-slate-500">Choosing a role not assigned to your account sends an access request; it does not switch your permissions.</span>
            </label>
            <button type="submit" class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Sign in</button>
        </form>
    </main>
</body>
</html>

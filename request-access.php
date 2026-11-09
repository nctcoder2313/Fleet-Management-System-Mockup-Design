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
$notice = $_SESSION['access_request_notice'] ?? '';
unset($_SESSION['access_request_notice']);
$roles = [
    'accounting' => 'Accounting',
    'manager' => 'Manager',
    'admin' => 'Admin',
];
$name = '';
$email = '';
$requestedRole = '';
$reason = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
    $email = strtolower(trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : ''));
    $requestedRole = $_POST['role'] ?? '';
    $reason = trim(is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $passwordConfirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';

    if ($name === '' || strlen($name) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        || !is_string($requestedRole) || !array_key_exists($requestedRole, $roles)
        || strlen($reason) > 1000
    ) {
        $error = 'Check your name, email, requested role, and reason (up to 1,000 characters).';
    } elseif (strlen($password) < 12 || strlen($password) > 72) {
        $error = 'Choose a password with at least 12 characters (maximum 72 bytes).';
    } elseif ($password !== $passwordConfirmation) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $existingUser = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $existingUser->execute(['email' => $email]);
            if ($existingUser->fetchColumn()) {
                $error = 'An account already uses this email. Please contact the Head Admin.';
            } else {
                $pendingRequest = $pdo->prepare(
                    "SELECT id FROM account_signup_requests WHERE email = :email AND status = 'PENDING' LIMIT 1"
                );
                $pendingRequest->execute(['email' => $email]);
                if ($pendingRequest->fetchColumn()) {
                    $error = 'A request for this email is already awaiting review.';
                } else {
                    $insert = $pdo->prepare(
                        "INSERT INTO account_signup_requests
                            (name, email, requested_role, reason, password_hash)
                         VALUES (:name, :email, :role, :reason, :password_hash)"
                    );
                    $insert->execute([
                        'name' => $name,
                        'email' => $email,
                        'role' => $requestedRole,
                        'reason' => $reason === '' ? null : $reason,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ]);
                    $_SESSION['access_request_notice'] = 'Your account request was sent to the Head Admin for review.';
                    header('Location: request-access.php');
                    exit;
                }
            }
        } catch (PDOException $exception) {
            error_log('Fleet account signup request error: ' . $exception->getMessage());
            $error = 'We could not submit your request. If this is the first setup, ask the Head Admin to import database/migrations/20261106_account_signup_requests.sql.';
        }
    }
}

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#dbeafe">
    <title>Request access | Paragon Fleet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, sans-serif; }
        body { margin: 0; min-width: 320px; min-height: 100vh; background: #dbeafe; }
        .form-input:focus { border-color: #3478f6; box-shadow: 0 0 0 4px rgba(52, 120, 246, .12); outline: none; }
        @media (prefers-reduced-motion: no-preference) {
            .request-card { animation: request-enter .4s ease-out both; }
            .request-action { transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease; }
            .request-action:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(29, 78, 216, .18); }
            @keyframes request-enter { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#dbeafe] via-[#eaf4ff] to-[#cfe4ff] px-4 py-8 text-slate-900">
    <main class="mx-auto max-w-2xl">
        <a href="login.php" class="inline-flex items-center rounded-xl bg-white/80 px-4 py-2 text-sm font-semibold text-blue-800 shadow-sm transition hover:bg-white">← Back to login</a>
        <section class="request-card mt-5 rounded-2xl border border-white/80 bg-white p-6 shadow-xl shadow-blue-900/10 sm:p-9">
            <img class="mb-5 h-auto w-48" src="assets/paragon.webp" alt="Paragon Communications Corp.">
            <p class="text-sm font-semibold text-blue-700">Fleet Management and Monitoring System</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">Request an account</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">Your request and password are sent securely to the Head Admin. You can sign in after the account is approved.</p>

            <?php if ($notice !== ''): ?>
                <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status"><?= $escape($notice) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><?= $escape($error) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-6 grid gap-4 sm:grid-cols-2">
                <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-slate-700">Full name</span>
                    <input class="form-input w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" name="name" maxlength="150" value="<?= $escape($name) ?>" autocomplete="name" required>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-slate-700">Work email</span>
                    <input class="form-input w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" name="email" type="email" maxlength="254" value="<?= $escape($email) ?>" autocomplete="email" required>
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-slate-700">Requested role</span>
                    <select class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm" name="role" required>
                        <option value="">Select a role</option>
                        <?php foreach ($roles as $value => $label): ?>
                            <option value="<?= $escape($value) ?>" <?= $requestedRole === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-slate-700">Reason for access <span class="font-normal text-slate-500">(optional)</span></span>
                    <textarea class="form-input w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" name="reason" maxlength="1000" rows="3"><?= $escape($reason) ?></textarea>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-slate-700">Create password</span>
                    <input class="form-input w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-slate-700">Confirm password</span>
                    <input class="form-input w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" name="password_confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
                </label>
                <p class="text-xs leading-5 text-slate-500 sm:col-span-2">Use at least 12 characters. Your password is stored as a secure hash and your account is created only after approval.</p>
                <button class="request-action rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white focus:outline-none focus:ring-4 focus:ring-blue-200 sm:col-span-2" type="submit">Send account request</button>
            </form>
        </section>
    </main>
</body>
</html>

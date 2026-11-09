<?php
declare(strict_types=1);

require_once __DIR__ . '/database/connection.php';

session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_SESSION['access_request_notice'])
    && is_string($_SESSION['access_request_notice'])) {
    $notice = $_SESSION['access_request_notice'];
    unset($_SESSION['access_request_notice']);
}
$name = '';
$email = '';
$role = '';
$reason = '';
$password = '';
$passwordConfirmation = '';
$roles = ['accounting', 'manager', 'admin', 'head_admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(400);
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
        $role = (string) ($_POST['role'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        if ($name === '' || strlen($name) > 120 || $email === '' || strlen($email) > 254
            || !in_array($role, $roles, true) || strlen($reason) > 1000) {
            $error = 'Check your details and try again. Reason for access must be 1,000 characters or fewer.';
        } elseif (strlen($password) < 8 || strlen($password) > 72) {
            $error = 'Your password must be at least 8 characters and no more than 72 bytes.';
        } elseif ($password !== $passwordConfirmation) {
            $error = 'The passwords do not match.';
        } else {
            try {
                $pdo = databaseConnection();
                $existingUser = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $existingUser->execute(['email' => $email]);

                if ($existingUser->fetch()) {
                    $error = 'An account already exists for this email. Please contact your administrator.';
                } else {
                    $pendingRequest = $pdo->prepare(
                        "SELECT id FROM access_requests WHERE email = :email AND status = 'pending' LIMIT 1"
                    );
                    $pendingRequest->execute(['email' => $email]);

                    if ($pendingRequest->fetch()) {
                        $error = 'A request for this email is already pending review.';
                    } else {
                        $insert = $pdo->prepare(
                            'INSERT INTO access_requests (name, email, requested_role, reason, password_hash) '
                            . 'VALUES (:name, :email, :role, :reason, :password_hash)'
                        );
                        $insert->execute([
                            'name' => $name,
                            'email' => $email,
                            'role' => $role,
                            'reason' => $reason === '' ? null : $reason,
                            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        ]);
                        $_SESSION['access_request_notice'] = 'Your request has been submitted successfully.';
                        $name = $email = $role = $reason = $password = $passwordConfirmation = '';
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        header('Location: request-access.php');
                        exit;
                    }
                }
            } catch (PDOException $exception) {
                error_log('Fleet management access request database error: ' . $exception->getMessage());
                $error = 'We could not save your request. Make sure MySQL is running and the fleetwise database is set up. If this is an existing database, run database/migrate-add-request-password.sql in phpMyAdmin.';
            }
        }
    }
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
        :root {
            color-scheme: light;
            font-family: ui-sans-serif, system-ui, sans-serif;
        }

        body {
            margin: 0;
            min-width: 320px;
            min-height: 100vh;
            background: #dbeafe;
        }

        .form-input:focus {
            border-color: #3478f6;
            box-shadow: 0 0 0 4px rgba(52, 120, 246, .12);
            outline: none;
        }

        .gradient-surface {
            position: relative;
            isolation: isolate;
            overflow: hidden;
        }

        .gradient-surface::before {
            content: "";
            position: absolute;
            inset: -25%;
            z-index: 0;
            background: linear-gradient(112deg, transparent 12%, rgba(59, 130, 246, .1) 38%, transparent 62%, rgba(96, 165, 250, .14) 88%, transparent);
            filter: blur(48px);
            pointer-events: none;
        }

        .gradient-surface > * {
            position: relative;
            z-index: 1;
        }

        @media (min-width: 768px) and (min-height: 640px) {
            body > main {
                height: 100vh;
                height: 100dvh;
                min-height: 0;
            }
        }

    </style>
</head>
<body class="font-sans text-slate-900 antialiased">
    <main class="gradient-surface relative z-10 flex min-h-screen items-center justify-center bg-gradient-to-br from-[#dbeafe] via-[#eaf4ff] to-[#cfe4ff] px-4 py-20 sm:px-6 sm:py-16 md:py-4">
        <section class="w-full max-w-[480px] rounded-2xl border border-blue-100 bg-white p-5 shadow-[0_20px_60px_rgba(30,64,175,0.16)] sm:p-6">
            <a class="mb-3 flex justify-center" href="login.php" aria-label="Paragon Fleet home">
                <img class="h-auto w-36" src="assets/paragon.webp" alt="Paragon Communications Corp.">
            </a>
            <div class="mb-4 text-center">
                <p class="mb-1 text-lg font-semibold text-blue-700">Fleet Management and Monitoring System</p>
                <h2 class="text-xl font-bold tracking-tight text-slate-900">Request an account</h2>
                <p class="mt-2 text-xs leading-5 text-slate-600">
                        Tell us about your access needs. An administrator will review your request.
                </p>
            </div>

            <?php if ($error !== ''): ?>
                <p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs leading-5 text-red-800" role="alert">
                    <?= $escape($error) ?>
                </p>
            <?php endif; ?>

            <?php if ($notice !== ''): ?>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-center" role="status">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="m5 12.5 4.5 4.5L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-emerald-900"><?= $escape($notice) ?></h3>
                    <p class="mt-2 text-xs leading-5 text-emerald-800">
                        Your request is pending review. You can log in once it has been approved and your account is activated.
                    </p>
                    <a class="mt-4 inline-flex w-full justify-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200" href="login.php">
                            Back to log in
                    </a>
                </div>
            <?php else: ?>
                <form id="request-form" class="space-y-3" action="request-access.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="request-name">Full name</label>
                        <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-name" name="name" type="text" autocomplete="name" placeholder="Your full name" maxlength="120" value="<?= $escape($name) ?>" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="request-email">Email</label>
                        <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-email" name="email" type="email" autocomplete="email" placeholder="you@company.com" maxlength="254" value="<?= $escape($email) ?>" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="request-password">Create password</label>
                        <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="At least 8 characters" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="request-password-confirmation">Confirm password</label>
                        <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Enter your password again" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="requested-role">Requested role</label>
                        <select class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900" id="requested-role" name="role" required>
                            <option value="" disabled <?= $role === '' ? 'selected' : '' ?>>Select a role</option>
                            <option value="accounting" <?= $role === 'accounting' ? 'selected' : '' ?>>Accounting</option>
                            <option value="manager" <?= $role === 'manager' ? 'selected' : '' ?>>Manager</option>
                            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="head_admin" <?= $role === 'head_admin' ? 'selected' : '' ?>>Head Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="request-reason">Reason for access <span class="font-normal text-slate-500">(optional)</span></label>
                        <textarea class="form-input w-full resize-y rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-reason" name="reason" rows="2" maxlength="1000" placeholder="Briefly describe your work requirements"><?= $escape($reason) ?></textarea>
                    </div>

                    <p class="text-xs leading-5 text-slate-600">
                        Your password is stored securely and your account will be created after approval.
                    </p>
                    <p class="text-xs leading-5 text-slate-600">
                        Head Admin access requires approval from an existing Head Admin.
                    </p>
                    <button class="w-full rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200" type="submit">
                        Submit request
                    </button>
                </form>

                <p class="mt-4 border-t border-slate-100 pt-3 text-center">
                    <a class="text-xs font-medium text-blue-700 hover:text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2" href="login.php">
                    Back to log in
                    </a>
                </p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
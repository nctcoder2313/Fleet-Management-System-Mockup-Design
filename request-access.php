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

$fname = '';
$mname = '';
$lname = '';
$officialEmail = '';
$personalEmail = '';
$role = '';
$reason = '';
$password = '';
$passwordConfirmation = '';
$allowedRoles = ['accounting', 'manager', 'admin', 'head-admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(400);
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $fname = trim((string) ($_POST['fname'] ?? ''));
        $mname = trim((string) ($_POST['mname'] ?? ''));
        $lname = trim((string) ($_POST['lname'] ?? ''));
        $officialEmail = strtolower(trim((string) ($_POST['official_email'] ?? '')));
        $personalEmail = strtolower(trim((string) ($_POST['personal_email'] ?? '')));
        $rawRole = strtolower(trim((string) ($_POST['role'] ?? '')));
        $role = $rawRole === 'head_admin' ? 'head-admin' : $rawRole;
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        // Matches validation in fleet_backend/auth/request_account.php
        if ($fname === '' || $lname === '' || $officialEmail === '' || $personalEmail === '' || $password === '') {
            $error = 'Validation Error: First name, last name, official company email, personal recovery email, and password are required.';
        } elseif (!filter_var($officialEmail, FILTER_VALIDATE_EMAIL) || !filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Validation Error: Please provide valid email addresses for both official and personal emails.';
        } elseif ($officialEmail === $personalEmail) {
            $error = 'Validation Error: Official company email and personal recovery email must be different.';
        } elseif (strlen($password) < 8 || strlen($password) > 72) {
            $error = 'Validation Error: Password must be at least 8 characters long.';
        } elseif ($password !== $passwordConfirmation) {
            $error = 'The passwords do not match.';
        } elseif (!in_array($role, $allowedRoles, true)) {
            $error = 'Validation Error: Invalid role selected. Allowed roles: head-admin, admin, manager, accounting.';
        } else {
            try {
                $pdo = databaseConnection();

                $checkStmt = $pdo->prepare(
                    'SELECT official_email, personal_email FROM users WHERE official_email = :official OR personal_email = :personal LIMIT 1'
                );
                $checkStmt->execute([
                    ':official' => $officialEmail,
                    ':personal' => $personalEmail,
                ]);
                $existing = $checkStmt->fetch();

                if ($existing) {
                    if ($existing['official_email'] === $officialEmail) {
                        $error = 'Duplicate Error: This official company email is already registered or has a pending account request.';
                    } else {
                        $error = 'Duplicate Error: This personal recovery email is already associated with another account.';
                    }
                } else {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare('
                        INSERT INTO users (fname, mname, lname, official_email, personal_email, password_hash, role, is_active)
                        VALUES (:fname, :mname, :lname, :official_email, :personal_email, :password_hash, :role, 0)
                    ');
                    $stmt->execute([
                        ':fname'          => $fname,
                        ':mname'          => $mname !== '' ? $mname : null,
                        ':lname'          => $lname,
                        ':official_email' => $officialEmail,
                        ':personal_email' => $personalEmail,
                        ':password_hash'  => $passwordHash,
                        ':role'           => $role,
                    ]);

                    $_SESSION['access_request_notice'] = 'Account request submitted successfully. Please wait for Head Admin approval before logging in.';
                    $fname = $mname = $lname = $officialEmail = $personalEmail = $role = $reason = $password = $passwordConfirmation = '';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    header('Location: request-access.php');
                    exit;
                }
            } catch (PDOException $exception) {
                error_log('Fleet management access request database error: ' . $exception->getMessage());
                $error = 'Database Error: Make sure MySQL is running in XAMPP (' . $exception->getMessage() . ').';
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
    <script src="/fleet_backend/frontend_api_helper.js" onerror="window.FLEET_HELPER_FALLBACK=true"></script>
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
                min-height: 100vh;
                min-height: 100dvh;
            }
        }
    </style>
</head>
<body class="font-sans text-slate-900 antialiased">
    <main class="gradient-surface relative z-10 flex min-h-screen items-center justify-center bg-gradient-to-br from-[#dbeafe] via-[#eaf4ff] to-[#cfe4ff] px-4 py-12 sm:px-6 md:py-6">
        <section class="w-full max-w-[500px] rounded-2xl border border-blue-100 bg-white p-5 shadow-[0_20px_60px_rgba(30,64,175,0.16)] sm:p-6">
            <a class="mb-3 flex justify-center" href="login.php" aria-label="Paragon Fleet home">
                <img class="h-auto w-36" src="assets/paragon.webp" alt="Paragon Communications Corp.">
            </a>
            <div class="mb-4 text-center">
                <p class="mb-1 text-lg font-semibold text-blue-700">Fleet Management and Monitoring System</p>
                <h2 class="text-xl font-bold tracking-tight text-slate-900">Request an account</h2>
                <p class="mt-2 text-xs leading-5 text-slate-600">
                    Provide your official company email and a separate personal recovery email. A Head Admin will review your request.
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
                        Your account is saved in <code>fleet_management_db.users</code> with status <strong>Pending Approval</strong> (<code>is_active = 0</code>).
                    </p>
                    <a class="mt-4 inline-flex w-full justify-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200" href="login.php">
                        Back to log in
                    </a>
                </div>
            <?php else: ?>
                <form id="request-form" class="space-y-3" action="request-access.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">

                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700" for="request-fname">First name</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-fname" name="fname" type="text" placeholder="Juan" maxlength="100" value="<?= $escape($fname) ?>" required>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700" for="request-mname">Middle <span class="font-normal text-slate-400">(opt)</span></label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-mname" name="mname" type="text" placeholder="Santos" maxlength="100" value="<?= $escape($mname) ?>">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700" for="request-lname">Last name</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-lname" name="lname" type="text" placeholder="Dela Cruz" maxlength="100" value="<?= $escape($lname) ?>" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700" for="request-official-email">Official Company Email</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-official-email" name="official_email" type="email" placeholder="you@company.com" maxlength="150" value="<?= $escape($officialEmail) ?>" required>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700" for="request-personal-email">Personal Recovery Email</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-personal-email" name="personal_email" type="email" placeholder="you.personal@gmail.com" maxlength="150" value="<?= $escape($personalEmail) ?>" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700" for="request-password">Create password</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="At least 8 characters" required>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700" for="request-password-confirmation">Confirm password</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Repeat password" required>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700" for="requested-role">Requested role</label>
                        <select class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900" id="requested-role" name="role" required>
                            <option value="" disabled <?= $role === '' ? 'selected' : '' ?>>Select a role</option>
                            <option value="accounting" <?= $role === 'accounting' ? 'selected' : '' ?>>Accounting</option>
                            <option value="manager" <?= $role === 'manager' ? 'selected' : '' ?>>Manager</option>
                            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="head-admin" <?= ($role === 'head-admin' || $role === 'head_admin') ? 'selected' : '' ?>>Head Admin</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700" for="request-reason">Reason for access <span class="font-normal text-slate-500">(optional)</span></label>
                        <textarea class="form-input w-full resize-y rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-reason" name="reason" rows="2" maxlength="1000" placeholder="Briefly describe your work requirements"><?= $escape($reason) ?></textarea>
                    </div>

                    <p class="text-xs leading-5 text-slate-600">
                        Your official email is used for login and 2FA OTP; your personal email is used for password recovery.
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

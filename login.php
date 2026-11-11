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
$step = $_SESSION['auth_step'] ?? 'login'; // 'login' | 'otp' | 'forgot_request' | 'forgot_reset'
$pendingEmail = $_SESSION['pending_official_email'] ?? '';
$otpPreview = $_SESSION['otp_preview_for_testing'] ?? '';
$recoveryEmail = $_SESSION['pending_recovery_email'] ?? '';
$recoveryPreview = $_SESSION['recovery_code_preview_for_testing'] ?? '';

if (isset($_GET['view'])) {
    if ($_GET['view'] === 'forgot') {
        $step = 'forgot_request';
        $_SESSION['auth_step'] = 'forgot_request';
    } elseif ($_GET['view'] === 'login') {
        $step = 'login';
        unset(
            $_SESSION['auth_step'],
            $_SESSION['pending_official_email'],
            $_SESSION['otp_preview_for_testing'],
            $_SESSION['pending_recovery_email'],
            $_SESSION['recovery_code_preview_for_testing']
        );
    }
}

$signedIn = isset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role']);
if ($signedIn && ($_SESSION['user_role'] === '' || $_SESSION['user_role'] === null)) {
    try {
        $pdo = databaseConnection();
        $roleStmt = $pdo->prepare('SELECT role FROM users WHERE user_id = :id LIMIT 1');
        $roleStmt->execute([':id' => (int) $_SESSION['user_id']]);
        $freshRole = $roleStmt->fetchColumn();
        if (is_string($freshRole) && $freshRole !== '') {
            $_SESSION['user_role'] = $freshRole;
        }
    } catch (PDOException $ignored) {
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(400);
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (isset($_POST['logout'])) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: login.php');
        exit;
    } elseif (isset($_POST['cancel_flow'])) {
        unset(
            $_SESSION['auth_step'],
            $_SESSION['pending_official_email'],
            $_SESSION['otp_preview_for_testing'],
            $_SESSION['pending_recovery_email'],
            $_SESSION['recovery_code_preview_for_testing']
        );
        header('Location: login.php');
        exit;
    } else {
        $formAction = (string) ($_POST['form_action'] ?? 'login');

        try {
            $pdo = databaseConnection();

            // =================================================================
            // 1. STEP 1 OF AUTHENTICATION: Official Email + Password -> OTP 2FA
            // Matches fleet_backend/auth/login.php
            // =================================================================
            if ($formAction === 'login') {
                $officialEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
                $password = (string) ($_POST['password'] ?? '');

                if ($officialEmail === '' || $password === '') {
                    $error = 'Validation Error: Official company email and password are required.';
                } else {
                    $stmt = $pdo->prepare('SELECT * FROM users WHERE official_email = :email LIMIT 1');
                    $stmt->execute([':email' => $officialEmail]);
                    $user = $stmt->fetch();

                    if (!$user || !password_verify($password, $user['password_hash'])) {
                        $error = 'Invalid official company email or password.';
                    } elseif ((int) $user['is_active'] === 0) {
                        $error = 'Your account is currently pending Head Admin approval or has been deactivated.';
                    } else {
                        $otp = (string) rand(100000, 999999);
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                        $updateOtp = $pdo->prepare(
                            'UPDATE users SET otp_code = :otp, otp_expires_at = :expires WHERE user_id = :id'
                        );
                        $updateOtp->execute([
                            ':otp'     => $otp,
                            ':expires' => $expiresAt,
                            ':id'      => $user['user_id'],
                        ]);

                        $_SESSION['auth_step'] = 'otp';
                        $_SESSION['pending_official_email'] = $user['official_email'];
                        $_SESSION['otp_preview_for_testing'] = $otp;

                        $step = 'otp';
                        $pendingEmail = $user['official_email'];
                        $otpPreview = $otp;
                        $notice = 'Credentials verified. A 6-digit OTP code has been generated for your official company email.';
                    }
                }
            }

            // =================================================================
            // 2. STEP 2 OF AUTHENTICATION: Verify 6-digit OTP Code
            // Matches fleet_backend/auth/verify_otp.php
            // =================================================================
            elseif ($formAction === 'verify_otp') {
                $officialEmail = strtolower(trim((string) ($_POST['official_email'] ?? $pendingEmail)));
                $otpCode = trim((string) ($_POST['otp_code'] ?? ''));

                if ($officialEmail === '' || $otpCode === '') {
                    $error = 'Validation Error: Official company email and 6-digit OTP code are required.';
                    $step = 'otp';
                } else {
                    $stmt = $pdo->prepare("
                        SELECT user_id, fname, mname, lname, official_email, personal_email, role, is_active, otp_code, otp_expires_at
                        FROM users
                        WHERE official_email = :email AND is_active = 1
                        LIMIT 1
                    ");
                    $stmt->execute([':email' => $officialEmail]);
                    $user = $stmt->fetch();

                    if (!$user || empty($user['otp_code']) || $user['otp_code'] !== $otpCode) {
                        $error = 'Invalid OTP verification code.';
                        $step = 'otp';
                    } elseif (strtotime((string) $user['otp_expires_at']) < time()) {
                        $error = 'OTP code has expired. Please log in again to generate a new code.';
                        unset($_SESSION['auth_step'], $_SESSION['pending_official_email'], $_SESSION['otp_preview_for_testing']);
                        $step = 'login';
                    } else {
                        $clearStmt = $pdo->prepare('UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE user_id = :id');
                        $clearStmt->execute([':id' => $user['user_id']]);

                        session_regenerate_id(true);
                        $fullName = trim($user['fname'] . ' ' . ($user['mname'] ? $user['mname'] . ' ' : '') . $user['lname']);
                        $_SESSION['user_id'] = (int) $user['user_id'];
                        $_SESSION['user_name'] = $fullName;
                        $_SESSION['fname'] = $user['fname'];
                        $_SESSION['mname'] = $user['mname'] ?? '';
                        $_SESSION['lname'] = $user['lname'];
                        $_SESSION['official_email'] = $user['official_email'];
                        $_SESSION['personal_email'] = $user['personal_email'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        unset($_SESSION['auth_step'], $_SESSION['pending_official_email'], $_SESSION['otp_preview_for_testing']);
                        $signedIn = true;
                        $notice = '2FA verification successful. Welcome back, ' . $user['fname'] . '!';
                    }
                }
            }

            // =================================================================
            // 3. FORGOT PASSWORD STEP 1: Request Recovery Code via Personal Email
            // Matches fleet_backend/auth/forgot_password.php (action = 'request_code')
            // =================================================================
            elseif ($formAction === 'forgot_request_code') {
                $personalEmail = strtolower(trim((string) ($_POST['personal_email'] ?? '')));

                if ($personalEmail === '') {
                    $error = 'Validation Error: Please enter your registered personal recovery email.';
                    $step = 'forgot_request';
                } else {
                    $stmt = $pdo->prepare('SELECT user_id, fname, official_email, personal_email FROM users WHERE personal_email = :email LIMIT 1');
                    $stmt->execute([':email' => $personalEmail]);
                    $user = $stmt->fetch();

                    if (!$user) {
                        $error = 'No account was found matching that personal recovery email.';
                        $step = 'forgot_request';
                    } else {
                        $recoveryCode = (string) rand(100000, 999999);
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                        $updStmt = $pdo->prepare('UPDATE users SET otp_code = :code, otp_expires_at = :expires WHERE user_id = :id');
                        $updStmt->execute([
                            ':code'    => $recoveryCode,
                            ':expires' => $expiresAt,
                            ':id'      => $user['user_id'],
                        ]);

                        $_SESSION['auth_step'] = 'forgot_reset';
                        $_SESSION['pending_recovery_email'] = $user['personal_email'];
                        $_SESSION['recovery_code_preview_for_testing'] = $recoveryCode;

                        $step = 'forgot_reset';
                        $recoveryEmail = $user['personal_email'];
                        $recoveryPreview = $recoveryCode;
                        $notice = 'Password recovery code generated for official account: ' . $user['official_email'];
                    }
                }
            }

            // =================================================================
            // 4. FORGOT PASSWORD STEP 2: Verify Recovery Code & Reset Password
            // Matches fleet_backend/auth/forgot_password.php (action = 'reset_password')
            // =================================================================
            elseif ($formAction === 'forgot_reset_password') {
                $personalEmail = strtolower(trim((string) ($_POST['personal_email'] ?? $recoveryEmail)));
                $recoveryCode = trim((string) ($_POST['recovery_code'] ?? ''));
                $newPassword = (string) ($_POST['new_password'] ?? '');

                if ($personalEmail === '' || $recoveryCode === '' || $newPassword === '') {
                    $error = 'Validation Error: Personal email, recovery code, and new password are required.';
                    $step = 'forgot_reset';
                } elseif (strlen($newPassword) < 8) {
                    $error = 'Validation Error: New password must be at least 8 characters long.';
                    $step = 'forgot_reset';
                } else {
                    $stmt = $pdo->prepare('SELECT user_id, official_email, otp_code, otp_expires_at FROM users WHERE personal_email = :email LIMIT 1');
                    $stmt->execute([':email' => $personalEmail]);
                    $user = $stmt->fetch();

                    if (!$user || empty($user['otp_code']) || $user['otp_code'] !== $recoveryCode) {
                        $error = 'Invalid password recovery code.';
                        $step = 'forgot_reset';
                    } elseif (strtotime((string) $user['otp_expires_at']) < time()) {
                        $error = 'Password recovery code has expired. Please request a new code.';
                        $step = 'forgot_request';
                    } else {
                        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $updStmt = $pdo->prepare('
                            UPDATE users
                            SET password_hash = :hash, otp_code = NULL, otp_expires_at = NULL
                            WHERE user_id = :id
                        ');
                        $updStmt->execute([
                            ':hash' => $newHash,
                            ':id'   => $user['user_id'],
                        ]);

                        unset($_SESSION['auth_step'], $_SESSION['pending_recovery_email'], $_SESSION['recovery_code_preview_for_testing']);
                        $step = 'login';
                        $notice = 'Password has been reset successfully for official account: ' . $user['official_email'] . '. You may now log in.';
                    }
                }
            }

            // =================================================================
            // 5. UPDATE PROFILE (When Signed In)
            // Matches fleet_backend/auth/auth_profile.php (action = 'update_profile')
            // =================================================================
            elseif ($formAction === 'update_profile' && $signedIn) {
                $fname = trim((string) ($_POST['fname'] ?? ''));
                $mname = trim((string) ($_POST['mname'] ?? ''));
                $lname = trim((string) ($_POST['lname'] ?? ''));
                $personalEmail = strtolower(trim((string) ($_POST['personal_email'] ?? '')));

                if ($fname === '' || $lname === '' || !filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Validation Error: First name, last name, and a valid personal recovery email are required.';
                } else {
                    $stmt = $pdo->prepare('
                        UPDATE users
                        SET fname = :fname, mname = :mname, lname = :lname, personal_email = :personal_email
                        WHERE user_id = :user_id
                    ');
                    $stmt->execute([
                        ':fname'          => $fname,
                        ':mname'          => $mname !== '' ? $mname : null,
                        ':lname'          => $lname,
                        ':personal_email' => $personalEmail,
                        ':user_id'        => (int) $_SESSION['user_id'],
                    ]);

                    $_SESSION['fname'] = $fname;
                    $_SESSION['mname'] = $mname;
                    $_SESSION['lname'] = $lname;
                    $_SESSION['personal_email'] = $personalEmail;
                    $_SESSION['user_name'] = trim($fname . ' ' . ($mname !== '' ? $mname . ' ' : '') . $lname);
                    $notice = 'Profile updated successfully.';
                }
            }
        } catch (PDOException $exception) {
            error_log('Fleet management auth database error: ' . $exception->getMessage());
            $error = 'Database Error: Make sure MySQL is running in XAMPP (' . $exception->getMessage() . ').';
        }
    }
}

$roleLabels = [
    'accounting' => 'Accounting',
    'manager' => 'Manager',
    'admin' => 'Admin',
    'head_admin' => 'Head Admin',
    'head-admin' => 'Head Admin',
];
$displayName = $signedIn ? (string) $_SESSION['user_name'] : '';
$displayRole = $signedIn
    ? ($roleLabels[$_SESSION['user_role']] ?? 'Team member')
    : '';
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#dbeafe">
    <title>Log in | Paragon Fleet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/fleet_backend/frontend_api_helper.js" onerror="window.FLEET_HELPER_FALLBACK=true"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        fleet: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3478f6',
                            600: '#2463eb',
                            700: '#1d4ed8',
                            900: '#172554'
                        }
                    },
                }
            }
        };
    </script>
    <style>
        :root {
            color-scheme: light;
            font-family: ui-sans-serif, system-ui, sans-serif;
        }

        body {
            margin: 0;
            min-width: 320px;
            min-height: 100vh;
            background: #f8fafc;
        }

        .hero-panel {
            background: #fff;
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

        @media (max-width: 767px) {
            .hero-panel {
                min-height: 470px;
            }
        }

        @media (min-width: 768px) and (min-height: 540px) {
            body {
                height: 100vh;
                height: 100dvh;
                overflow: hidden;
            }

            body > main {
                position: fixed;
                inset: 0;
                height: 100vh;
                height: 100dvh;
                min-height: 0;
            }

            body > main > section {
                height: 100%;
                min-height: 0;
            }

            body > main > .hero-panel,
            body > main > .gradient-surface {
                overflow-x: hidden;
                overflow-y: auto;
            }

            body > main > .gradient-surface::before {
                inset: 0;
            }

            .hero-panel .hero-vehicle {
                max-height: clamp(120px, calc(100dvh - 420px), 280px);
            }
        }
    </style>
</head>
<body class="font-sans text-slate-900 antialiased">
    <main class="min-h-screen bg-slate-50 md:grid md:grid-cols-[1.08fr_.92fr]">
        <section class="hero-panel relative flex min-h-[470px] flex-col overflow-hidden px-6 py-6 text-blue-900 sm:px-8 md:min-h-screen md:border-r md:border-slate-100 md:px-10 md:py-8 lg:px-14">
            <a class="relative z-10 flex w-fit flex-col items-start text-blue-800" href="login.php" aria-label="Paragon Fleet home">
                <img class="h-auto w-48 sm:w-56" src="assets/paragon.webp" alt="Paragon Communications Corp.">
            </a>

            <div class="relative z-10 mt-6 max-w-lg sm:mt-8">
                <h1 class="max-w-md text-2xl font-bold leading-tight tracking-tight text-slate-900 sm:text-3xl">
                    Your fleet,<br>always connected.
                </h1>
                <p class="mt-3 max-w-sm text-sm leading-6 text-slate-600">
                    Keep your vehicles, people, and operations moving together — all from one place.
                </p>
            </div>

            <div class="flex flex-1 items-center justify-center py-5">
                <img class="hero-vehicle max-h-[220px] w-full object-contain sm:max-h-[280px] md:max-h-[40vh] lg:max-h-[48vh]" src="assets/fleet%20pcc%20image.jpg" alt="Paragon fleet service vehicle">
            </div>

            <div class="relative z-10 mt-auto hidden pt-6 text-xs text-slate-600 md:block">
                <span>© <?= date('Y') ?> Paragon Communications Corp.</span>
                <span class="mx-2 text-blue-300">·</span>
                <span>Fleet operations, made clearer.</span>
            </div>
        </section>

        <section class="gradient-surface flex min-h-[calc(100vh-470px)] items-center justify-center bg-gradient-to-br from-[#dbeafe] via-[#eaf4ff] to-[#cfe4ff] px-6 py-8 sm:px-10 md:min-h-screen md:px-10 md:py-6 lg:px-14">
            <div class="w-full max-w-[460px] rounded-2xl border border-slate-200 bg-white p-7 shadow-[0_20px_60px_rgba(15,23,42,0.08)] sm:p-9">
                <div class="mb-6">
                    <p class="mb-1 text-lg font-semibold text-blue-700">Fleet Management and Monitoring System</p>
                    <h2 class="text-base font-bold tracking-tight text-slate-900">
                        <?php if ($signedIn): ?>
                            You’re signed in
                        <?php elseif ($step === 'otp'): ?>
                            Two-Factor Authentication (2FA)
                        <?php elseif ($step === 'forgot_request' || $step === 'forgot_reset'): ?>
                            Account Password Recovery
                        <?php else: ?>
                            Log in to your account
                        <?php endif; ?>
                    </h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        <?php if ($signedIn): ?>
                            Connected to <code>fleet_management_db</code> backend.
                        <?php elseif ($step === 'otp'): ?>
                            Enter the 6-digit OTP code generated for <strong><?= $escape($pendingEmail) ?></strong>.
                        <?php elseif ($step === 'forgot_request'): ?>
                            Enter your registered personal recovery email to receive a 6-digit reset code.
                        <?php elseif ($step === 'forgot_reset'): ?>
                            Enter the 6-digit recovery code sent to <strong><?= $escape($recoveryEmail) ?></strong> and your new password.
                        <?php else: ?>
                            Enter your official company email and password to continue.
                        <?php endif; ?>
                    </p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="mb-5 flex gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm0-11a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 7Zm0 7.25a.875.875 0 1 0 0-1.75.875.875 0 0 0 0 1.75Z" clip-rule="evenodd"/>
                        </svg>
                        <p><?= $escape($error) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($notice !== ''): ?>
                    <div class="mb-5 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs leading-5 text-emerald-900" role="status">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/>
                        </svg>
                        <p><?= $escape($notice) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($signedIn): ?>
                    <div class="rounded-2xl border border-blue-100 bg-white p-5 shadow-[0_12px_40px_rgba(30,64,175,0.07)]">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
                                    <path d="m6 12.5 4 4 8-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">
                                    Welcome, <?= $escape($displayName) ?>!
                                </p>
                                <p class="text-xs text-slate-600">
                                    Role: <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 font-medium text-blue-700"><?= $escape($displayRole) ?></span>
                                </p>
                            </div>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600 space-y-1">
                            <div><span class="font-medium text-slate-700">Official Email:</span> <?= $escape((string) ($_SESSION['official_email'] ?? '')) ?></div>
                            <div><span class="font-medium text-slate-700">Personal Email:</span> <?= $escape((string) ($_SESSION['personal_email'] ?? '')) ?></div>
                        </div>

                        <details class="mt-4 rounded-xl border border-slate-200 p-3">
                            <summary class="cursor-pointer text-xs font-semibold text-blue-700 hover:text-blue-800">
                                Update Profile (Connected to auth_profile.php)
                            </summary>
                            <form class="mt-3 space-y-2.5" method="post" action="login.php">
                                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="form_action" value="update_profile">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700" for="prof-fname">First name</label>
                                        <input class="form-input w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs" id="prof-fname" name="fname" type="text" value="<?= $escape((string) ($_SESSION['fname'] ?? '')) ?>" required>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-700" for="prof-mname">Middle name</label>
                                        <input class="form-input w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs" id="prof-mname" name="mname" type="text" value="<?= $escape((string) ($_SESSION['mname'] ?? '')) ?>">
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-700" for="prof-lname">Last name</label>
                                    <input class="form-input w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs" id="prof-lname" name="lname" type="text" value="<?= $escape((string) ($_SESSION['lname'] ?? '')) ?>" required>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-700" for="prof-pemail">Personal recovery email</label>
                                    <input class="form-input w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs" id="prof-pemail" name="personal_email" type="email" value="<?= $escape((string) ($_SESSION['personal_email'] ?? '')) ?>" required>
                                </div>
                                <button class="w-full rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-900" type="submit">
                                    Save profile changes
                                </button>
                            </form>
                        </details>

                        <form class="mt-5" method="post" action="login.php">
                            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                            <button class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200" type="submit" name="logout" value="1">
                                Sign out
                            </button>
                        </form>
                    </div>

                <?php elseif ($step === 'otp'): ?>
                    <?php if ($otpPreview !== ''): ?>
                        <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold">2FA OTP Preview (Testing Mode):</span>
                                <button type="button" onclick="document.getElementById('otp_code').value='<?= $escape($otpPreview) ?>'" class="rounded bg-blue-700 px-2 py-0.5 text-[11px] font-medium text-white hover:bg-blue-800">
                                    Auto-fill OTP
                                </button>
                            </div>
                            <p class="mt-1 font-mono text-base font-bold tracking-widest text-blue-700"><?= $escape($otpPreview) ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="login.php" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="form_action" value="verify_otp">
                        <input type="hidden" name="official_email" value="<?= $escape($pendingEmail) ?>">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="otp_code">6-Digit OTP Verification Code</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-center font-mono text-lg tracking-widest text-slate-900 placeholder:text-slate-400" id="otp_code" name="otp_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus>
                        </div>
                        <div class="pt-1 space-y-2">
                            <button class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200" type="submit">
                                Verify OTP &amp; Sign in
                            </button>
                            <a href="login.php?view=login" class="block w-full text-center text-xs font-medium text-slate-500 hover:text-slate-700 py-1">
                                Cancel and return to log in
                            </a>
                        </div>
                    </form>

                <?php elseif ($step === 'forgot_request'): ?>
                    <form method="post" action="login.php" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="form_action" value="forgot_request_code">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="personal_email">Personal Recovery Email</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" id="personal_email" name="personal_email" type="email" placeholder="your.personal@gmail.com" required>
                        </div>
                        <div class="pt-1 space-y-2">
                            <button class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200" type="submit">
                                Send 6-Digit Recovery Code
                            </button>
                            <a href="login.php?view=login" class="block w-full text-center text-xs font-medium text-slate-500 hover:text-slate-700 py-1">
                                Back to log in
                            </a>
                        </div>
                    </form>

                <?php elseif ($step === 'forgot_reset'): ?>
                    <?php if ($recoveryPreview !== ''): ?>
                        <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold">Recovery Code Preview (Testing Mode):</span>
                                <button type="button" onclick="document.getElementById('recovery_code').value='<?= $escape($recoveryPreview) ?>'" class="rounded bg-blue-700 px-2 py-0.5 text-[11px] font-medium text-white hover:bg-blue-800">
                                    Auto-fill Code
                                </button>
                            </div>
                            <p class="mt-1 font-mono text-base font-bold tracking-widest text-blue-700"><?= $escape($recoveryPreview) ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="login.php" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="form_action" value="forgot_reset_password">
                        <input type="hidden" name="personal_email" value="<?= $escape($recoveryEmail) ?>">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="recovery_code">6-Digit Recovery Code</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-center font-mono text-base tracking-widest text-slate-900" id="recovery_code" name="recovery_code" type="text" maxlength="6" placeholder="000000" required>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="new_password">New Password</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" id="new_password" name="new_password" type="password" minlength="8" placeholder="At least 8 characters" required>
                        </div>
                        <div class="pt-1 space-y-2">
                            <button class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200" type="submit">
                                Reset Password
                            </button>
                            <a href="login.php?view=login" class="block w-full text-center text-xs font-medium text-slate-500 hover:text-slate-700 py-1">
                                Back to log in
                            </a>
                        </div>
                    </form>

                <?php else: ?>
                    <form method="post" action="login.php" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="form_action" value="login">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="email">Official Company Email</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" id="email" name="email" type="email" autocomplete="username" placeholder="headadmin@company.com" required>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="password">Password</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                            <div class="mt-2 text-right">
                                <a href="login.php?view=forgot" class="text-xs font-medium text-blue-700 hover:text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                                    Forgot password?
                                </a>
                            </div>
                        </div>
                        <div class="pt-2">
                            <button class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200" type="submit">
                                Log in
                            </button>
                        </div>
                    </form>
                    <p class="mt-5 border-t border-slate-100 pt-4 text-center">
                        <a class="text-xs font-medium text-blue-700 hover:text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2" href="request-access.php">
                            Don’t have an account? Request access
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>

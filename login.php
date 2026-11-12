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

// Play the 2-second car intro and choreography whenever visiting the login view.
// Only skip intro if user is already signed in or during multi-step forms (OTP/forgot-password)
$skipIntro = $signedIn
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '')
    || in_array($step, ['otp', 'forgot_request', 'forgot_reset'], true);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#F8FAFA">
    <title>Log in | Paragon Fleet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preload" href="assets/video/white%20fleet-car.mp4" as="video" type="video/mp4">
    <link rel="preload" href="assets/video/white-fleet-car-poster.png" as="image" type="image/png">
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/top-loader.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/fleet_backend/frontend_api_helper.js" onerror="window.FLEET_HELPER_FALLBACK=true"></script>
    <script src="assets/top-loader.js"></script>
    <style>
        /* ---------- Page shell: Matched exactly to the video background tone (#F8FAFA) ---------- */
        .login-page {
            background: #F8FAFA;
            min-height: 100vh;
            min-height: 100dvh;
            overflow: hidden;
            position: relative;
        }

        .login-brand {
            position: fixed;
            top: 24px;
            left: 32px;
            z-index: 30;
            display: flex;
            align-items: center;
        }

        /* ---------- Clean Corporate Brand Card (Upper Left) ---------- */
        .brand-card {
            display: inline-flex;
            align-items: center;
            gap: 16px;
            padding: 9px 22px 9px 15px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.06), 0 1px 3px rgba(15, 23, 42, 0.04);
            text-decoration: none;
            transition: all 180ms ease;
        }

        .brand-card:hover {
            border-color: #93C5FD;
            box-shadow: 0 6px 20px -2px rgba(37, 99, 235, 0.12);
            transform: translateY(-1px);
        }

        .brand-logo {
            height: 36px;
            width: auto;
            max-width: 150px;
            object-fit: contain;
            display: block;
        }

        .brand-divider {
            width: 1.5px;
            height: 26px;
            background: #CBD5E1;
            border-radius: 9999px;
            flex-shrink: 0;
        }

        .brand-title {
            font-size: 1.125rem;
            font-weight: 700;
            line-height: 1.25;
            letter-spacing: -0.015em;
            color: #1E3A8A;
            margin: 0;
            white-space: nowrap;
        }

        .login-footer {
            position: fixed;
            left: 40px;
            bottom: 24px;
            z-index: 30;
        }

        .login-stage {
            position: relative;
            width: 100%;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
        }

        /* ---------- Video stage: starts centered at 0:00, then slowly glides to the left reaching final position at 0:03 ---------- */
        .video-wrap {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            z-index: 10;
            animation: fleet-car-glide 3.0s cubic-bezier(0.22, 1, 0.36, 1) 0.1s both;
        }

        @keyframes fleet-car-glide {
            0% {
                transform: translate3d(0, 0, 0);
            }
            15% {
                transform: translate3d(0, 0, 0);
            }
            100% {
                transform: translate3d(-15vw, 0, 0);
            }
        }

        .video-stage {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Video element & poster: feathered mask eliminates any rectangular bounding box */
        .video-media {
            width: min(1080px, 90vw);
            max-height: 72vh;
            object-fit: contain;
            background: transparent !important;
            -webkit-mask-image: radial-gradient(ellipse 90% 86% at 50% 50%, #000 60%, transparent 100%);
            mask-image: radial-gradient(ellipse 90% 86% at 50% 50%, #000 60%, transparent 100%);
        }

        /* Poster image: painted underneath at 0:00 as instant fallback */
        .video-poster-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        /* Video element: always 100% visible, sits on top of poster */
        .video-wrap video {
            position: relative;
            z-index: 2;
            opacity: 1 !important;
            display: block;
        }

        /* ---------- Floating blue card: WALA PA at 0:00, emerges from the right to be in place at 0:03 ---------- */
        .card-wrap {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 48%;
            max-width: 620px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 68px 48px 48px 16px;
            z-index: 20;
            animation: login-card-enter 3.0s cubic-bezier(0.22, 1, 0.36, 1) 0.1s both;
        }

        @keyframes login-card-enter {
            0% {
                opacity: 0;
                visibility: hidden;
                transform: translate3d(140px, 0, 0);
                pointer-events: none;
            }
            20% {
                opacity: 0;
                visibility: hidden;
                transform: translate3d(140px, 0, 0);
                pointer-events: none;
            }
            100% {
                opacity: 1;
                visibility: visible;
                transform: translate3d(0, 0, 0);
                pointer-events: auto;
            }
        }

        .card-float {
            position: relative;
            width: 100%;
            max-width: 490px;
            max-height: 100%;
            animation: card-float 6s ease-in-out infinite;
        }

        /* Soft blue halo behind the card */
        .card-glow {
            position: absolute;
            inset: -48px;
            z-index: 0;
            border-radius: 48px;
            background:
                radial-gradient(closest-side at 50% 45%, rgba(37, 99, 235, .45), transparent 75%),
                radial-gradient(closest-side at 70% 80%, rgba(191, 219, 254, .7), transparent 70%);
            filter: blur(28px);
            animation: glow-pulse 4s ease-in-out infinite;
            pointer-events: none;
        }

        .login-card {
            position: relative;
            z-index: 1;
            overflow: hidden auto;
            max-height: calc(100dvh - 140px);
            border-radius: 24px;
            padding: 38px 40px;
            color: #FFFFFF;
            background: linear-gradient(150deg, #1E3A8A 0%, #1D4ED8 55%, #2563EB 100%);
            box-shadow:
                0 0 0 1px rgba(191, 219, 254, .35),
                0 28px 60px -16px rgba(30, 58, 138, .6),
                0 0 70px rgba(37, 99, 235, .45);
        }

        /* Glowing edge */
        .login-card::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1.5px;
            background: linear-gradient(135deg, rgba(191, 219, 254, .95), rgba(191, 219, 254, 0) 35%, rgba(191, 219, 254, 0) 65%, rgba(191, 219, 254, .8));
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        /* One-time light sweep when the card arrives */
        .login-card::after {
            content: "";
            position: absolute;
            top: 0;
            left: -60%;
            width: 50%;
            height: 100%;
            background: linear-gradient(100deg, transparent, rgba(255, 255, 255, .18), transparent);
            transform: skewX(-18deg);
            pointer-events: none;
            animation: card-shine 1.4s ease 3.0s 1 both;
        }

        /* ---------- Controls on the blue card ---------- */
        .card-input {
            border-radius: 8px;
            font-size: .875rem;
            line-height: 1.25rem;
            color: #0F172A;
            background: rgba(255, 255, 255, .97);
            border: 1px solid rgba(191, 219, 254, .6);
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        .card-input::placeholder {
            color: #64748B;
        }

        .card-input:focus {
            outline: none;
            border-color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(191, 219, 254, .55), 0 0 18px rgba(191, 219, 254, .45);
        }

        .btn-light {
            border-radius: 8px;
            background: #FFFFFF;
            color: #1E3A8A;
            font-size: .875rem;
            font-weight: 600;
            line-height: 1.25rem;
            transition: background-color 150ms ease, box-shadow 200ms ease, transform 150ms ease;
        }

        .btn-light:hover {
            background: #EFF6FF;
            box-shadow: 0 0 24px rgba(191, 219, 254, .65);
        }

        .btn-light:active {
            transform: translateY(1px);
        }

        .btn-light:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(191, 219, 254, .7);
        }

        .card-link {
            color: #BFDBFE;
            font-size: .75rem;
            font-weight: 500;
        }

        .card-link:hover {
            color: #FFFFFF;
            text-decoration: underline;
        }

        /* ---------- Keyframes ---------- */
        @keyframes card-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes glow-pulse {
            0%, 100% { opacity: .75; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.04); }
        }

        @keyframes card-shine {
            from { left: -60%; }
            to { left: 130%; }
        }

        /* Skip the intro (form posts, errors, OTP, forgot password, reduced motion) */
        .no-anim .video-wrap {
            animation: none !important;
            transform: translate3d(-15vw, 0, 0) !important;
        }

        .no-anim .card-wrap {
            animation: none !important;
            opacity: 1 !important;
            visibility: visible !important;
            transform: translate3d(0, 0, 0) !important;
            pointer-events: auto !important;
        }

        .no-anim .login-card::after {
            animation: none !important;
        }

        @media (prefers-reduced-motion: reduce) {
            .card-float,
            .card-glow,
            .login-card::after {
                animation: none !important;
            }
        }

        /* ---------- Mobile: video on top, card below ---------- */
        @media (max-width: 767px) {
            .login-page {
                overflow: auto;
            }

            .login-brand {
                position: relative;
                top: auto;
                left: auto;
                padding: 18px 16px 0;
                justify-content: center;
            }

            .brand-card {
                gap: 12px;
                padding: 7px 16px 7px 12px;
                max-width: 100%;
            }

            .brand-logo {
                height: 28px;
            }

            .brand-divider {
                height: 20px;
            }

            .brand-title {
                font-size: 0.92rem;
                white-space: normal;
                line-height: 1.25;
            }

            .login-footer {
                position: relative;
                left: auto;
                bottom: auto;
                padding: 0 20px 24px;
                text-align: center;
            }

            .login-stage {
                height: auto;
                min-height: calc(100vh - 120px);
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .video-wrap {
                position: relative;
                inset: auto;
                width: 100%;
                height: 40vh;
                animation: none !important;
                transform: none !important;
            }

            .video-media {
                width: 100%;
                max-height: 100%;
            }

            .card-wrap {
                position: relative;
                inset: auto;
                width: 100%;
                max-width: 460px;
                padding: 0 20px 40px;
                animation: login-card-mobile 2.2s ease 0.1s both;
            }

            .no-anim .card-wrap {
                animation: none !important;
                opacity: 1 !important;
                visibility: visible !important;
                transform: none !important;
            }

            .login-card {
                max-height: none;
                padding: 28px 24px;
            }
        }

        @keyframes login-card-mobile {
            0%, 20% {
                opacity: 0;
                visibility: hidden;
                transform: translate3d(0, 40px, 0);
            }
            100% {
                opacity: 1;
                visibility: visible;
                transform: translate3d(0, 0, 0);
            }
        }
    </style>
</head>
<body class="login-page font-sans text-body text-app-text antialiased<?= $skipIntro ? ' is-ready no-anim' : '' ?>">
    <!-- Top Loading Bar Micro-Interaction -->
    <div id="top-loader" class="top-loader" aria-hidden="true">
        <div class="top-loader-bar" id="top-loader-bar">
            <div class="top-loader-glow"></div>
        </div>
    </div>
    <div id="top-loader-spinner" class="top-loader-spinner" aria-hidden="true">
        <div class="top-loader-spinner-circle"></div>
    </div>
    <header class="login-brand">
        <a href="login.php" class="brand-card" aria-label="Paragon Fleet home">
            <img class="brand-logo" src="assets/paragon.webp" alt="Paragon Communications Corp.">
            <span class="brand-divider" aria-hidden="true"></span>
            <span class="brand-title">Fleet Management and Monitoring System</span>
        </a>
    </header>

    <main class="login-stage">
        <div class="video-wrap" id="video-wrap">
            <div class="video-stage">
                <!-- Static poster image with pure white background; painted immediately at 0:00 so user never sees black box -->
                <img id="video-poster"
                     class="video-media video-poster-img"
                     src="assets/video/white-fleet-car-poster.png"
                     alt="Paragon Fleet Vehicle"
                     aria-hidden="true">

                <!-- New video with pure white background ("white fleet-car.mp4") -->
                <video id="fleet-video"
                       class="video-media"
                       autoplay
                       muted
                       loop
                       playsinline
                       preload="auto"
                       poster="assets/video/white-fleet-car-poster.png"
                       aria-hidden="true">
                    <source src="assets/video/white%20fleet-car.mp4" type="video/mp4">
                    <source src="assets/video/white fleet-car.mp4" type="video/mp4">
                </video>
            </div>
        </div>

        <section class="card-wrap" id="card-wrap" aria-label="Sign in">
            <div class="card-float">
                <div class="card-glow" aria-hidden="true"></div>
                <div class="login-card">
                    <div class="mb-6">
                        <h1 class="text-page-title tracking-tight text-white">
                            <?php if ($signedIn): ?>
                                You’re signed in
                            <?php elseif ($step === 'otp'): ?>
                                Two-Factor Authentication
                            <?php elseif ($step === 'forgot_request' || $step === 'forgot_reset'): ?>
                                Password Recovery
                            <?php else: ?>
                                Welcome back
                            <?php endif; ?>
                        </h1>
                        <p class="mt-1 text-body text-fleet-border">
                            <?php if ($signedIn): ?>
                                Connected to the <code>fleet_management_db</code> backend.
                            <?php elseif ($step === 'otp'): ?>
                                Enter the 6-digit OTP code generated for <strong class="text-white"><?= $escape($pendingEmail) ?></strong>.
                            <?php elseif ($step === 'forgot_request'): ?>
                                Enter your registered personal recovery email to receive a 6-digit reset code.
                            <?php elseif ($step === 'forgot_reset'): ?>
                                Enter the code sent to <strong class="text-white"><?= $escape($recoveryEmail) ?></strong> and your new password.
                            <?php else: ?>
                                Log in with your official company email and password.
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="mb-5 flex gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-body text-red-800" role="alert">
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
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/30">
                                <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
                                    <path d="m6 12.5 4 4 8-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-body font-semibold text-white">Welcome, <?= $escape($displayName) ?>!</p>
                                <p class="mt-1 text-xs text-fleet-border">
                                    Role: <span class="status-badge inline-flex items-center rounded-full px-2.5 py-0.5"><?= $escape($displayRole) ?></span>
                                </p>
                            </div>
                        </div>

                        <div class="space-y-1 rounded-xl border border-white/20 bg-white/10 p-3 text-xs text-fleet-border">
                            <div><span class="font-semibold text-white">Official Email:</span> <?= $escape((string) ($_SESSION['official_email'] ?? '')) ?></div>
                            <div><span class="font-semibold text-white">Personal Email:</span> <?= $escape((string) ($_SESSION['personal_email'] ?? '')) ?></div>
                        </div>

                        <details class="mt-4 rounded-xl border border-white/20 bg-white/5 p-3">
                            <summary class="cursor-pointer text-xs font-semibold text-white hover:text-fleet-border">
                                Update profile
                            </summary>
                            <form class="mt-3 space-y-2.5" method="post" action="login.php">
                                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="form_action" value="update_profile">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-white" for="prof-fname">First name</label>
                                        <input class="card-input w-full rounded-lg px-2.5 py-1.5" id="prof-fname" name="fname" type="text" value="<?= $escape((string) ($_SESSION['fname'] ?? '')) ?>" required>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-white" for="prof-mname">Middle name</label>
                                        <input class="card-input w-full rounded-lg px-2.5 py-1.5" id="prof-mname" name="mname" type="text" value="<?= $escape((string) ($_SESSION['mname'] ?? '')) ?>">
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-white" for="prof-lname">Last name</label>
                                    <input class="card-input w-full rounded-lg px-2.5 py-1.5" id="prof-lname" name="lname" type="text" value="<?= $escape((string) ($_SESSION['lname'] ?? '')) ?>" required>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-white" for="prof-pemail">Personal recovery email</label>
                                    <input class="card-input w-full rounded-lg px-2.5 py-1.5" id="prof-pemail" name="personal_email" type="email" value="<?= $escape((string) ($_SESSION['personal_email'] ?? '')) ?>" required>
                                </div>
                                <button class="btn-light w-full rounded-lg px-3 py-2 text-xs" type="submit">Save profile changes</button>
                            </form>
                        </details>

                        <form class="mt-5" method="post" action="login.php">
                            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                            <button class="btn-light w-full rounded-lg px-4 py-3" type="submit" name="logout" value="1">Sign out</button>
                        </form>

                    <?php elseif ($step === 'otp'): ?>
                        <?php if ($otpPreview !== ''): ?>
                            <div class="mb-4 rounded-xl border border-white/25 bg-white/10 p-3 text-xs text-white">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold">OTP preview (testing mode)</span>
                                    <button type="button" onclick="document.getElementById('otp_code').value='<?= $escape($otpPreview) ?>'" class="btn-light rounded px-2 py-0.5 text-[11px]">Auto-fill OTP</button>
                                </div>
                                <p class="mt-1 font-mono text-base font-bold tracking-widest text-white"><?= $escape($otpPreview) ?></p>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="login.php" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="form_action" value="verify_otp">
                            <input type="hidden" name="official_email" value="<?= $escape($pendingEmail) ?>">
                            <div>
                                <label class="mb-1.5 block text-body font-medium text-white" for="otp_code">6-digit OTP code</label>
                                <input class="card-input w-full rounded-lg px-3 py-2.5 text-center font-mono text-lg tracking-widest" id="otp_code" name="otp_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus>
                            </div>
                            <div class="space-y-2 pt-1">
                                <button class="btn-light w-full rounded-lg px-4 py-3" type="submit">Verify OTP &amp; sign in</button>
                                <a href="login.php?view=login" class="card-link block w-full py-1 text-center">Cancel and return to log in</a>
                            </div>
                        </form>

                    <?php elseif ($step === 'forgot_request'): ?>
                        <form method="post" action="login.php" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="form_action" value="forgot_request_code">
                            <div>
                                <label class="mb-1.5 block text-body font-medium text-white" for="personal_email">Personal recovery email</label>
                                <input class="card-input w-full rounded-lg px-3 py-2.5" id="personal_email" name="personal_email" type="email" placeholder="your.personal@gmail.com" required autofocus>
                            </div>
                            <div class="space-y-2 pt-1">
                                <button class="btn-light w-full rounded-lg px-4 py-3" type="submit">Send 6-digit recovery code</button>
                                <a href="login.php?view=login" class="card-link block w-full py-1 text-center">Back to log in</a>
                            </div>
                        </form>

                    <?php elseif ($step === 'forgot_reset'): ?>
                        <?php if ($recoveryPreview !== ''): ?>
                            <div class="mb-4 rounded-xl border border-white/25 bg-white/10 p-3 text-xs text-white">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold">Recovery code preview (testing mode)</span>
                                    <button type="button" onclick="document.getElementById('recovery_code').value='<?= $escape($recoveryPreview) ?>'" class="btn-light rounded px-2 py-0.5 text-[11px]">Auto-fill code</button>
                                </div>
                                <p class="mt-1 font-mono text-base font-bold tracking-widest text-white"><?= $escape($recoveryPreview) ?></p>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="login.php" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="form_action" value="forgot_reset_password">
                            <input type="hidden" name="personal_email" value="<?= $escape($recoveryEmail) ?>">
                            <div>
                                <label class="mb-1.5 block text-body font-medium text-white" for="recovery_code">6-digit recovery code</label>
                                <input class="card-input w-full rounded-lg px-3 py-2 text-center font-mono text-base tracking-widest" id="recovery_code" name="recovery_code" type="text" maxlength="6" placeholder="000000" required>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-body font-medium text-white" for="new_password">New password</label>
                                <input class="card-input w-full rounded-lg px-3 py-2.5" id="new_password" name="new_password" type="password" minlength="8" placeholder="At least 8 characters" required>
                            </div>
                            <div class="space-y-2 pt-1">
                                <button class="btn-light w-full rounded-lg px-4 py-3" type="submit">Reset password</button>
                                <a href="login.php?view=login" class="card-link block w-full py-1 text-center">Back to log in</a>
                            </div>
                        </form>

                    <?php else: ?>
                        <form method="post" action="login.php" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="form_action" value="login">
                            <div>
                                <label class="mb-1.5 block text-body font-medium text-white" for="email">Official company email</label>
                                <input class="card-input w-full rounded-lg px-3 py-2.5" id="email" name="email" type="email" autocomplete="username" placeholder="you@company.com" required>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-body font-medium text-white" for="password">Password</label>
                                <input class="card-input w-full rounded-lg px-3 py-2.5" id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                                <div class="mt-2 text-right">
                                    <a href="login.php?view=forgot" class="card-link">Forgot password?</a>
                                </div>
                            </div>
                            <div class="pt-2">
                                <button class="btn-light w-full rounded-lg px-4 py-3" type="submit">Log in</button>
                            </div>
                        </form>
                        <p class="mt-5 border-t border-white/20 pt-4 text-center">
                            <a class="card-link" href="request-access.php">Don’t have an account? Request access</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="login-footer text-xs text-slate-600">
        © <?= date('Y') ?> Paragon Communications Corp. <span class="mx-1.5 text-slate-400">·</span> Fleet operations, made clearer.
    </footer>

    <script>
        (function () {
            var body = document.body;
            var videoWrap = document.getElementById('video-wrap');
            var video = document.getElementById('fleet-video');

            // =========================================================
            // 1. VIDEO ENGINE: CONTINUOUS PLAYBACK ON ALL SCREENS
            // (Login, Forgot Password, OTP, Signed In)
            // =========================================================
            if (video) {
                video.muted = true;
                video.defaultMuted = true;
                video.loop = true;
                video.playsInline = true;

                function ensurePlaying() {
                    if (video.paused) {
                        var p = video.play();
                        if (p && typeof p.catch === 'function') {
                            p.catch(function () {});
                        }
                    }
                }

                // Initial play attempt
                ensurePlaying();

                // Autoplay unlock: if browser policy paused initial playback, unlock on first user interaction
                function unlockAutoplay() {
                    ensurePlaying();
                    document.removeEventListener('click', unlockAutoplay);
                    document.removeEventListener('keydown', unlockAutoplay);
                    document.removeEventListener('touchstart', unlockAutoplay);
                }
                document.addEventListener('click', unlockAutoplay, { once: true });
                document.addEventListener('keydown', unlockAutoplay, { once: true });
                document.addEventListener('touchstart', unlockAutoplay, { once: true });

                // Seamless continuous loop: never stops at 7s boundary
                video.addEventListener('timeupdate', function () {
                    if (video.currentTime >= 6.85) {
                        video.currentTime = 0.05;
                    }
                });

                // Fallback loop if ended event fires
                video.addEventListener('ended', function () {
                    video.currentTime = 0.05;
                    ensurePlaying();
                });

                // Resume if ever paused
                video.addEventListener('pause', function () {
                    setTimeout(ensurePlaying, 250);
                });
            }

            // =========================================================
            // 2. CHOREOGRAPHY & TIMING (0:00 Center -> 0:03 Left)
            // =========================================================
            if (body.classList.contains('no-anim')) {
                // When intro is skipped (OTP, error, or signed-in state):
                // Video continues playing, focus the first input immediately
                setTimeout(function () {
                    var field = document.querySelector('.login-card input:not([type=hidden])');
                    if (field) {
                        field.focus({ preventScroll: true });
                    }
                }, 300);
                return;
            }

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                body.classList.add('no-anim');
                return;
            }

            // Auto-focus primary field once the 3.0s animation completes
            setTimeout(function () {
                var field = document.querySelector('.login-card input:not([type=hidden])');
                if (field) {
                    field.focus({ preventScroll: true });
                }
            }, 3100);
        })();
    </script>
</body>
</html>

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
    <meta name="theme-color" content="#F8FAFA">
    <title>Request access | Paragon Fleet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/top-loader.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/fleet_backend/frontend_api_helper.js" onerror="window.FLEET_HELPER_FALLBACK=true"></script>
    <script src="assets/top-loader.js"></script>
    <style>
        .request-page {
            background: #F8FAFA;
            height: 100vh;
            height: 100dvh;
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
            left: 36px;
            bottom: 20px;
            z-index: 30;
        }

        /* Center the card with safe top padding so it stays comfortably below the brand header */
        .stage-container {
            height: 100vh;
            height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 90px 24px 36px;
            box-sizing: border-box;
            overflow: hidden;
        }

        /* Compact, wide rectangular card */
        .card-float {
            position: relative;
            width: 100%;
            max-width: 820px;
            animation: card-float 6s ease-in-out infinite;
        }

        /* Soft blue halo behind the card */
        .card-glow {
            position: absolute;
            inset: -32px;
            z-index: 0;
            border-radius: 36px;
            background:
                radial-gradient(closest-side at 50% 45%, rgba(37, 99, 235, .38), transparent 75%),
                radial-gradient(closest-side at 70% 80%, rgba(191, 219, 254, .6), transparent 70%);
            filter: blur(22px);
            animation: glow-pulse 4s ease-in-out infinite;
            pointer-events: none;
        }

        /* Strictly no scrollbars - compact layout fits 100% in viewport */
        .request-card {
            position: relative;
            z-index: 1;
            overflow: hidden;
            border-radius: 20px;
            padding: 22px 34px;
            color: #FFFFFF;
            background: linear-gradient(150deg, #1E3A8A 0%, #1D4ED8 55%, #2563EB 100%);
            box-shadow:
                0 0 0 1px rgba(191, 219, 254, .35),
                0 22px 50px -16px rgba(30, 58, 138, .55),
                0 0 60px rgba(37, 99, 235, .38);
        }

        /* Glowing edge */
        .request-card::before {
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

        /* One-time light sweep */
        .request-card::after {
            content: "";
            position: absolute;
            top: 0;
            left: -60%;
            width: 50%;
            height: 100%;
            background: linear-gradient(100deg, transparent, rgba(255, 255, 255, .18), transparent);
            transform: skewX(-18deg);
            pointer-events: none;
            animation: card-shine 1.4s ease 0.6s 1 both;
        }

        /* Inputs styled like login card */
        .card-input {
            border-radius: 6px;
            font-size: .75rem;
            line-height: 1rem;
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
            box-shadow: 0 0 0 3px rgba(191, 219, 254, .55), 0 0 16px rgba(191, 219, 254, .45);
        }

        .btn-light {
            border-radius: 6px;
            background: #FFFFFF;
            color: #1E3A8A;
            font-size: .8125rem;
            font-weight: 600;
            line-height: 1.125rem;
            transition: background-color 150ms ease, box-shadow 200ms ease, transform 150ms ease;
        }

        .btn-light:hover {
            background: #EFF6FF;
            box-shadow: 0 0 22px rgba(191, 219, 254, .65);
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

        @keyframes card-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        @keyframes glow-pulse {
            0%, 100% { opacity: .75; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.02); }
        }

        @keyframes card-shine {
            from { left: -60%; }
            to { left: 130%; }
        }

        @media (max-width: 767px) {
            .request-page {
                overflow: auto;
                height: auto;
                min-height: 100vh;
            }

            .login-brand {
                position: relative;
                top: auto;
                left: auto;
                padding: 16px 16px 0;
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
                padding: 16px 20px 24px;
                text-align: center;
            }

            .stage-container {
                padding: 16px 16px 32px;
                height: auto;
                min-height: auto;
            }

            .request-card {
                padding: 22px 18px;
            }
        }
    </style>
</head>
<body class="request-page font-sans text-body text-app-text antialiased">
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

    <main class="stage-container">
        <div class="card-float">
            <div class="card-glow" aria-hidden="true"></div>

            <section class="request-card" aria-label="Request access form">
                <div class="mb-3 border-b border-white/15 pb-2">
                    <h1 class="text-lg font-bold tracking-tight text-white">Request an Access</h1>
                    <p class="mt-0.5 text-[11px] text-fleet-border">
                        Submit your official company credentials. A Head Admin will review and activate your access.
                    </p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="mb-3 flex gap-2.5 rounded-lg border border-red-300/40 bg-red-500/20 px-3 py-2 text-xs leading-5 text-white backdrop-blur-sm" role="alert">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-200" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm0-11a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 7Zm0 7.25a.875.875 0 1 0 0-1.75.875.875 0 0 0 0 1.75Z" clip-rule="evenodd"/>
                        </svg>
                        <p><?= $escape($error) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($notice !== ''): ?>
                    <div class="rounded-xl border border-white/25 bg-white/10 p-5 text-center text-white backdrop-blur-sm" role="status">
                        <div class="mx-auto mb-2.5 flex h-12 w-12 items-center justify-center rounded-full bg-white/20 text-white ring-1 ring-white/30">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                        <h2 class="text-base font-bold text-white"><?= $escape($notice) ?></h2>
                        <p class="mt-1.5 text-xs text-fleet-border">
                            Your account is saved with status <span class="status-badge inline-flex items-center rounded-full px-2.5 py-0.5">Pending Approval</span> (<code>is_active = 0</code>).
                        </p>
                        <a class="btn-light mt-4 inline-flex justify-center rounded-md px-5 py-2 text-xs font-semibold" href="login.php">
                            Return to log in
                        </a>
                    </div>
                <?php else: ?>
                    <form id="request-form" class="space-y-2.5" action="request-access.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">

                        <!-- Name row (3 columns) -->
                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                            <div>
                                <label class="mb-0.5 block text-[11px] font-medium text-white" for="request-fname">First name</label>
                                <input class="card-input w-full rounded-md px-2.5 py-1.5 text-xs" id="request-fname" name="fname" type="text" placeholder="Juan" maxlength="100" value="<?= $escape($fname) ?>" required autofocus>
                            </div>
                            <div>
                                <label class="mb-0.5 block text-[11px] font-medium text-white" for="request-mname">Middle name <span class="font-normal text-fleet-border">(optional)</span></label>
                                <input class="card-input w-full rounded-md px-2.5 py-1.5 text-xs" id="request-mname" name="mname" type="text" placeholder="Santos" maxlength="100" value="<?= $escape($mname) ?>">
                            </div>
                            <div>
                                <label class="mb-0.5 block text-[11px] font-medium text-white" for="request-lname">Last name</label>
                                <input class="card-input w-full rounded-md px-2.5 py-1.5 text-xs" id="request-lname" name="lname" type="text" placeholder="Dela Cruz" maxlength="100" value="<?= $escape($lname) ?>" required>
                            </div>
                        </div>

                        <!-- Email row (2 equal columns) -->
                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                            <div>
                                <label class="mb-0.5 block text-[11px] font-medium text-white" for="request-official-email">Official Company Email</label>
                                <input class="card-input w-full rounded-md px-2.5 py-1.5 text-xs" id="request-official-email" name="official_email" type="email" placeholder="you@company.com" maxlength="150" value="<?= $escape($officialEmail) ?>" required>
                            </div>
                            <div>
                                <label class="mb-0.5 block text-[11px] font-medium text-white" for="request-personal-email">Personal Recovery Email</label>
                                <input class="card-input w-full rounded-md px-2.5 py-1.5 text-xs" id="request-personal-email" name="personal_email" type="email" placeholder="you.personal@gmail.com" maxlength="150" value="<?= $escape($personalEmail) ?>" required>
                            </div>
                        </div>

                        <!-- Password row (2 equal columns) -->
                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                            <div>
                                <label class="mb-0.5 block text-[11px] font-medium text-white" for="request-password">Create password</label>
                                <input class="card-input w-full rounded-md px-2.5 py-1.5 text-xs" id="request-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="At least 8 characters" required>
                            </div>
                            <div>
                                <label class="mb-0.5 block text-[11px] font-medium text-white" for="request-password-confirmation">Confirm password</label>
                                <input class="card-input w-full rounded-md px-2.5 py-1.5 text-xs" id="request-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Repeat password" required>
                            </div>
                        </div>

                        <!-- Role & Reason row (2 columns) -->
                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                            <div>
                                <label class="mb-0.5 block text-[11px] font-medium text-white" for="requested-role">Requested role</label>
                                <select class="card-input w-full rounded-md px-2.5 py-1.5 text-xs" id="requested-role" name="role" required>
                                    <option value="" disabled <?= $role === '' ? 'selected' : '' ?>>Select a role</option>
                                    <option value="accounting" <?= $role === 'accounting' ? 'selected' : '' ?>>Accounting</option>
                                    <option value="manager" <?= $role === 'manager' ? 'selected' : '' ?>>Manager</option>
                                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="head-admin" <?= ($role === 'head-admin' || $role === 'head_admin') ? 'selected' : '' ?>>Head Admin</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-0.5 block text-[11px] font-medium text-white" for="request-reason">Reason for access <span class="font-normal text-fleet-border">(optional)</span></label>
                                <input class="card-input w-full rounded-md px-2.5 py-1.5 text-xs" id="request-reason" name="reason" type="text" maxlength="1000" placeholder="Briefly describe your work requirements" value="<?= $escape($reason) ?>">
                            </div>
                        </div>

                        <p class="text-[10px] leading-tight text-fleet-border pt-0.5">
                            Official email is used for login and 2FA OTP verification; personal email is used for account password recovery.
                        </p>

                        <div class="pt-1">
                            <button class="btn-light w-full rounded-md py-2 px-3 text-xs font-semibold" type="submit">
                                Submit Account Request
                            </button>
                        </div>
                    </form>

                    <p class="mt-2.5 border-t border-white/15 pt-2 text-center">
                        <a class="card-link text-xs" href="login.php">
                            Already have an account? Back to log in
                        </a>
                    </p>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer class="login-footer text-xs text-slate-600">
        © <?= date('Y') ?> Paragon Communications Corp. <span class="mx-1.5 text-slate-400">·</span> Fleet operations, made clearer.
    </footer>
</body>
</html>

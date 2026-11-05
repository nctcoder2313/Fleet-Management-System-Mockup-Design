<?php
declare(strict_types=1);

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
$signedIn = isset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(400);
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (isset($_POST['logout'])) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: /');
        exit;
    } else {
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string) ($_POST['password'] ?? '');

        if ($email === false || $password === '') {
            $error = 'Enter a valid email address and password.';
        } else {
            $host = getenv('DB_HOST');
            $database = getenv('DB_NAME');
            $username = getenv('DB_USER');
            $dbPassword = getenv('DB_PASSWORD');

            if (!$host || !$database || $username === false || $dbPassword === false) {
                $error = 'Login is not connected yet. Configure the MySQL database to continue.';
            } else {
                try {
                    $pdo = new PDO(
                        "mysql:host={$host};dbname={$database};charset=utf8mb4",
                        $username,
                        $dbPassword,
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES => false,
                        ]
                    );
                    $statement = $pdo->prepare(
                        'SELECT id, name, role, password_hash FROM users WHERE email = :email LIMIT 1'
                    );
                    $statement->execute(['email' => $email]);
                    $user = $statement->fetch();

                    if ($user && password_verify($password, $user['password_hash'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = (int) $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $signedIn = true;
                    } else {
                        $error = 'The email or password you entered is incorrect.';
                    }
                } catch (PDOException $exception) {
                    error_log('Fleet management login database error: ' . $exception->getMessage());
                    $error = 'We could not connect to the login service. Please try again later.';
                }
            }
        }
    }
}

$roleLabels = [
    'accounting' => 'Accounting',
    'manager' => 'Manager',
    'admin' => 'Admin',
    'head_admin' => 'Head Admin',
];
$displayName = $signedIn ? (string) $_SESSION['user_name'] : '';
$displayRole = $signedIn
    ? ($roleLabels[$_SESSION['user_role']] ?? 'Team member')
    : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#dbeafe">
    <title>Log in | Paragon Fleet</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                        <?= $signedIn ? 'You’re signed in' : 'Log in to your account' ?>
                    </h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        <?= $signedIn
                            ? 'Your account is ready to go.'
                            : 'Enter your work email and password to continue.' ?>
                    </p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="mb-5 flex gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm0-11a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 7Zm0 7.25a.875.875 0 1 0 0-1.75.875.875 0 0 0 0 1.75Z" clip-rule="evenodd"/>
                        </svg>
                        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($signedIn): ?>
                    <div class="rounded-2xl border border-blue-100 bg-white p-5 shadow-[0_12px_40px_rgba(30,64,175,0.07)]">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                            <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
                                <path d="m6 12.5 4 4 8-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-slate-900">
                            Welcome, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>.
                        </p>
                        <p class="mt-1 text-sm text-slate-600">
                            Signed in as <?= htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8') ?>.
                        </p>
                        <form class="mt-6" method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <button class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200" type="submit" name="logout" value="1">
                                Sign out
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="post" action="" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="email">Work email</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" id="email" name="email" type="email" autocomplete="username" placeholder="you@company.com" required>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="password">Password</label>
                            <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                            <details class="mt-2 text-right">
                                <summary class="cursor-pointer list-none text-xs font-medium text-blue-700 hover:text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                                    Forgot password?
                                </summary>
                                <p class="mt-2 text-left text-xs leading-5 text-slate-500">
                                    Please contact your system administrator to reset your password.
                                </p>
                            </details>
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
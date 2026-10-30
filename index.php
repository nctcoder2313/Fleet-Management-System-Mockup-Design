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
    <meta name="theme-color" content="#f5f8ff">
    <title>Sign in | Fleetwise</title>
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
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        body {
            margin: 0;
            min-width: 320px;
            min-height: 100vh;
            background: #f5f8ff;
        }

        .hero-panel {
            background-color: #123f9e;
            background-image: linear-gradient(155deg, rgba(14, 46, 113, .2), rgba(11, 42, 105, .02)), url("assets/fleet-hero.svg");
            background-position: center;
            background-size: cover;
        }

        .form-input:focus {
            border-color: #3478f6;
            box-shadow: 0 0 0 4px rgba(52, 120, 246, .12);
            outline: none;
        }

        @media (max-width: 767px) {
            .hero-panel {
                min-height: 270px;
                background-position: center 57%;
            }
        }
    </style>
</head>
<body class="font-sans text-slate-900 antialiased">
    <main class="min-h-screen md:grid md:grid-cols-[1.08fr_.92fr]">
        <section class="hero-panel relative flex min-h-[310px] flex-col overflow-hidden px-7 py-7 text-white sm:px-10 md:min-h-screen md:px-12 md:py-10 lg:px-16">
            <a class="relative z-10 inline-flex w-fit items-center gap-3 text-white" href="/" aria-label="Fleetwise home">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25 backdrop-blur-sm">
                    <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
                        <path d="M3 16.5h18M5.5 16.5l1-5.3a2 2 0 0 1 2-1.7h7a2 2 0 0 1 1.9 1.4l1.2 5.6M8 16.5v2m8-2v2M7.5 13h9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="8" cy="17" r="1.4" fill="currentColor"/>
                        <circle cx="16" cy="17" r="1.4" fill="currentColor"/>
                    </svg>
                </span>
                <span class="text-xl font-semibold tracking-tight">fleetwise</span>
            </a>

            <div class="relative z-10 mt-12 max-w-lg sm:mt-14 md:mt-[clamp(4rem,13vh,9rem)]">
                <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-medium tracking-wide text-blue-50 backdrop-blur-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
                    YOUR FLEET, IN SYNC
                </p>
                <h1 class="max-w-md text-3xl font-semibold leading-tight tracking-tight sm:text-4xl lg:text-[2.75rem]">
                    Every mile,<br>moving you forward.
                </h1>
                <p class="mt-4 max-w-sm text-sm leading-6 text-blue-100 sm:text-base">
                    Keep your vehicles, people, and operations moving together — all from one place.
                </p>
            </div>

            <div class="relative z-10 mt-auto hidden pt-10 text-xs text-blue-100/80 md:block">
                <span>© <?= date('Y') ?> Fleetwise</span>
                <span class="mx-2 text-white/40">·</span>
                <span>Fleet operations, made clearer.</span>
            </div>
        </section>

        <section class="flex min-h-[calc(100vh-310px)] items-center justify-center px-6 py-12 sm:px-10 md:min-h-screen md:px-12 lg:px-16">
            <div class="w-full max-w-[410px]">
                <div class="mb-9 md:mb-11">
                    <p class="mb-2 text-sm font-medium text-fleet-600">Welcome back</p>
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-[2rem]">
                        <?= $signedIn ? 'You’re signed in' : 'Sign in to your account' ?>
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">
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
                    <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-[0_12px_40px_rgba(30,64,175,0.07)]">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                            <svg viewBox="0 0 24 24" fill="none" class="h-6 w-6" aria-hidden="true">
                                <path d="m6 12.5 4 4 8-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <p class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-1 text-sm text-slate-500">Signed in as <span class="font-medium text-slate-700"><?= htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8') ?></span></p>
                        <form class="mt-6" method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <button class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100" type="submit" name="logout" value="1">
                                Sign out
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="post" class="space-y-5" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Email address</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true">
                                        <rect x="2.5" y="4" width="15" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/>
                                        <path d="m3.5 6 6.5 4.5L16.5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <input class="form-input w-full rounded-xl border border-slate-200 bg-white py-3 pl-11 pr-4 text-sm text-slate-900 placeholder:text-slate-400 transition" id="email" name="email" type="email" autocomplete="username" placeholder="you@company.com" value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                            </div>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true">
                                        <rect x="3.5" y="8.5" width="13" height="9" rx="2" stroke="currentColor" stroke-width="1.5"/>
                                        <path d="M6.5 8.5V6a3.5 3.5 0 0 1 7 0v2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        <circle cx="10" cy="13" r="1" fill="currentColor"/>
                                    </svg>
                                </span>
                                <input class="form-input w-full rounded-xl border border-slate-200 bg-white py-3 pl-11 pr-14 text-sm text-slate-900 placeholder:text-slate-400 transition" id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                                <button class="absolute inset-y-0 right-0 flex items-center px-4 text-xs font-semibold text-slate-500 transition hover:text-fleet-600 focus:outline-none focus-visible:text-fleet-700" id="toggle-password" type="button" aria-label="Show password" aria-pressed="false">
                                    Show
                                </button>
                            </div>
                        </div>

                        <button class="group flex w-full items-center justify-center gap-2 rounded-xl bg-fleet-600 px-4 py-3.5 text-sm font-semibold text-white shadow-[0_6px_14px_rgba(36,99,235,0.2)] transition hover:bg-fleet-700 focus:outline-none focus:ring-4 focus:ring-blue-200" type="submit">
                            Sign in
                            <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true">
                                <path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </form>

                    <p class="mt-7 text-center text-sm text-slate-500">
                        Need an account?
                        <span class="font-medium text-slate-700">Contact your system administrator.</span>
                    </p>
                <?php endif; ?>

                <p class="mt-10 text-center text-xs text-slate-400 md:hidden">© <?= date('Y') ?> Fleetwise</p>
            </div>
        </section>
    </main>
    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('toggle-password');

        passwordToggle?.addEventListener('click', () => {
            const isVisible = passwordInput.type === 'text';
            passwordInput.type = isVisible ? 'password' : 'text';
            passwordToggle.textContent = isVisible ? 'Show' : 'Hide';
            passwordToggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
            passwordToggle.setAttribute('aria-pressed', String(!isVisible));
        });

        document.querySelector('form[method="post"]:not(:has([name="logout"]))')?.addEventListener('submit', (event) => {
            const emailInput = document.getElementById('email');
            if (!emailInput.checkValidity() || passwordInput.value.length === 0) {
                event.preventDefault();
                emailInput.reportValidity();
                if (emailInput.checkValidity() && passwordInput.value.length === 0) {
                    passwordInput.reportValidity();
                }
            }
        });
    </script>
</body>
</html>

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
    } elseif ($selectedRole !== '' && !in_array($selectedRole, ['admin', 'manager', 'accounting'], true)) {
        $error = 'Select a valid role to request.';
    } else {
        $login = completeLogin($pdo, (int) $account['id'], $selectedRole === '' ? null : $selectedRole);
        header('Location: ' . $login['dashboard']);
        exit;
    }
}

$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$loginRoles = [
    'admin' => 'Admin',
    'manager' => 'Manager',
    'accounting' => 'Accounting',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#dbeafe">
    <title>Log in | Paragon Fleet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { color-scheme: light; font-family: ui-sans-serif, system-ui, sans-serif; }
        body { margin: 0; min-width: 320px; min-height: 100vh; background: #f8fafc; }
        .hero-panel { background: #fff; }
        .form-input:focus { border-color: #3478f6; box-shadow: 0 0 0 4px rgba(52, 120, 246, .12); outline: none; }
        .gradient-surface { position: relative; isolation: isolate; overflow: hidden; }
        .gradient-surface::before {
            content: ""; position: absolute; inset: -25%; z-index: 0; pointer-events: none;
            background: linear-gradient(112deg, transparent 12%, rgba(59, 130, 246, .1) 38%, transparent 62%, rgba(96, 165, 250, .14) 88%, transparent);
            filter: blur(48px);
        }
        .gradient-surface > * { position: relative; z-index: 1; }
        @media (max-width: 767px) { .hero-panel { min-height: 470px; } }
        @media (min-width: 768px) and (min-height: 540px) {
            body { height: 100vh; height: 100dvh; overflow: hidden; }
            body > main { position: fixed; inset: 0; height: 100vh; height: 100dvh; min-height: 0; }
            body > main > section { height: 100%; min-height: 0; }
            body > main > .hero-panel, body > main > .gradient-surface { overflow-x: hidden; overflow-y: auto; }
            .hero-panel .hero-vehicle { max-height: clamp(120px, calc(100dvh - 420px), 280px); }
        }
        @media (prefers-reduced-motion: no-preference) {
            .login-card { animation: login-enter .45s ease-out both; }
            .login-action { transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease; }
            .login-action:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(29, 78, 216, .18); }
            @keyframes login-enter { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
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
                <h1 class="max-w-md text-2xl font-bold leading-tight tracking-tight text-slate-900 sm:text-3xl">Your fleet,<br>always connected.</h1>
                <p class="mt-3 max-w-sm text-sm leading-6 text-slate-600">Keep your vehicles, people, and operations moving together — all from one place.</p>
            </div>
            <div class="flex flex-1 items-center justify-center py-5">
                <img class="hero-vehicle max-h-[220px] w-full object-contain sm:max-h-[280px] md:max-h-[40vh] lg:max-h-[48vh]" src="assets/fleet%20pcc%20image.jpg" alt="Paragon fleet service vehicle">
            </div>
            <div class="relative z-10 mt-auto hidden pt-6 text-xs text-slate-600 md:block">
                <span>© <?= date('Y') ?> Paragon Communications Corp.</span><span class="mx-2 text-blue-300">·</span><span>Fleet operations, made clearer.</span>
            </div>
        </section>
        <section class="gradient-surface flex min-h-[calc(100vh-470px)] items-center justify-center bg-gradient-to-br from-[#dbeafe] via-[#eaf4ff] to-[#cfe4ff] px-6 py-8 sm:px-10 md:min-h-screen md:px-10 md:py-6 lg:px-14">
            <div class="login-card w-full max-w-[460px] rounded-2xl border border-slate-200 bg-white p-7 shadow-[0_20px_60px_rgba(15,23,42,0.08)] sm:p-9">
                <div class="mb-6">
                    <p class="mb-1 text-lg font-semibold text-blue-700">Fleet Management and Monitoring System</p>
                    <h2 class="text-base font-bold tracking-tight text-slate-900">Log in to your account</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Enter your email and password to continue.</p>
                </div>
                <?php if ($flashMessage !== ''): ?>
                    <div role="status" class="mb-5 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"><?= $escape($flashMessage) ?></div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div role="alert" class="mb-5 flex gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <span class="font-bold" aria-hidden="true">!</span><p><?= $escape($error) ?></p>
                    </div>
                <?php endif; ?>
                <form method="post" action="login.php" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700" for="email">Email</label>
                        <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" id="email" name="email" type="email" value="<?= $escape(is_string($_POST['email'] ?? null) ? $_POST['email'] : '') ?>" autocomplete="username" placeholder="you@company.com" required>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700" for="password">Password</label>
                        <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400" id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                        <details class="mt-2 text-right">
                            <summary class="cursor-pointer list-none text-xs font-medium text-blue-700 hover:text-blue-800 hover:underline">Forgot password?</summary>
                            <p class="mt-2 text-left text-xs leading-5 text-slate-500">Please contact your Head Admin to reset your password.</p>
                        </details>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700" for="role">Request a different role <span class="font-normal text-slate-500">(optional)</span></label>
                        <select class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900" id="role" name="role">
                            <option value="">Sign in with my assigned role</option>
                            <?php foreach ($loginRoles as $value => $label): ?>
                                <option value="<?= $escape($value) ?>" <?= ($_POST['role'] ?? '') === $value ? 'selected' : '' ?>>Request <?= $escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1.5 text-xs leading-5 text-slate-500">A different role is sent to the Head Admin for approval; it does not change your current access.</p>
                    </div>
                    <button class="login-action w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white focus:outline-none focus:ring-4 focus:ring-blue-200" type="submit">Log in</button>
                </form>
                <p class="mt-5 border-t border-slate-100 pt-4 text-center">
                    <a class="text-xs font-medium text-blue-700 hover:text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2" href="request-access.php">Don’t have an account? Request access</a>
                </p>
            </div>
        </section>
    </main>
</body>
</html>

<?php
declare(strict_types=1);
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
            main {
                height: 100vh;
                min-height: 0;
                overflow: hidden;
            }
        }
    </style>
</head>
<body class="font-sans text-slate-900 antialiased">
    <main class="gradient-surface relative z-10 flex min-h-screen items-center justify-center bg-gradient-to-br from-[#dbeafe] via-[#eaf4ff] to-[#cfe4ff] px-4 py-20 sm:px-6 sm:py-16 md:py-4">
        <section class="w-full max-w-[480px] rounded-2xl border border-blue-100 bg-white p-5 shadow-[0_20px_60px_rgba(30,64,175,0.16)] sm:p-6">
            <a class="mb-3 flex justify-center" href="index.php" aria-label="Paragon Fleet home">
                <img class="h-auto w-36" src="assets/paragon.webp" alt="Paragon Communications Corp.">
            </a>
            <div class="mb-4 text-center">
                <p class="mb-1 text-lg font-semibold text-blue-700">Fleet Management and Monitoring System</p>
                <h2 class="text-xl font-bold tracking-tight text-slate-900">Request an account</h2>
                <p class="mt-2 text-xs leading-5 text-slate-600">
                        Tell us about your access needs. An administrator will review your request.
                </p>
            </div>

            <form id="request-form" class="space-y-3" action="request-access.php" method="post">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="request-name">Full name</label>
                        <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-name" name="name" type="text" autocomplete="name" placeholder="Your full name" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="request-email">Work email</label>
                        <input class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-email" name="email" type="email" autocomplete="email" placeholder="you@company.com" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="requested-role">Requested role</label>
                        <select class="form-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900" id="requested-role" name="role" required>
                            <option value="" selected disabled>Select a role</option>
                            <option value="accounting">Accounting</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                            <option value="head_admin">Head Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="request-reason">Reason for access <span class="font-normal text-slate-500">(optional)</span></label>
                        <textarea class="form-input w-full resize-y rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400" id="request-reason" name="reason" rows="2" placeholder="Briefly describe your work requirements"></textarea>
                    </div>

                    <p class="text-xs leading-5 text-slate-600">
                        Admin and Head Admin access requires approval from an existing Head Admin.
                    </p>
                    <button class="w-full rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200" type="submit">
                        Submit request
                    </button>
            </form>

            <p id="request-notice" class="mt-4 hidden rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs leading-5 text-blue-900" role="status">
                This request form is a mockup and does not send or save your information yet.
            </p>

            <p class="mt-4 border-t border-slate-100 pt-3 text-center">
                <a class="text-xs font-medium text-blue-700 hover:text-blue-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2" href="index.php">
                    Back to log in
                </a>
            </p>
        </section>
    </main>
    <script>
        document.getElementById('request-form').addEventListener('submit', (event) => {
            event.preventDefault();
            document.getElementById('request-notice').classList.remove('hidden');
        });
    </script>
</body>
</html>
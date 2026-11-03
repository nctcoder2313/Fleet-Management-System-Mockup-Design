<?php
$today = date('l, F j, Y');
$filters = [
    'all' => 'All vehicles',
    'on-trip' => 'On trip',
    'available' => 'Available',
    'maintenance' => 'Maintenance',
    'damaged' => 'Damaged',
    'attention' => 'Needs attention',
    'low-fuel' => 'Low fuel (25% or below)',
];
$requestedFilter = $_GET['filter'] ?? 'all';
$filter = is_string($requestedFilter) && array_key_exists($requestedFilter, $filters)
    ? $requestedFilter
    : 'all';
$requestedSearch = $_GET['q'] ?? '';
$search = is_string($requestedSearch) ? trim($requestedSearch) : '';

$vehicles = [
    [
        'name' => 'Toyota HiAce',
        'plate' => 'ABC 2048',
        'driver' => 'Miguel Santos',
        'location' => 'EDSA, Quezon City',
        'fuel' => 68,
        'condition' => 'No issue',
        'status' => 'on-trip',
    ],
    [
        'name' => 'Mitsubishi L300',
        'plate' => 'XYZ 1982',
        'driver' => 'Angela Reyes',
        'location' => 'NLEX, Bocaue',
        'fuel' => 18,
        'condition' => 'No issue',
        'status' => 'on-trip',
    ],
    [
        'name' => 'Isuzu N-Series',
        'plate' => 'DEF 1760',
        'driver' => 'Unassigned',
        'location' => 'Fleet garage',
        'fuel' => null,
        'condition' => 'Engine warning',
        'status' => 'maintenance',
    ],
    [
        'name' => 'Toyota Hilux',
        'plate' => 'GHI 2251',
        'driver' => 'Paolo Garcia',
        'location' => 'Fleet garage',
        'fuel' => 74,
        'condition' => 'Body damage',
        'status' => 'damaged',
    ],
    [
        'name' => 'Nissan Navara',
        'plate' => 'JKL 3091',
        'driver' => 'Ramon Cruz',
        'location' => 'Fleet garage',
        'fuel' => 52,
        'condition' => 'Tire damage',
        'status' => 'damaged',
    ],
    [
        'name' => 'Toyota Innova',
        'plate' => 'MNO 4187',
        'driver' => 'Unassigned',
        'location' => 'Fleet garage',
        'fuel' => 82,
        'condition' => 'No issue',
        'status' => 'available',
    ],
];

$matchingVehicles = array_filter($vehicles, static function (array $vehicle) use ($filter, $search, $filters): bool {
    $matchesFilter = match ($filter) {
        'low-fuel' => $vehicle['fuel'] !== null && $vehicle['fuel'] <= 25,
        'attention' => in_array($vehicle['status'], ['maintenance', 'damaged'], true),
        default => $filter === 'all' || $vehicle['status'] === $filter,
    };

    $searchableText = implode(' ', [
        $vehicle['name'],
        $vehicle['plate'],
        $vehicle['driver'],
        $vehicle['location'],
        $vehicle['condition'],
        $filters[$vehicle['status']],
    ]);

    return $matchesFilter && ($search === '' || stripos($searchableText, $search) !== false);
});

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$statusStyles = [
    'on-trip' => 'bg-blue-50 text-blue-700',
    'available' => 'bg-sky-50 text-sky-700',
    'maintenance' => 'bg-amber-50 text-amber-700',
    'damaged' => 'bg-rose-50 text-rose-700',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Monitoring | Head Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#dbeafe] text-slate-900 antialiased">
    <div class="relative isolate min-h-screen overflow-hidden bg-gradient-to-br from-[#dbeafe] via-[#eaf4ff] to-[#cfe4ff] lg:flex">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -right-40 -top-40 h-[32rem] w-[32rem] rounded-full bg-blue-300/50 blur-3xl"></div>
            <div class="absolute -bottom-48 left-1/4 h-[28rem] w-[28rem] rounded-full bg-sky-200/70 blur-3xl"></div>
        </div>

        <aside class="relative z-10 hidden w-64 shrink-0 border-r border-slate-200 bg-white lg:flex lg:flex-col">
            <div class="flex h-20 items-center gap-3 border-b border-slate-100 px-6">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-700 text-white">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.5V18a1 1 0 0 0 1 1h1m15-5.5V18a1 1 0 0 1-1 1h-1M3 13.5l1.5-5A2 2 0 0 1 6.4 7h11.2a2 2 0 0 1 1.9 1.5l1.5 5M3 13.5h18M6 19h12M7 16h.01M17 16h.01M8 7l1-3h6l1 3" /></svg>
                </div>
                <div>
                    <p class="font-bold tracking-tight">Fleet<span class="text-blue-700">Flow</span></p>
                    <p class="text-xs text-slate-500">Management System</p>
                </div>
            </div>
            <nav class="flex-1 space-y-1 px-4 py-6" aria-label="Main navigation">
                <p class="px-3 pb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Organization</p>
                <a href="dashboard.php" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10Zm10 8h8V11h-8v10Zm0-12h8V3h-8v6ZM3 21h8v-6H3v6Z" /></svg>
                    Overview
                </a>
                <a href="fleet.php" aria-current="page" class="flex items-center gap-3 rounded-lg bg-blue-50 px-3 py-2.5 text-sm font-semibold text-blue-800">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.5V18a1 1 0 0 0 1 1h1m15-5.5V18a1 1 0 0 1-1 1h-1M3 13.5l1.5-5A2 2 0 0 1 6.4 7h11.2a2 2 0 0 1 1.9 1.5l1.5 5M3 13.5h18M6 19h12M7 16h.01M17 16h.01" /></svg>
                    Fleet monitoring
                </a>
                <a href="dashboard.php#users" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 19h5v-1a4 4 0 0 0-5.7-3.6M16 19H8m8 0v-1a5 5 0 0 0-10 0v1m2-11a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z" /></svg>
                    User access
                </a>
                <a href="dashboard.php#reports" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 15v-4m4 4V7m4 8v-6" /></svg>
                    Reports
                </a>
            </nav>
            <div class="border-t border-slate-100 p-4">
                <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-800">HA</div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">Head Administrator</p>
                        <p class="text-xs text-slate-500">Organization-wide access</p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="relative z-10 min-w-0 flex-1">
            <header class="flex h-20 items-center justify-between border-b border-slate-200 bg-white px-5 sm:px-8">
                <div>
                    <p class="text-sm text-slate-500"><?= $escape($today) ?></p>
                    <h1 class="text-lg font-bold tracking-tight sm:text-xl">Fleet Monitoring</h1>
                </div>
                <button id="mobile-menu-toggle" type="button" aria-label="Open navigation menu" aria-expanded="false" aria-controls="mobile-menu" class="rounded-lg border border-slate-200 p-2.5 text-slate-600 hover:bg-slate-50 lg:hidden">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            </header>
            <nav id="mobile-menu" class="hidden border-b border-blue-100 bg-white px-4 py-3 lg:hidden" aria-label="Mobile navigation">
                <a href="dashboard.php" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Overview</a>
                <a href="fleet.php" aria-current="page" class="mt-1 block rounded-lg bg-blue-50 px-3 py-2.5 text-sm font-semibold text-blue-800">Fleet monitoring</a>
                <a href="dashboard.php#users" class="mt-1 block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">User access</a>
                <a href="dashboard.php#reports" class="mt-1 block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Reports</a>
            </nav>

            <div class="space-y-6 p-5 sm:p-8">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <a href="dashboard.php" class="text-sm font-semibold text-blue-800 hover:underline">← Back to dashboard</a>
                        <h2 class="mt-3 text-2xl font-bold tracking-tight">Fleet overview</h2>
                        <p class="mt-1 text-sm text-slate-600">Search and review vehicle status, reported condition, fuel, and last known location.</p>
                    </div>
                    <span class="rounded-lg border border-blue-100 bg-white/80 px-3 py-2 text-sm font-semibold text-blue-800">51 vehicles in fleet</span>
                </div>

                <section class="rounded-xl border border-blue-100 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="vehicles-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 id="vehicles-heading" class="font-bold">Vehicle records</h3>
                            <p class="mt-1 text-sm text-slate-500">Showing matching sample records below. Connect the fleet database to load all 51 vehicles.</p>
                        </div>
                    </div>

                    <form method="get" action="fleet.php" class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(12rem,0.65fr)_auto]">
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-semibold text-slate-600">Search vehicles</span>
                            <input type="search" name="q" value="<?= $escape($search) ?>" placeholder="Vehicle, plate, driver, location..." class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-semibold text-slate-600">Filter by status</span>
                            <select name="filter" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                                <?php foreach ($filters as $value => $label): ?>
                                    <option value="<?= $escape($value) ?>" <?= $filter === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="min-h-[42px] flex-1 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 sm:flex-none">Apply filters</button>
                            <a href="fleet.php" class="inline-flex min-h-[42px] items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Reset</a>
                        </div>
                    </form>

                    <div class="mt-5 overflow-x-auto rounded-lg border border-slate-100">
                        <table class="w-full min-w-[900px] text-left text-sm">
                            <thead class="bg-blue-50/70 text-xs uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th scope="col" class="px-5 py-3 font-semibold">Vehicle</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Plate number</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Assigned driver</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Last known location</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Fuel</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Condition</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if ($matchingVehicles === []): ?>
                                    <tr>
                                        <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">No sample vehicles match these filters.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($matchingVehicles as $vehicle): ?>
                                        <?php
                                        $fuelClass = $vehicle['fuel'] !== null && $vehicle['fuel'] <= 25
                                            ? 'font-semibold text-amber-700'
                                            : 'font-medium text-slate-700';
                                        $conditionClass = $vehicle['status'] === 'damaged'
                                            ? 'bg-rose-50 text-rose-700'
                                            : ($vehicle['status'] === 'maintenance' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700');
                                        ?>
                                        <tr class="hover:bg-blue-50/40">
                                            <td class="whitespace-nowrap px-5 py-4 font-semibold"><?= $escape($vehicle['name']) ?></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-slate-600"><?= $escape($vehicle['plate']) ?></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-slate-600"><?= $escape($vehicle['driver']) ?></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-slate-600"><?= $escape($vehicle['location']) ?></td>
                                            <td class="whitespace-nowrap px-5 py-4 <?= $fuelClass ?>"><?= $vehicle['fuel'] === null ? '—' : $escape((string) $vehicle['fuel']) . '%' ?></td>
                                            <td class="whitespace-nowrap px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $conditionClass ?>"><?= $escape($vehicle['condition']) ?></span></td>
                                            <td class="whitespace-nowrap px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusStyles[$vehicle['status']] ?>"><?= $escape($filters[$vehicle['status']]) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Showing <?= count($matchingVehicles) ?> of <?= count($vehicles) ?> sample vehicle records · Fleet total: 51 · Locations and telemetry are illustrative</p>
                </section>
                <footer class="pb-2 text-center text-xs text-slate-500">FleetFlow Head Admin · Sample data, not live vehicle telemetry</footer>
            </div>
        </main>
    </div>
    <script>
        const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuToggle.addEventListener('click', () => {
            const isExpanded = mobileMenuToggle.getAttribute('aria-expanded') === 'true';
            mobileMenuToggle.setAttribute('aria-expanded', String(!isExpanded));
            mobileMenuToggle.setAttribute('aria-label', isExpanded ? 'Open navigation menu' : 'Close navigation menu');
            mobileMenu.classList.toggle('hidden', isExpanded);
        });

        mobileMenu.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                mobileMenu.classList.add('hidden');
                mobileMenuToggle.setAttribute('aria-expanded', 'false');
                mobileMenuToggle.setAttribute('aria-label', 'Open navigation menu');
            });
        });
    </script>
</body>
</html>

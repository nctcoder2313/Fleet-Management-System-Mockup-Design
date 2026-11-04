<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'emergency_overrides';
$pageTitle = 'Emergency trip overrides';

if ($previewMode) {
    $activeTrips = [];
    foreach ($_SESSION['head_admin_preview_data']['trips'] as $trip) {
        if ($trip['status'] !== 'in_progress') {
            continue;
        }
        foreach ($_SESSION['head_admin_preview_data']['vehicles'] as $vehicle) {
            if ((int) $vehicle['id'] === (int) $trip['vehicle_id']) {
                $trip['vehicle_name'] = $vehicle['name'];
                $trip['plate_number'] = $vehicle['plate_number'];
                $trip['vehicle_status'] = $vehicle['status'];
                $trip['location'] = $vehicle['location'];
                break;
            }
        }
        $activeTrips[] = $trip;
    }
    usort($activeTrips, static fn (array $left, array $right): int => strcmp($left['started_at'], $right['started_at']));
} else {
    $activeTrips = $pdo->query(
        "SELECT t.id, t.reference, t.origin, t.destination, t.started_at,
                v.id AS vehicle_id, v.name AS vehicle_name, v.plate_number, v.status AS vehicle_status, v.location,
                u.name AS driver_name
         FROM fleet_trips t
         INNER JOIN fleet_vehicles v ON v.id = t.vehicle_id
         LEFT JOIN users u ON u.id = t.driver_id
         WHERE t.status = 'in_progress'
         ORDER BY t.started_at ASC"
    )->fetchAll();
}

$activeCount = count($activeTrips);
require __DIR__ . '/_layout_start.php';
?>
<section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-sm font-medium text-slate-500">Fleet operations · Data recovery</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Emergency trip overrides</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Use this controlled recovery workflow only when a driver device or trip system cannot close a trip normally. Review the vehicle and route before choosing an override.</p>
    </div>
    <a href="<?= $escape(headAdminUrl('trip_override_history.php')) ?>" class="inline-flex w-fit items-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">View override history</a>
</section>

<section class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950 sm:p-5">
    <p class="font-semibold">Overrides are final trip-state changes.</p>
    <p class="mt-1">Each action requires a reason, records the acting Head Admin and time, and returns the vehicle to Idle unless it is already Under maintenance. A second override cannot be applied to a closed trip.</p>
</section>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
        <h2 class="font-bold text-slate-900"><?= $activeCount ?> active trip<?= $activeCount === 1 ? '' : 's' ?> to review</h2>
        <p class="mt-1 text-sm text-slate-500">Select a trip to review its route, vehicle, driver, and start time before acting.</p>
    </div>
    <?php if (!$activeTrips): ?>
        <div class="p-8 text-center">
            <p class="font-semibold text-slate-800">No trips are currently in progress.</p>
            <p class="mt-1 text-sm text-slate-500">When a trip needs recovery, it will appear here for review.</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($activeTrips as $trip): ?>
                <?php $ageHours = max(0, (int) floor((time() - strtotime($trip['started_at'])) / 3600)); ?>
                <article class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-900"><?= $escape($trip['reference']) ?></h3>
                            <?php if ($ageHours >= 4): ?>
                                <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-800">Active for <?= $ageHours ?> hours</span>
                            <?php else: ?>
                                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">In progress</span>
                            <?php endif; ?>
                        </div>
                        <p class="mt-1 text-sm text-slate-700"><?= $escape($trip['origin']) ?> <span aria-hidden="true">&rarr;</span> <?= $escape($trip['destination']) ?></p>
                        <p class="mt-1 text-sm text-slate-500"><?= $escape($trip['vehicle_name']) ?> · <?= $escape($trip['plate_number']) ?> · Driver: <?= $escape($trip['driver_name'] ?? 'Not assigned') ?></p>
                        <p class="mt-1 text-xs text-slate-500">Started <?= $escape(date('M j, Y g:i A', strtotime($trip['started_at']))) ?> · Vehicle currently <?= $escape(str_replace('_', ' ', $trip['vehicle_status'])) ?></p>
                    </div>
                    <a href="<?= $escape(headAdminUrl('trip_override.php', ['trip_id' => (int) $trip['id']])) ?>" class="inline-flex w-fit shrink-0 items-center rounded-lg bg-amber-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-900">Inspect override</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_layout_end.php'; ?>

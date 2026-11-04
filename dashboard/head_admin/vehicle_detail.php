<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'vehicles';
$pageTitle = 'Vehicle profile';
$vehicleId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$vehicleId) {
    http_response_code(400);
    exit('A valid vehicle ID is required.');
}

$vehicle = null;
$activeTrip = null;
$tripHistory = [];
if ($previewMode) {
    foreach ($_SESSION['head_admin_preview_data']['vehicles'] as $candidate) {
        if ((int) $candidate['id'] === $vehicleId) {
            $vehicle = $candidate;
            break;
        }
    }
    if ($vehicle) {
        foreach ($_SESSION['head_admin_preview_data']['trips'] as $trip) {
            if ((int) $trip['vehicle_id'] !== $vehicleId) {
                continue;
            }
            $tripHistory[] = $trip;
            if ($trip['status'] === 'in_progress') {
                $activeTrip = $trip;
            }
        }
        usort($tripHistory, static fn (array $left, array $right): int => strcmp($right['started_at'], $left['started_at']));
    }
} else {
    $lookup = $pdo->prepare(
        'SELECT id, name, plate_number, location, condition_note, fuel_level_percent, status, created_at, updated_at
         FROM fleet_vehicles WHERE id = :id LIMIT 1'
    );
    $lookup->execute(['id' => $vehicleId]);
    $vehicle = $lookup->fetch() ?: null;
    if ($vehicle) {
        $activeLookup = $pdo->prepare(
            "SELECT t.id, t.reference, t.origin, t.destination, t.status, t.started_at, t.ended_at,
                    t.override_action, t.override_reason, u.name AS driver_name
             FROM fleet_trips t
             LEFT JOIN users u ON u.id = t.driver_id
             WHERE t.vehicle_id = :vehicle_id AND t.status = 'in_progress'
             LIMIT 1"
        );
        $activeLookup->execute(['vehicle_id' => $vehicleId]);
        $activeTrip = $activeLookup->fetch() ?: null;

        $historyLookup = $pdo->prepare(
            'SELECT t.id, t.reference, t.origin, t.destination, t.status, t.started_at, t.ended_at,
                    t.override_action, t.override_reason, u.name AS driver_name
             FROM fleet_trips t
             LEFT JOIN users u ON u.id = t.driver_id
             WHERE t.vehicle_id = :vehicle_id
             ORDER BY t.started_at DESC
             LIMIT 20'
        );
        $historyLookup->execute(['vehicle_id' => $vehicleId]);
        $tripHistory = $historyLookup->fetchAll();
    }
}

if (!$vehicle) {
    http_response_code(404);
    exit('Vehicle not found.');
}

$vehicleStatusLabels = ['on_road' => 'On the road', 'idle' => 'Idle', 'maintenance' => 'Under maintenance'];
$tripStatusLabels = ['in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
$activePage = 'vehicles';

require __DIR__ . '/_layout_start.php';
?>
<div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-sm font-medium text-slate-500">Fleet operations · Vehicle profile</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900"><?= $escape($vehicle['name']) ?></h2>
        <p class="mt-1 text-sm text-slate-600"><?= $escape($vehicle['plate_number']) ?> · <?= $escape($vehicleStatusLabels[$vehicle['status']] ?? $vehicle['status']) ?></p>
    </div>
    <a href="<?= $escape(headAdminUrl('vehicles.php')) ?>" class="text-sm font-semibold text-blue-700 hover:underline">Back to vehicle directory</a>
</div>

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ([
        ['label' => 'Current status', 'value' => $vehicleStatusLabels[$vehicle['status']] ?? $vehicle['status']],
        ['label' => 'Reported location', 'value' => $vehicle['location']],
        ['label' => 'Fuel level', 'value' => $vehicle['fuel_level_percent'] === null ? 'Not reported' : (int) $vehicle['fuel_level_percent'] . '%'],
        ['label' => 'Reported condition', 'value' => $vehicle['condition_note'] !== '' ? $vehicle['condition_note'] : 'No condition report'],
    ] as $detail): ?>
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500"><?= $escape($detail['label']) ?></p>
            <p class="mt-2 font-semibold text-slate-900"><?= $escape($detail['value']) ?></p>
        </article>
    <?php endforeach; ?>
</section>

<?php if ($activeTrip): ?>
    <section class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-amber-900">Active trip requires a live status</p>
                <h2 class="mt-1 text-lg font-bold text-slate-900"><?= $escape($activeTrip['reference']) ?></h2>
                <p class="mt-2 text-sm text-slate-700"><?= $escape($activeTrip['origin']) ?> <span aria-hidden="true">&rarr;</span> <?= $escape($activeTrip['destination']) ?></p>
                <p class="mt-1 text-sm text-slate-600">Driver: <?= $escape($activeTrip['driver_name'] ?? 'Not assigned') ?> · Started <?= $escape(date('M j, Y g:i A', strtotime($activeTrip['started_at']))) ?></p>
            </div>
            <a href="<?= $escape(headAdminUrl('trip_override.php', ['trip_id' => (int) $activeTrip['id']])) ?>" class="inline-flex w-fit shrink-0 items-center rounded-lg bg-amber-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-900">Review trip override</a>
        </div>
    </section>
<?php else: ?>
    <section class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">This vehicle has no trip currently in progress.</section>
<?php endif; ?>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
        <h2 class="font-bold text-slate-900">Trip history</h2>
        <p class="mt-1 text-sm text-slate-500">Most recent trips recorded for this vehicle.</p>
    </div>
    <?php if (!$tripHistory): ?>
        <p class="p-8 text-center text-sm text-slate-500">No trip records are available for this vehicle.</p>
    <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($tripHistory as $trip): ?>
                <article class="px-5 py-4 sm:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-900"><?= $escape($trip['reference']) ?></p>
                            <p class="mt-1 text-sm text-slate-600"><?= $escape($trip['origin']) ?> <span aria-hidden="true">&rarr;</span> <?= $escape($trip['destination']) ?></p>
                            <p class="mt-1 text-xs text-slate-500">Driver: <?= $escape($trip['driver_name'] ?? 'Not assigned') ?> · Started <?= $escape(date('M j, Y g:i A', strtotime($trip['started_at']))) ?></p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"><?= $escape($tripStatusLabels[$trip['status']] ?? $trip['status']) ?></span>
                    </div>
                    <?php if ($trip['override_action'] !== null): ?>
                        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950">
                            Head Admin override: <?= $trip['override_action'] === 'force_complete' ? 'Force completed' : 'Cancelled' ?><?= $trip['ended_at'] ? ' · ' . $escape(date('M j, Y g:i A', strtotime($trip['ended_at']))) : '' ?>.
                            Reason: <?= $escape($trip['override_reason'] ?? 'Not recorded') ?>
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_layout_end.php'; ?>

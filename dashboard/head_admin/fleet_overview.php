<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'fleet_overview';
$pageTitle = 'Fleet operations';

$counts = ['total' => 0, 'on_road' => 0, 'idle' => 0, 'maintenance' => 0];
if ($previewMode) {
    foreach ($_SESSION['head_admin_preview_data']['vehicles'] as $vehicle) {
        $counts['total']++;
        if (array_key_exists($vehicle['status'], $counts)) {
            $counts[$vehicle['status']]++;
        }
    }
    $openTripCount = count(array_filter(
        $_SESSION['head_admin_preview_data']['trips'],
        static fn (array $trip): bool => $trip['status'] === 'in_progress'
    ));
} else {
    $statusCounts = $pdo->query(
        'SELECT status, COUNT(*) AS total
         FROM fleet_vehicles
         GROUP BY status'
    )->fetchAll();
    foreach ($statusCounts as $row) {
        $status = (string) $row['status'];
        $count = (int) $row['total'];
        if (array_key_exists($status, $counts)) {
            $counts[$status] = $count;
            $counts['total'] += $count;
        }
    }
    $openTripCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM fleet_trips WHERE status = 'in_progress'"
    )->fetchColumn();
}

$statusCards = [
    ['label' => 'Total vehicles', 'status' => 'all', 'count' => $counts['total'], 'description' => 'Every registered fleet vehicle', 'style' => 'border-slate-200 text-slate-900'],
    ['label' => 'On the road', 'status' => 'on_road', 'count' => $counts['on_road'], 'description' => 'Vehicles currently assigned to a trip', 'style' => 'border-blue-200 text-blue-900'],
    ['label' => 'Idle', 'status' => 'idle', 'count' => $counts['idle'], 'description' => 'Vehicles ready for dispatch', 'style' => 'border-emerald-200 text-emerald-900'],
    ['label' => 'Under maintenance', 'status' => 'maintenance', 'count' => $counts['maintenance'], 'description' => 'Vehicles unavailable for dispatch', 'style' => 'border-amber-200 text-amber-950'],
];

require __DIR__ . '/_layout_start.php';
?>
<section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-sm font-medium text-slate-500"><?= $escape(date('l, F j, Y')) ?></p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Fleet operations</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">A dedicated Head Admin view of current vehicle availability and trip recovery actions.</p>
    </div>
    <a href="<?= $escape(headAdminUrl('emergency_overrides.php')) ?>" class="inline-flex w-fit items-center rounded-lg bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-800">Review active trips <span class="ml-2 rounded-full bg-white/15 px-2 py-0.5"><?= $openTripCount ?></span></a>
</section>

<section aria-label="Fleet status summary" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($statusCards as $card): ?>
        <a href="<?= $escape(headAdminUrl('vehicles.php', ['status' => $card['status']])) ?>" class="group rounded-xl border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md <?= $escape($card['style']) ?>">
            <span class="block text-sm font-semibold"><?= $escape($card['label']) ?></span>
            <span class="mt-3 block text-3xl font-bold"><?= (int) $card['count'] ?></span>
            <span class="mt-2 block text-sm text-slate-500"><?= $escape($card['description']) ?></span>
            <span class="mt-4 inline-flex text-xs font-semibold text-blue-700 group-hover:underline">View matching vehicles <span aria-hidden="true" class="ml-1">&rarr;</span></span>
        </a>
    <?php endforeach; ?>
</section>

<section class="grid gap-4 lg:grid-cols-2">
    <a href="<?= $escape(headAdminUrl('vehicles.php')) ?>" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md sm:p-6">
        <p class="text-sm font-semibold text-blue-700">Vehicle directory</p>
        <h2 class="mt-2 text-lg font-bold text-slate-900">Inspect fleet vehicles</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">Search by vehicle, plate, location, driver, or current condition. Open a vehicle profile to see its active trip and trip history.</p>
        <span class="mt-4 inline-flex text-sm font-semibold text-blue-700 group-hover:underline">Open vehicle directory <span aria-hidden="true" class="ml-1">&rarr;</span></span>
    </a>
    <a href="<?= $escape(headAdminUrl('emergency_overrides.php')) ?>" class="group rounded-xl border border-amber-200 bg-white p-5 shadow-sm transition hover:border-amber-300 hover:shadow-md sm:p-6">
        <p class="text-sm font-semibold text-amber-800">Emergency trip recovery</p>
        <h2 class="mt-2 text-lg font-bold text-slate-900"><?= (int) $openTripCount ?> trip<?= $openTripCount === 1 ? '' : 's' ?> currently in progress</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">Inspect a live trip before force-completing or cancelling it. Each override requires a reason and is written to the audit trail.</p>
        <span class="mt-4 inline-flex text-sm font-semibold text-amber-800 group-hover:underline">Open emergency overrides <span aria-hidden="true" class="ml-1">&rarr;</span></span>
    </a>
</section>

<?php if (!$previewMode && $counts['total'] === 0): ?>
    <section class="rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm leading-6 text-blue-950">
        <h2 class="font-bold">Fleet records are ready to connect</h2>
        <p class="mt-1">No vehicles are registered yet. Apply the fleet operations migration and connect the fleet's verified vehicle and trip records; this dashboard does not insert demonstration vehicles into company data.</p>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>

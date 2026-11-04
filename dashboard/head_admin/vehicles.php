<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'vehicles';
$pageTitle = 'Vehicle directory';
$statusLabels = [
    'all' => 'All vehicles',
    'on_road' => 'On the road',
    'idle' => 'Idle',
    'maintenance' => 'Under maintenance',
];
$requestedStatus = $_GET['status'] ?? 'all';
if (!is_string($requestedStatus) || !array_key_exists($requestedStatus, $statusLabels)) {
    http_response_code(400);
    exit('Invalid vehicle status filter.');
}
$statusFilter = $requestedStatus;
$requestedSearch = $_GET['q'] ?? '';
$search = is_string($requestedSearch) ? trim($requestedSearch) : '';
$vehicles = [];

if ($previewMode) {
    $tripsByVehicle = [];
    foreach ($_SESSION['head_admin_preview_data']['trips'] as $trip) {
        if ($trip['status'] === 'in_progress') {
            $tripsByVehicle[$trip['vehicle_id']] = $trip;
        }
    }
    $vehicles = array_values(array_filter(
        $_SESSION['head_admin_preview_data']['vehicles'],
        static function (array $vehicle) use ($statusFilter, $search, $tripsByVehicle): bool {
            $matchesStatus = $statusFilter === 'all' || $vehicle['status'] === $statusFilter;
            $trip = $tripsByVehicle[$vehicle['id']] ?? null;
            $searchable = implode(' ', [
                $vehicle['name'],
                $vehicle['plate_number'],
                $vehicle['location'],
                $vehicle['condition_note'],
                $trip['driver_name'] ?? '',
            ]);
            return $matchesStatus && ($search === '' || stripos($searchable, $search) !== false);
        }
    ));
    usort($vehicles, static fn (array $left, array $right): int => strcmp($left['plate_number'], $right['plate_number']));
    foreach ($vehicles as &$vehicle) {
        $vehicle['driver_name'] = $tripsByVehicle[$vehicle['id']]['driver_name'] ?? null;
        $vehicle['trip_reference'] = $tripsByVehicle[$vehicle['id']]['reference'] ?? null;
        $vehicle['trip_started_at'] = $tripsByVehicle[$vehicle['id']]['started_at'] ?? null;
    }
    unset($vehicle);
} else {
    $conditions = [];
    $parameters = [];
    if ($statusFilter !== 'all') {
        $conditions[] = 'v.status = :status';
        $parameters['status'] = $statusFilter;
    }
    if ($search !== '') {
        $conditions[] = '(v.name LIKE :name_search OR v.plate_number LIKE :plate_search OR v.location LIKE :location_search OR v.condition_note LIKE :condition_search OR u.name LIKE :driver_search)';
        foreach (['name_search', 'plate_search', 'location_search', 'condition_search', 'driver_search'] as $parameter) {
            $parameters[$parameter] = '%' . $search . '%';
        }
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $statement = $pdo->prepare(
        "SELECT v.id, v.name, v.plate_number, v.location, v.condition_note, v.fuel_level_percent, v.status,
                t.reference AS trip_reference, t.started_at AS trip_started_at, u.name AS driver_name
         FROM fleet_vehicles v
         LEFT JOIN fleet_trips t ON t.vehicle_id = v.id AND t.status = 'in_progress'
         LEFT JOIN users u ON u.id = t.driver_id
         {$where}
         ORDER BY v.plate_number ASC"
    );
    $statement->execute($parameters);
    $vehicles = $statement->fetchAll();
}

$statusBadges = [
    'on_road' => 'bg-blue-50 text-blue-800',
    'idle' => 'bg-emerald-50 text-emerald-800',
    'maintenance' => 'bg-amber-50 text-amber-900',
];
$requestedStatusLabel = $statusLabels[$statusFilter];

require __DIR__ . '/_layout_start.php';
?>
<section>
    <p class="text-sm font-medium text-slate-500">Fleet operations · <?= $escape($requestedStatusLabel) ?></p>
    <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900"><?= $escape($requestedStatusLabel) ?></h2>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Search and inspect vehicle status, current assignment, location, and reported condition. Select a vehicle to open its dedicated profile and trip history.</p>
</section>

<section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <form method="get" class="grid gap-3 sm:grid-cols-[minmax(12rem,1fr)_minmax(12rem,2fr)_auto]">
        <label class="sr-only" for="vehicle-status-filter">Filter by vehicle status</label>
        <select id="vehicle-status-filter" name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
            <?php foreach ($statusLabels as $value => $label): ?>
                <option value="<?= $escape($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($previewMode): ?><input type="hidden" name="preview" value="1"><?php endif; ?>
        <label class="sr-only" for="vehicle-search">Search vehicles</label>
        <input id="vehicle-search" type="search" name="q" value="<?= $escape($search) ?>" placeholder="Search vehicle, plate, location, driver, or condition" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
        <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Apply filters</button>
    </form>
</section>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
        <div>
            <h2 class="font-bold text-slate-900"><?= count($vehicles) ?> vehicle<?= count($vehicles) === 1 ? '' : 's' ?> found</h2>
            <p class="mt-1 text-sm text-slate-500">Vehicle assignments and trip status are shown from the current fleet records.</p>
        </div>
        <a href="<?= $escape(headAdminUrl('fleet_overview.php')) ?>" class="text-sm font-semibold text-blue-700 hover:underline">Back to fleet summary</a>
    </div>
    <?php if (!$vehicles): ?>
        <p class="p-8 text-center text-sm text-slate-500">No vehicles match this view. Try another status or search term.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-semibold sm:px-6">Vehicle</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Current trip / driver</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Location</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Condition</th>
                        <th scope="col" class="px-5 py-3 font-semibold sm:px-6">Profile</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($vehicles as $vehicle): ?>
                        <tr class="align-top">
                            <td class="px-5 py-4 sm:px-6">
                                <span class="block font-semibold text-slate-900"><?= $escape($vehicle['name']) ?></span>
                                <span class="mt-1 block text-xs text-slate-500"><?= $escape($vehicle['plate_number']) ?><?= $vehicle['fuel_level_percent'] === null ? '' : ' · ' . (int) $vehicle['fuel_level_percent'] . '% fuel' ?></span>
                            </td>
                            <td class="px-4 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= $escape($statusBadges[$vehicle['status']] ?? 'bg-slate-100 text-slate-700') ?>"><?= $escape($statusLabels[$vehicle['status']] ?? $vehicle['status']) ?></span></td>
                            <td class="px-4 py-4">
                                <?php if ($vehicle['trip_reference']): ?>
                                    <span class="block font-medium text-slate-800"><?= $escape($vehicle['trip_reference']) ?></span>
                                    <span class="mt-1 block text-xs text-slate-500"><?= $escape($vehicle['driver_name'] ?? 'Driver not assigned') ?></span>
                                <?php else: ?>
                                    <span class="text-slate-400">No active trip</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 text-slate-600"><?= $escape($vehicle['location']) ?></td>
                            <td class="max-w-56 px-4 py-4 text-slate-600"><?= $escape($vehicle['condition_note']) ?></td>
                            <td class="px-5 py-4 sm:px-6"><a href="<?= $escape(headAdminUrl('vehicle_detail.php', ['id' => (int) $vehicle['id']])) ?>" class="font-semibold text-blue-700 hover:underline">Open profile</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_layout_end.php'; ?>

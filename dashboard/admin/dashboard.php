<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'dashboard';
$pageTitle = 'Admin dashboard';

if ($previewMode) {
    $vehicles = $_SESSION['admin_preview_vehicles'];
    $vehicleCount = count($vehicles);
    $onRoadCount = count(array_filter(
        $vehicles,
        static fn (array $vehicle): bool => ($vehicle['status'] ?? '') === 'on_road'
    ));
    $documents = $_SESSION['admin_document_preview'];
    $maintenanceRecords = $_SESSION['admin_maintenance_preview'];
    $geofenceRules = $_SESSION['admin_geofence_preview'];
    $equipmentRecords = $_SESSION['admin_equipment_preview'];
    $vendorCount = count($_SESSION['admin_vendor_preview']);
    $complianceAttentionTotal = count(array_filter(
        $documents,
        static fn (array $document): bool => $document['expiry_date'] <= date('Y-m-d', strtotime('+30 days'))
    ));
    $openMaintenanceCount = count(array_filter(
        $maintenanceRecords,
        static fn (array $record): bool => in_array($record['service_status'], ['Scheduled', 'In-Progress'], true)
    ));
    $overdueMaintenanceCount = count(array_filter(
        $maintenanceRecords,
        static fn (array $record): bool => in_array($record['service_status'], ['Scheduled', 'In-Progress'], true)
            && $record['scheduled_date'] < date('Y-m-d')
    ));
    $inProgressMaintenanceCount = count(array_filter(
        $maintenanceRecords,
        static fn (array $record): bool => $record['service_status'] === 'In-Progress'
    ));
    $equipmentAttentionCount = count(array_filter(
        $equipmentRecords,
        static fn (array $record): bool => $record['status'] === 'Needs Service'
    ));
} else {
    $vehicleSummary = $pdo->query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status = 'on_road'), 0) AS on_road
         FROM fleet_vehicles"
    )->fetch();
    $vehicleCount = (int) $vehicleSummary['total'];
    $onRoadCount = (int) $vehicleSummary['on_road'];

    $documents = $pdo->query(
        "SELECT d.id, d.vehicle_id, d.plate_number, v.name AS vehicle_name,
                d.document_type, d.document_no, d.expiry_date, d.notes,
                CASE
                    WHEN d.expiry_date < CURRENT_DATE THEN 'Expired'
                    WHEN d.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN 'Expiring Soon'
                    ELSE 'Valid'
                END AS current_status
         FROM vehicle_documents d
         JOIN fleet_vehicles v ON v.id = d.vehicle_id
         WHERE d.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
         ORDER BY d.expiry_date ASC
         LIMIT 6"
    )->fetchAll();
    $complianceAttentionTotal = (int) $pdo->query(
        'SELECT COUNT(*)
         FROM vehicle_documents
         WHERE expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)'
    )->fetchColumn();

    $maintenanceRecords = $pdo->query(
        "SELECT m.id, m.vehicle_id, m.plate_number, v.name AS vehicle_name,
                m.service_type, m.scheduled_date, m.completion_date,
                m.mechanic_or_shop, m.parts_replaced, m.service_status, m.notes
         FROM maintenance_logs m
         JOIN fleet_vehicles v ON v.id = m.vehicle_id
         WHERE m.service_status IN ('Scheduled', 'In-Progress')
         ORDER BY CASE WHEN m.scheduled_date < CURRENT_DATE THEN 0 ELSE 1 END,
                  m.scheduled_date ASC
         LIMIT 8"
    )->fetchAll();
    $upcomingMaintenance = $pdo->query(
        "SELECT m.id, m.vehicle_id, m.plate_number, v.name AS vehicle_name,
                m.service_type, m.scheduled_date, m.completion_date,
                m.mechanic_or_shop, m.parts_replaced, m.service_status, m.notes
         FROM maintenance_logs m
         JOIN fleet_vehicles v ON v.id = m.vehicle_id
         WHERE m.service_status IN ('Scheduled', 'In-Progress')
           AND m.scheduled_date >= CURRENT_DATE
         ORDER BY m.scheduled_date ASC
         LIMIT 5"
    )->fetchAll();
    $maintenanceSummary = $pdo->query(
        "SELECT COUNT(*) AS open_total,
                COALESCE(SUM(service_status IN ('Scheduled', 'In-Progress')
                    AND scheduled_date < CURRENT_DATE), 0) AS overdue_total,
                COALESCE(SUM(service_status = 'In-Progress'), 0) AS in_progress_total
         FROM maintenance_logs
         WHERE service_status IN ('Scheduled', 'In-Progress')"
    )->fetch();
    $openMaintenanceCount = (int) $maintenanceSummary['open_total'];
    $overdueMaintenanceCount = (int) $maintenanceSummary['overdue_total'];
    $inProgressMaintenanceCount = (int) $maintenanceSummary['in_progress_total'];

    $geofenceRules = $pdo->query(
        "SELECT id, zone_name, status
         FROM geofence_rules
         ORDER BY status ASC, zone_name ASC"
    )->fetchAll();

    $equipmentRecords = $pdo->query(
        "SELECT e.id, e.vehicle_id, e.equipment_type, e.serial_number, e.status,
                v.name AS vehicle_name, v.plate_number
         FROM vehicle_equipment e
         JOIN fleet_vehicles v ON v.id = e.vehicle_id
         WHERE e.status = 'Needs Service'
         ORDER BY e.updated_at DESC
         LIMIT 5"
    )->fetchAll();
    $equipmentAttentionCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM vehicle_equipment WHERE status = 'Needs Service'"
    )->fetchColumn();

    $vendorCount = (int) $pdo->query('SELECT COUNT(*) FROM accredited_vendors')->fetchColumn();
}

$today = new DateTimeImmutable('today');
$documentAttention = [];
foreach ($documents as $document) {
    if ($previewMode) {
        $daysRemaining = (int) $today->diff(new DateTimeImmutable($document['expiry_date']))->format('%r%a');
        $document['current_status'] = $daysRemaining < 0
            ? 'Expired'
            : ($daysRemaining <= 30 ? 'Expiring Soon' : 'Valid');
    }
    if ($document['current_status'] !== 'Valid') {
        $document['days_remaining'] = (int) $today->diff(new DateTimeImmutable($document['expiry_date']))->format('%r%a');
        $documentAttention[] = $document;
    }
}
$complianceAttentionCount = $complianceAttentionTotal;

$maintenanceOpen = array_values(array_filter(
    $maintenanceRecords,
    static fn (array $record): bool => in_array($record['service_status'], ['Scheduled', 'In-Progress'], true)
));
if ($previewMode) {
    $upcomingMaintenance = array_values(array_filter(
        $maintenanceOpen,
        static fn (array $record): bool => $record['scheduled_date'] >= date('Y-m-d')
    ));
}
$overdueMaintenance = array_values(array_filter(
    $maintenanceOpen,
    static fn (array $record): bool => $record['scheduled_date'] < date('Y-m-d')
));
$equipmentAttention = $previewMode
    ? array_values(array_filter($equipmentRecords, static fn (array $record): bool => $record['status'] === 'Needs Service'))
    : $equipmentRecords;
$activeGeofenceCount = count(array_filter(
    $geofenceRules,
    static fn (array $rule): bool => $rule['status'] === 'Active'
));
$disabledGeofenceCount = count($geofenceRules) - $activeGeofenceCount;
$attentionCount = $complianceAttentionTotal + $overdueMaintenanceCount + $equipmentAttentionCount;
$shownDocumentCount = min(4, count($documentAttention));
$shownOverdueCount = min(count($overdueMaintenance), max(0, 4 - $shownDocumentCount));
$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

$statusTone = static fn (string $status): string => match ($status) {
    'Expired', 'Overdue', 'Needs Service' => 'bg-red-100 text-red-800',
    'Expiring Soon', 'Scheduled' => 'bg-amber-100 text-amber-800',
    'In-Progress' => 'bg-blue-100 text-blue-800',
    default => 'bg-slate-100 text-slate-700',
};
$equipmentPageUrl = $adminPageUrl('vendors_equipment.php') . ($previewMode ? '&tab=equipment' : '?tab=equipment');

require __DIR__ . '/_layout_start.php';
?>
<section class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-end sm:justify-between sm:p-7">
    <div>
        <p class="text-sm font-medium text-slate-500"><?= $escape(date('l, F j, Y')) ?></p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Welcome, <?= $escape($admin['name']) ?></h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-600">Your operational overview for vehicle readiness, compliance, maintenance, and fleet equipment.</p>
    </div>
    <a href="<?= $escape($adminPageUrl('maintenance.php')) ?>" class="inline-flex w-fit items-center gap-2 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">
        Schedule maintenance <span aria-hidden="true">→</span>
    </a>
</section>

<section aria-label="Fleet operations summary" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <a href="<?= $escape($equipmentPageUrl) ?>" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md">
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-medium text-slate-500">Fleet vehicles</p>
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-blue-50 text-blue-700" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 13.5 5.2 7h13.6l2.2 6.5v5h-2v-2H5v2H3v-5ZM5 13h14M7 16h.01M17 16h.01"/></svg>
            </span>
        </div>
        <p class="mt-3 text-3xl font-bold text-slate-900"><?= $vehicleCount ?></p>
        <p class="mt-1 text-xs text-slate-500"><?= $onRoadCount ?> currently on road</p>
        <span class="mt-3 inline-flex text-xs font-semibold text-blue-700 group-hover:underline">View vehicle equipment <span class="ml-1" aria-hidden="true">→</span></span>
    </a>
    <?php if (!$previewMode): ?>
    <a href="<?= $escape($adminPageUrl('compliance.php')) ?>" class="group rounded-xl border <?= $complianceAttentionCount > 0 ? 'border-amber-200 bg-amber-50/50' : 'border-slate-200 bg-white' ?> p-5 shadow-sm transition hover:border-amber-300 hover:shadow-md">
    <?php else: ?>
    <div class="rounded-xl border <?= $complianceAttentionCount > 0 ? 'border-amber-200 bg-amber-50/50' : 'border-slate-200 bg-white' ?> p-5 shadow-sm">
    <?php endif; ?>
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-medium text-slate-500">Documents needing attention</p>
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-amber-100 text-amber-800" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3.75h7l4 4v12.5H7a2 2 0 0 1-2-2v-12.5a2 2 0 0 1 2-2ZM14 4v4h4M8 13h8M8 16h6"/></svg>
            </span>
        </div>
        <p class="mt-3 text-3xl font-bold text-slate-900"><?= $complianceAttentionCount ?></p>
        <p class="mt-1 text-xs text-slate-500">Expired or expiring within 30 days</p>
        <?php if (!$previewMode): ?>
            <span class="mt-3 inline-flex text-xs font-semibold text-blue-700 group-hover:underline">Review compliance <span class="ml-1" aria-hidden="true">→</span></span>
        <?php else: ?>
            <span class="mt-3 block text-xs text-slate-500">Compliance preview metrics · full page requires Admin sign-in</span>
        <?php endif; ?>
    <?php if (!$previewMode): ?></a><?php else: ?></div><?php endif; ?>
    <a href="<?= $escape($adminPageUrl('maintenance.php')) ?>" class="group rounded-xl border <?= $overdueMaintenance ? 'border-red-200 bg-red-50/50' : 'border-slate-200 bg-white' ?> p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md">
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-medium text-slate-500">Open maintenance jobs</p>
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-blue-50 text-blue-700" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m14.7 6.3 3-3a5 5 0 0 1-6.5 6.5L5 16a2.1 2.1 0 1 0 3 3l6.2-6.2a5 5 0 0 0 6.5-6.5l-3 3-3-3Z"/></svg>
            </span>
        </div>
        <p class="mt-3 text-3xl font-bold text-slate-900"><?= $openMaintenanceCount ?></p>
        <p class="mt-1 text-xs text-slate-500"><?= $overdueMaintenanceCount ?> overdue · <?= $inProgressMaintenanceCount ?> in progress</p>
        <span class="mt-3 inline-flex text-xs font-semibold text-blue-700 group-hover:underline">Open service schedule <span class="ml-1" aria-hidden="true">→</span></span>
    </a>
    <a href="<?= $escape($adminPageUrl('geofencing.php')) ?>" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow-md">
        <div class="flex items-start justify-between gap-3">
            <p class="text-sm font-medium text-slate-500">Active geofence zones</p>
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-50 text-emerald-700" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-5.2 7-12a7 7 0 1 0-14 0c0 6.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>
            </span>
        </div>
        <p class="mt-3 text-3xl font-bold text-slate-900"><?= $activeGeofenceCount ?></p>
        <p class="mt-1 text-xs text-slate-500"><?= $disabledGeofenceCount ?> disabled zone<?= $disabledGeofenceCount === 1 ? '' : 's' ?></p>
        <span class="mt-3 inline-flex text-xs font-semibold text-blue-700 group-hover:underline">Manage zone rules <span class="ml-1" aria-hidden="true">→</span></span>
    </a>
</section>

<section class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(19rem,0.8fr)]">
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Action center</h2>
                <p class="mt-1 text-sm text-slate-500"><?= $attentionCount > 0 ? $attentionCount . ' item' . ($attentionCount === 1 ? '' : 's') . ' need follow-up' : 'No urgent follow-ups right now' ?></p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $attentionCount > 0 ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-800' ?>"><?= $attentionCount > 0 ? 'Needs attention' : 'All clear' ?></span>
        </div>
        <div class="divide-y divide-slate-100">
            <?php foreach (array_slice($documentAttention, 0, 4) as $document): ?>
                <?php
                $documentStatus = $document['current_status'];
                $documentDetail = $documentStatus === 'Expired'
                    ? abs((int) $document['days_remaining']) . ' day' . (abs((int) $document['days_remaining']) === 1 ? '' : 's') . ' overdue'
                    : 'Expires in ' . (int) $document['days_remaining'] . ' day' . ((int) $document['days_remaining'] === 1 ? '' : 's');
                ?>
                <?php if (!$previewMode): ?>
                <a href="<?= $escape($adminPageUrl('compliance.php')) ?>" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50 sm:px-6">
                <?php else: ?>
                <div class="flex items-center justify-between gap-4 px-5 py-4 sm:px-6">
                <?php endif; ?>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-900"><?= $escape($document['plate_number']) ?> · <?= $escape($document['document_type']) ?></span>
                        <span class="mt-1 block text-xs text-slate-500"><?= $escape($document['vehicle_name']) ?> · <?= $escape($documentDetail) ?></span>
                    </span>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusTone($documentStatus) ?>"><?= $escape($documentStatus) ?></span>
                <?php if (!$previewMode): ?></a><?php else: ?></div><?php endif; ?>
            <?php endforeach; ?>
            <?php foreach (array_slice($overdueMaintenance, 0, $shownOverdueCount) as $record): ?>
                <a href="<?= $escape($adminPageUrl('maintenance.php')) ?>" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50 sm:px-6">
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-900"><?= $escape($record['plate_number']) ?> · <?= $escape($record['service_type']) ?></span>
                        <span class="mt-1 block text-xs text-slate-500"><?= $escape($record['vehicle_name']) ?> · Scheduled <?= $escape($record['scheduled_date']) ?></span>
                    </span>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusTone('Overdue') ?>">Overdue</span>
                </a>
            <?php endforeach; ?>
            <?php foreach (array_slice($equipmentAttention, 0, max(0, 4 - $shownDocumentCount - $shownOverdueCount)) as $item): ?>
                <a href="<?= $escape($equipmentPageUrl) ?>" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50 sm:px-6">
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-900"><?= $escape($item['equipment_type']) ?> · <?= $escape($item['serial_number']) ?></span>
                        <span class="mt-1 block text-xs text-slate-500"><?= $escape($item['plate_number'] ?? '') ?> · <?= $escape($item['vehicle_name'] ?? '') ?></span>
                    </span>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusTone('Needs Service') ?>">Needs service</span>
                </a>
            <?php endforeach; ?>
            <?php if ($attentionCount === 0): ?>
                <div class="px-5 py-10 text-center sm:px-6">
                    <span class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-emerald-50 text-emerald-700" aria-hidden="true">✓</span>
                    <p class="mt-3 text-sm font-semibold text-slate-900">You’re up to date</p>
                    <p class="mt-1 text-sm text-slate-500">No expired documents, overdue maintenance, or equipment service flags.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Fleet readiness</h2>
                    <p class="mt-1 text-sm text-slate-500">Vehicles currently on the road</p>
                </div>
                <span class="text-sm font-bold text-slate-900"><?= $vehicleCount > 0 ? (int) round($onRoadCount / $vehicleCount * 100) : 0 ?>%</span>
            </div>
            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Vehicles currently on the road" aria-valuenow="<?= $vehicleCount > 0 ? (int) round($onRoadCount / $vehicleCount * 100) : 0 ?>" aria-valuemin="0" aria-valuemax="100">
                <span class="block h-full rounded-full bg-blue-600 transition-all" style="width: <?= $vehicleCount > 0 ? min(100, (int) round($onRoadCount / $vehicleCount * 100)) : 0 ?>%"></span>
            </div>
            <div class="mt-3 flex justify-between text-xs text-slate-500">
                <span><?= $onRoadCount ?> on road</span>
                <span><?= max(0, $vehicleCount - $onRoadCount) ?> not on road</span>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Fleet directory</h2>
                    <p class="mt-1 text-sm text-slate-500"><?= $vendorCount ?> accredited repair shop<?= $vendorCount === 1 ? '' : 's' ?> available</p>
                </div>
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-blue-50 text-blue-700" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M15 10h.01M9 14h.01M15 14h.01M10 21v-4h4v4"/></svg>
                </span>
            </div>
            <a href="<?= $escape($adminPageUrl('vendors_equipment.php')) ?>" class="mt-4 inline-flex text-sm font-semibold text-blue-700 hover:underline">Open vendors & equipment <span class="ml-1" aria-hidden="true">→</span></a>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Quick access</h2>
                    <p class="mt-1 text-sm text-slate-500">Common Admin workspaces</p>
                </div>
            </div>
            <nav class="mt-4 grid gap-2" aria-label="Admin quick access">
                <?php if (!$previewMode): ?>
                        <a href="<?= $escape($adminPageUrl('compliance.php')) ?>" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">Document compliance <span class="float-right text-slate-400" aria-hidden="true">→</span></a>
                <?php endif; ?>
                <a href="<?= $escape($adminPageUrl('maintenance.php')) ?>" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">Maintenance schedule <span class="float-right text-slate-400" aria-hidden="true">→</span></a>
                <a href="<?= $escape($adminPageUrl('geofencing.php')) ?>" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">Geofencing & speed limits <span class="float-right text-slate-400" aria-hidden="true">→</span></a>
                <a href="<?= $escape($adminPageUrl('vendors_equipment.php')) ?>" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">Vendors & equipment <span class="float-right text-slate-400" aria-hidden="true">→</span></a>
            </nav>
        </section>
    </aside>
</section>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Upcoming maintenance</h2>
            <p class="mt-1 text-sm text-slate-500">Scheduled and active service jobs for your fleet</p>
        </div>
        <a href="<?= $escape($adminPageUrl('maintenance.php')) ?>" class="text-sm font-semibold text-blue-700 hover:underline">View service schedule <span aria-hidden="true">→</span></a>
    </div>
    <?php if ($upcomingMaintenance): ?>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3 sm:px-6">Vehicle</th>
                        <th scope="col" class="px-5 py-3">Service</th>
                        <th scope="col" class="px-5 py-3">Scheduled</th>
                        <th scope="col" class="px-5 py-3">Mechanic / shop</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach (array_slice($upcomingMaintenance, 0, 5) as $record): ?>
                        <tr>
                            <td class="px-5 py-4 sm:px-6">
                                <span class="block font-semibold text-slate-900"><?= $escape($record['plate_number']) ?></span>
                                <span class="mt-0.5 block text-xs text-slate-500"><?= $escape($record['vehicle_name']) ?></span>
                            </td>
                            <td class="px-5 py-4"><?= $escape($record['service_type']) ?></td>
                            <td class="px-5 py-4"><?= $escape($record['scheduled_date']) ?></td>
                            <td class="px-5 py-4"><?= $escape($record['mechanic_or_shop']) ?></td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusTone($record['service_status']) ?>"><?= $escape($record['service_status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="px-5 py-9 text-center sm:px-6">
            <p class="text-sm font-semibold text-slate-900">No upcoming service jobs</p>
            <p class="mt-1 text-sm text-slate-500">Schedule a maintenance checkup to keep the fleet running smoothly.</p>
            <a href="<?= $escape($adminPageUrl('maintenance.php')) ?>" class="mt-3 inline-flex text-sm font-semibold text-blue-700 hover:underline">Schedule maintenance <span class="ml-1" aria-hidden="true">→</span></a>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_layout_end.php'; ?>

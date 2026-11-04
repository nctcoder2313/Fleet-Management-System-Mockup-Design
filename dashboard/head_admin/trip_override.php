<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'emergency_overrides';
$pageTitle = 'Review trip override';
$formError = '';
$tripId = filter_var(
    $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['trip_id'] ?? null) : ($_GET['trip_id'] ?? null),
    FILTER_VALIDATE_INT
);
if (!$tripId) {
    http_response_code(400);
    exit('A valid trip ID is required.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $action = $_POST['override_action'] ?? '';
    $reason = trim(is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '');
    $reasonLength = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
    if (!is_string($action) || !in_array($action, ['force_complete', 'cancel_trip'], true)) {
        $formError = 'Choose either Force complete or Cancel trip.';
    } elseif ($reason === '' || $reasonLength > 500) {
        $formError = 'Enter a clear reason of no more than 500 characters before applying the override.';
    } elseif ($previewMode) {
        $tripIndex = null;
        foreach ($_SESSION['head_admin_preview_data']['trips'] as $index => $candidate) {
            if ((int) $candidate['id'] === $tripId) {
                $tripIndex = $index;
                break;
            }
        }
        if ($tripIndex === null || $_SESSION['head_admin_preview_data']['trips'][$tripIndex]['status'] !== 'in_progress') {
            $formError = 'This trip is no longer active. Refresh the trip list and select an active trip.';
        } else {
            $trip = &$_SESSION['head_admin_preview_data']['trips'][$tripIndex];
            $vehicleIndex = null;
            foreach ($_SESSION['head_admin_preview_data']['vehicles'] as $index => $candidate) {
                if ((int) $candidate['id'] === (int) $trip['vehicle_id']) {
                    $vehicleIndex = $index;
                    break;
                }
            }
            if ($vehicleIndex === null) {
                unset($trip);
                $formError = 'The assigned vehicle record is missing. No trip data was changed.';
            } else {
                $vehicle = &$_SESSION['head_admin_preview_data']['vehicles'][$vehicleIndex];
                $previousTripStatus = $trip['status'];
                $previousVehicleStatus = $vehicle['status'];
                $trip['status'] = $action === 'force_complete' ? 'completed' : 'cancelled';
                $trip['ended_at'] = date('Y-m-d H:i:s');
                $trip['override_reason'] = $reason;
                $trip['override_action'] = $action;
                $trip['overridden_by'] = $headAdmin['name'];
                if ($vehicle['status'] !== 'maintenance') {
                    $vehicle['status'] = 'idle';
                }
                $details = [
                    'trip_id' => $tripId,
                    'trip_reference' => $trip['reference'],
                    'vehicle_id' => $vehicle['id'],
                    'vehicle_name' => $vehicle['name'],
                    'plate_number' => $vehicle['plate_number'],
                    'action' => $action,
                    'previous_trip_status' => $previousTripStatus,
                    'new_trip_status' => $trip['status'],
                    'previous_vehicle_status' => $previousVehicleStatus,
                    'new_vehicle_status' => $vehicle['status'],
                    'reason' => $reason,
                ];
                unset($trip, $vehicle);
                headAdminRecordAudit($action === 'force_complete' ? 'TRIP_FORCE_COMPLETED' : 'TRIP_CANCELLED', $details);
                headAdminRedirect(
                    headAdminUrl('emergency_overrides.php'),
                    $action === 'force_complete' ? 'Trip force-completed in the preview and vehicle status updated.' : 'Trip cancelled in the preview and vehicle status updated.'
                );
            }
        }
    } else {
        try {
            $pdo->beginTransaction();
            $tripLock = $pdo->prepare(
                'SELECT id, reference, vehicle_id, status FROM fleet_trips WHERE id = :id FOR UPDATE'
            );
            $tripLock->execute(['id' => $tripId]);
            $lockedTrip = $tripLock->fetch();
            if (!$lockedTrip || $lockedTrip['status'] !== 'in_progress') {
                throw new DomainException('This trip is no longer active. Refresh the trip list and select an active trip.');
            }

            $vehicleLock = $pdo->prepare(
                'SELECT id, name, plate_number, status FROM fleet_vehicles WHERE id = :id FOR UPDATE'
            );
            $vehicleLock->execute(['id' => $lockedTrip['vehicle_id']]);
            $lockedVehicle = $vehicleLock->fetch();
            if (!$lockedVehicle) {
                throw new DomainException('The assigned vehicle record is missing. No trip data was changed.');
            }

            $newTripStatus = $action === 'force_complete' ? 'completed' : 'cancelled';
            $newVehicleStatus = $lockedVehicle['status'] === 'maintenance' ? 'maintenance' : 'idle';
            $updateTrip = $pdo->prepare(
                'UPDATE fleet_trips
                 SET status = :status, ended_at = CURRENT_TIMESTAMP,
                     override_reason = :reason, override_action = :action, override_by_user_id = :actor_id
                 WHERE id = :id AND status = \'in_progress\''
            );
            $updateTrip->execute([
                'status' => $newTripStatus,
                'reason' => $reason,
                'action' => $action,
                'actor_id' => $headAdmin['id'],
                'id' => $tripId,
            ]);
            if ($updateTrip->rowCount() !== 1) {
                throw new DomainException('The trip status changed before the override could be applied. Refresh and review the current trip state.');
            }

            $updateVehicle = $pdo->prepare('UPDATE fleet_vehicles SET status = :status WHERE id = :id');
            $updateVehicle->execute(['status' => $newVehicleStatus, 'id' => $lockedVehicle['id']]);
            headAdminRecordAudit($action === 'force_complete' ? 'TRIP_FORCE_COMPLETED' : 'TRIP_CANCELLED', [
                'trip_id' => $tripId,
                'trip_reference' => $lockedTrip['reference'],
                'vehicle_id' => $lockedVehicle['id'],
                'vehicle_name' => $lockedVehicle['name'],
                'plate_number' => $lockedVehicle['plate_number'],
                'action' => $action,
                'previous_trip_status' => $lockedTrip['status'],
                'new_trip_status' => $newTripStatus,
                'previous_vehicle_status' => $lockedVehicle['status'],
                'new_vehicle_status' => $newVehicleStatus,
                'reason' => $reason,
            ]);
            $pdo->commit();
            headAdminRedirect(
                headAdminUrl('emergency_overrides.php'),
                $action === 'force_complete' ? 'Trip force-completed and vehicle status updated.' : 'Trip cancelled and vehicle status updated.'
            );
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $formError = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}

$trip = null;
$vehicle = null;
if ($previewMode) {
    foreach ($_SESSION['head_admin_preview_data']['trips'] as $candidate) {
        if ((int) $candidate['id'] === $tripId) {
            $trip = $candidate;
            break;
        }
    }
    if ($trip) {
        foreach ($_SESSION['head_admin_preview_data']['vehicles'] as $candidate) {
            if ((int) $candidate['id'] === (int) $trip['vehicle_id']) {
                $vehicle = $candidate;
                break;
            }
        }
    }
} else {
    $statement = $pdo->prepare(
        'SELECT t.id, t.reference, t.vehicle_id, t.driver_id, t.origin, t.destination, t.status, t.started_at,
                t.ended_at, t.override_action, t.override_reason, t.override_by_user_id,
                v.name AS vehicle_name, v.plate_number, v.location, v.status AS vehicle_status,
                u.name AS driver_name, actor.name AS override_actor_name
         FROM fleet_trips t
         INNER JOIN fleet_vehicles v ON v.id = t.vehicle_id
         LEFT JOIN users u ON u.id = t.driver_id
         LEFT JOIN users actor ON actor.id = t.override_by_user_id
         WHERE t.id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $tripId]);
    $trip = $statement->fetch() ?: null;
    if ($trip) {
        $vehicle = [
            'id' => $trip['vehicle_id'],
            'name' => $trip['vehicle_name'],
            'plate_number' => $trip['plate_number'],
            'location' => $trip['location'],
            'status' => $trip['vehicle_status'],
        ];
    }
}
if (!$trip || !$vehicle) {
    http_response_code(404);
    exit('Trip or assigned vehicle not found.');
}

$tripStatusLabels = ['in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
$vehicleStatusLabels = ['on_road' => 'On the road', 'idle' => 'Idle', 'maintenance' => 'Under maintenance'];
require __DIR__ . '/_layout_start.php';
?>
<section class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-sm font-medium text-slate-500">Emergency trip recovery · Review before action</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900"><?= $escape($trip['reference']) ?></h2>
        <p class="mt-1 text-sm text-slate-600"><?= $escape($tripStatusLabels[$trip['status']] ?? $trip['status']) ?> · <?= $escape($vehicle['name']) ?> · <?= $escape($vehicle['plate_number']) ?></p>
    </div>
    <a href="<?= $escape(headAdminUrl('emergency_overrides.php')) ?>" class="text-sm font-semibold text-blue-700 hover:underline">Back to active trips</a>
</section>

<?php if ($formError !== ''): ?>
    <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900"><?= $escape($formError) ?></p>
<?php endif; ?>

<section class="grid gap-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(18rem,0.7fr)]">
    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="font-bold text-slate-900">Trip and vehicle review</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <?php foreach ([
                ['Route', $trip['origin'] . ' → ' . $trip['destination']],
                ['Driver', $trip['driver_name'] ?? 'Not assigned'],
                ['Vehicle', $vehicle['name'] . ' · ' . $vehicle['plate_number']],
                ['Vehicle status', $vehicleStatusLabels[$vehicle['status']] ?? $vehicle['status']],
                ['Reported location', $vehicle['location']],
                ['Trip started', date('M j, Y g:i A', strtotime($trip['started_at']))],
            ] as [$label, $value]): ?>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= $escape($label) ?></dt>
                    <dd class="mt-1 text-sm font-medium leading-6 text-slate-900"><?= $escape($value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <a href="<?= $escape(headAdminUrl('vehicle_detail.php', ['id' => (int) $vehicle['id']])) ?>" class="mt-5 inline-flex text-sm font-semibold text-blue-700 hover:underline">Open vehicle profile and trip history</a>
    </article>

    <?php if ($trip['status'] === 'in_progress'): ?>
        <article class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-sm font-semibold text-amber-800">Emergency action</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">Close this active trip</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Confirm the trip is genuinely stuck. The selected action closes the trip, releases the vehicle to Idle unless it is Under maintenance, and creates a permanent audit record.</p>
            <form method="post" class="mt-5 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                <input type="hidden" name="trip_id" value="<?= (int) $trip['id'] ?>">
                <label class="block text-sm font-medium text-slate-700" for="override-reason">
                    Reason for override
                    <textarea id="override-reason" name="reason" required maxlength="500" rows="4" placeholder="For example: Driver confirmed trip ended; mobile device remained stuck on the active screen." class="mt-1.5 w-full resize-y rounded-lg border border-slate-300 px-3 py-2.5 font-normal outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"><?= $escape($_POST['reason'] ?? '') ?></textarea>
                    <span class="mt-1 block text-xs font-normal text-slate-500">Required. This explanation will be retained in the trip and audit history.</span>
                </label>
                <div class="grid gap-2">
                    <button type="submit" name="override_action" value="force_complete" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800" data-confirm="Force-complete this trip and update the vehicle status? This action is recorded and cannot be undone.">Force complete</button>
                    <button type="submit" name="override_action" value="cancel_trip" class="rounded-lg border border-rose-300 bg-white px-4 py-2.5 text-sm font-semibold text-rose-800 transition hover:bg-rose-50" data-confirm="Cancel this trip and update the vehicle status? This action is recorded and cannot be undone.">Cancel trip</button>
                </div>
            </form>
        </article>
    <?php else: ?>
        <article class="rounded-xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
            <p class="font-semibold text-slate-900">This trip is already closed.</p>
            <p class="mt-2 text-sm text-slate-600">Its current status is <?= $escape($tripStatusLabels[$trip['status']] ?? $trip['status']) ?>. Emergency actions are available only for trips still in progress.</p>
            <?php if ($trip['override_action'] !== null): ?>
                <p class="mt-3 text-sm text-slate-700">Recorded reason: <?= $escape($trip['override_reason'] ?? 'No reason recorded') ?></p>
                <a href="<?= $escape(headAdminUrl('trip_override_history.php')) ?>" class="mt-3 inline-flex text-sm font-semibold text-blue-700 hover:underline">Open trip override history</a>
            <?php endif; ?>
        </article>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_layout_end.php'; ?>

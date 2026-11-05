<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'maintenance';
$pageTitle = 'Maintenance & service logs';
$formError = '';
$modalOpen = false;
$formValues = [
    'action' => 'create',
    'id' => '',
    'vehicle_id' => '',
    'service_type' => '',
    'scheduled_date' => '',
    'completion_date' => '',
    'mechanic_or_shop' => '',
    'parts_replaced' => '',
    'service_status' => 'Scheduled',
    'notes' => '',
];
$serviceTypes = ['Change Oil', 'Tire Replacement', 'Engine Repair', 'PMS'];
$serviceStatuses = ['Scheduled', 'In-Progress', 'Completed', 'Cancelled'];
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$validDate = static function (string $date): bool {
    if (strlen($date) !== 10 || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) !== 1) {
        return false;
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    return $parsed !== false
        && $parsed->format('Y-m-d') === $date
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $action = $_POST['action'] ?? '';
    if (!is_string($action) || !in_array($action, ['create', 'update', 'delete'], true)) {
        http_response_code(400);
        exit('Invalid maintenance action.');
    }

    $recordId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'delete') {
        if (!$recordId || $recordId < 1) {
            http_response_code(400);
            exit('Invalid maintenance record.');
        }

        if ($previewMode) {
            $records = &$_SESSION['admin_maintenance_preview'];
            $recordIndex = null;
            foreach ($records as $index => $record) {
                if ((int) $record['id'] === $recordId) {
                    $recordIndex = $index;
                    break;
                }
            }
            if ($recordIndex === null) {
                $formError = 'That maintenance record no longer exists.';
            } else {
                array_splice($records, $recordIndex, 1);
                $_SESSION['flash_message'] = 'Maintenance record removed from this preview.';
                header('Location: ' . $adminPageUrl('maintenance.php'));
                exit;
            }
        } else {
            try {
                $delete = $pdo->prepare('DELETE FROM maintenance_logs WHERE id = :id');
                $delete->execute(['id' => $recordId]);
                if ($delete->rowCount() !== 1) {
                    $formError = 'That maintenance record no longer exists.';
                } else {
                    $_SESSION['flash_message'] = 'Maintenance record removed.';
                    header('Location: ' . $adminPageUrl('maintenance.php'));
                    exit;
                }
            } catch (PDOException $exception) {
                $formError = 'The maintenance record could not be removed. Check for related records and try again.';
            }
        }
    } else {
        $formValues = [
            'action' => $action,
            'id' => is_scalar($_POST['id'] ?? null) ? (string) $_POST['id'] : '',
            'vehicle_id' => is_scalar($_POST['vehicle_id'] ?? null) ? (string) $_POST['vehicle_id'] : '',
            'service_type' => is_string($_POST['service_type'] ?? null) ? $_POST['service_type'] : '',
            'scheduled_date' => is_string($_POST['scheduled_date'] ?? null) ? $_POST['scheduled_date'] : '',
            'completion_date' => is_string($_POST['completion_date'] ?? null) ? $_POST['completion_date'] : '',
            'mechanic_or_shop' => trim(is_string($_POST['mechanic_or_shop'] ?? null) ? $_POST['mechanic_or_shop'] : ''),
            'parts_replaced' => trim(is_string($_POST['parts_replaced'] ?? null) ? $_POST['parts_replaced'] : ''),
            'service_status' => is_string($_POST['service_status'] ?? null) ? $_POST['service_status'] : '',
            'notes' => trim(is_string($_POST['notes'] ?? null) ? $_POST['notes'] : ''),
        ];
        $recordId = filter_var($formValues['id'], FILTER_VALIDATE_INT);
        $vehicleId = filter_var($formValues['vehicle_id'], FILTER_VALIDATE_INT);
        $completionDateValid = $formValues['completion_date'] === '' || $validDate($formValues['completion_date']);

        if (($action === 'update' && (!$recordId || $recordId < 1))
            || !$vehicleId || $vehicleId < 1
            || !in_array($formValues['service_type'], $serviceTypes, true)
            || !in_array($formValues['service_status'], $serviceStatuses, true)
            || !$validDate($formValues['scheduled_date'])
            || !$completionDateValid
            || ($formValues['service_status'] === 'Completed' && $formValues['completion_date'] === '')
            || ($formValues['completion_date'] !== '' && $formValues['completion_date'] < $formValues['scheduled_date'])
            || $formValues['mechanic_or_shop'] === '' || strlen($formValues['mechanic_or_shop']) > 200
            || strlen($formValues['parts_replaced']) > 10000
            || strlen($formValues['notes']) > 10000
        ) {
            $formError = 'Check the vehicle, service details, mechanic or shop, and dates. Completed services need a completion date on or after the scheduled date.';
        } else {
            if ($previewMode) {
                $vehicle = null;
                foreach ($_SESSION['admin_preview_vehicles'] as $candidate) {
                    if ((int) $candidate['id'] === $vehicleId) {
                        $vehicle = $candidate;
                        break;
                    }
                }
                $plateNumber = $vehicle['plate_number'] ?? false;
            } else {
                $vehicleLookup = $pdo->prepare('SELECT plate_number FROM fleet_vehicles WHERE id = :id LIMIT 1');
                $vehicleLookup->execute(['id' => $vehicleId]);
                $plateNumber = $vehicleLookup->fetchColumn();
            }

            if (!is_string($plateNumber)) {
                $formError = 'Select a vehicle that exists in the fleet directory.';
            } else {
                if ($previewMode) {
                    $records = &$_SESSION['admin_maintenance_preview'];
                    $previewRecord = [
                        'vehicle_id' => $vehicleId,
                        'vehicle_name' => $vehicle['name'],
                        'plate_number' => $plateNumber,
                        'service_type' => $formValues['service_type'],
                        'scheduled_date' => $formValues['scheduled_date'],
                        'completion_date' => $formValues['service_status'] === 'Completed' ? $formValues['completion_date'] : null,
                        'mechanic_or_shop' => $formValues['mechanic_or_shop'],
                        'parts_replaced' => $formValues['parts_replaced'] === '' ? null : $formValues['parts_replaced'],
                        'service_status' => $formValues['service_status'],
                        'notes' => $formValues['notes'] === '' ? null : $formValues['notes'],
                    ];
                    if ($action === 'create') {
                        $ids = array_column($records, 'id');
                        $previewRecord['id'] = $ids ? max($ids) + 1 : 1;
                        $records[] = $previewRecord;
                    } else {
                        $recordIndex = null;
                        foreach ($records as $index => $record) {
                            if ((int) $record['id'] === $recordId) {
                                $recordIndex = $index;
                                break;
                            }
                        }
                        if ($recordIndex === null) {
                            $formError = 'That maintenance record no longer exists.';
                        } else {
                            $previewRecord['id'] = $recordId;
                            $records[$recordIndex] = $previewRecord;
                        }
                    }
                    if ($formError === '') {
                        $_SESSION['flash_message'] = $action === 'create'
                            ? 'Maintenance service scheduled in this preview.'
                            : 'Maintenance record updated in this preview.';
                        header('Location: ' . $adminPageUrl('maintenance.php'));
                        exit;
                    }
                } else try {
                    if ($action === 'create') {
                        $statement = $pdo->prepare(
                            'INSERT INTO maintenance_logs
                                (vehicle_id, plate_number, service_type, scheduled_date, completion_date,
                                 mechanic_or_shop, parts_replaced, service_status, notes)
                             VALUES
                                (:vehicle_id, :plate_number, :service_type, :scheduled_date, :completion_date,
                                 :mechanic_or_shop, :parts_replaced, :service_status, :notes)'
                        );
                    } else {
                        $statement = $pdo->prepare(
                            'UPDATE maintenance_logs
                             SET vehicle_id = :vehicle_id, plate_number = :plate_number, service_type = :service_type,
                                 scheduled_date = :scheduled_date, completion_date = :completion_date,
                                 mechanic_or_shop = :mechanic_or_shop, parts_replaced = :parts_replaced,
                                 service_status = :service_status, notes = :notes
                             WHERE id = :id'
                        );
                    }

                    $statementParameters = [
                        'vehicle_id' => $vehicleId,
                        'plate_number' => $plateNumber,
                        'service_type' => $formValues['service_type'],
                        'scheduled_date' => $formValues['scheduled_date'],
                        'completion_date' => $formValues['service_status'] === 'Completed'
                            ? $formValues['completion_date']
                            : null,
                        'mechanic_or_shop' => $formValues['mechanic_or_shop'],
                        'parts_replaced' => $formValues['parts_replaced'] === '' ? null : $formValues['parts_replaced'],
                        'service_status' => $formValues['service_status'],
                        'notes' => $formValues['notes'] === '' ? null : $formValues['notes'],
                    ];
                    if ($action === 'update') {
                        $statementParameters['id'] = $recordId;
                    }
                    $statement->execute($statementParameters);

                    if ($action === 'update' && $statement->rowCount() === 0) {
                        $exists = $pdo->prepare('SELECT id FROM maintenance_logs WHERE id = :id LIMIT 1');
                        $exists->execute(['id' => $recordId]);
                        if (!$exists->fetchColumn()) {
                            $formError = 'That maintenance record no longer exists.';
                        }
                    }

                    if ($formError === '') {
                        $_SESSION['flash_message'] = $action === 'create'
                            ? 'Maintenance service scheduled.'
                            : 'Maintenance record updated.';
                        header('Location: ' . $adminPageUrl('maintenance.php'));
                        exit;
                    }
                } catch (PDOException $exception) {
                    $formError = 'The maintenance record could not be saved. Check the details and try again.';
                }
            }
        }

        $modalOpen = true;
    }
}

if ($previewMode) {
    $vehicles = $_SESSION['admin_preview_vehicles'];
} else {
    $vehiclesStatement = $pdo->query('SELECT id, name, plate_number FROM fleet_vehicles ORDER BY plate_number');
    $vehicles = $vehiclesStatement->fetchAll();
}
$search = $_GET['search'] ?? '';
$search = is_string($search) ? trim($search) : '';
$search = substr($search, 0, 100);
$selectedStatus = $_GET['status'] ?? '';
$selectedType = $_GET['type'] ?? '';
$selectedStatus = is_string($selectedStatus) && in_array($selectedStatus, $serviceStatuses, true) ? $selectedStatus : '';
$selectedType = is_string($selectedType) && in_array($selectedType, $serviceTypes, true) ? $selectedType : '';
$where = [];
$queryParameters = [];
if ($selectedStatus !== '') {
    $where[] = 'm.service_status = :service_status';
    $queryParameters['service_status'] = $selectedStatus;
}
if ($selectedType !== '') {
    $where[] = 'm.service_type = :service_type';
    $queryParameters['service_type'] = $selectedType;
}
if ($search !== '') {
    $where[] = '(m.plate_number LIKE :search_plate OR v.name LIKE :search_vehicle
        OR m.service_type LIKE :search_type OR m.mechanic_or_shop LIKE :search_shop
        OR m.parts_replaced LIKE :search_parts OR m.notes LIKE :search_notes)';
    $searchPattern = '%' . $search . '%';
    $queryParameters += [
        'search_plate' => $searchPattern,
        'search_vehicle' => $searchPattern,
        'search_type' => $searchPattern,
        'search_shop' => $searchPattern,
        'search_parts' => $searchPattern,
        'search_notes' => $searchPattern,
    ];
}
$sql = 'SELECT m.id, m.vehicle_id, m.plate_number, v.name AS vehicle_name, m.service_type,
               m.scheduled_date, m.completion_date, m.mechanic_or_shop, m.parts_replaced,
               m.service_status, m.notes
        FROM maintenance_logs m
        JOIN fleet_vehicles v ON v.id = m.vehicle_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY CASE WHEN m.service_status = \'Completed\' THEN 1 ELSE 0 END,
          m.scheduled_date ASC, m.id DESC';
if ($previewMode) {
    $records = array_values(array_filter(
        $_SESSION['admin_maintenance_preview'],
        static function (array $record) use ($selectedStatus, $selectedType, $search): bool {
            if ($selectedStatus !== '' && $record['service_status'] !== $selectedStatus) {
                return false;
            }
            if ($selectedType !== '' && $record['service_type'] !== $selectedType) {
                return false;
            }
            if ($search !== '') {
                $searchable = implode(' ', [
                    $record['plate_number'],
                    $record['vehicle_name'],
                    $record['service_type'],
                    $record['mechanic_or_shop'],
                    $record['parts_replaced'] ?? '',
                    $record['notes'] ?? '',
                ]);
                if (stripos($searchable, $search) === false) {
                    return false;
                }
            }
            return true;
        }
    ));
    usort($records, static fn (array $a, array $b): int => [$a['service_status'] === 'Completed', $a['scheduled_date']] <=> [$b['service_status'] === 'Completed', $b['scheduled_date']]);
} else {
    $recordsStatement = $pdo->prepare($sql);
    $recordsStatement->execute($queryParameters);
    $records = $recordsStatement->fetchAll();
}
require __DIR__ . '/_layout_start.php';
?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Maintenance schedule & technical service logs</h2>
            <p class="mt-1 text-sm text-slate-500">Schedule service, track repairs, and update work details. Financial records are not included.</p>
        </div>
        <button type="button" id="add-maintenance" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">Schedule maintenance</button>
    </div>
    <form method="get" class="mt-5 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(12rem,1.5fr)_1fr_1fr_auto_auto] lg:items-end">
        <?php if ($previewMode): ?><input type="hidden" name="preview" value="1"><?php endif; ?>
        <label class="text-sm font-medium text-slate-700">Search
            <input type="search" name="search" maxlength="100" value="<?= $escape($search) ?>" placeholder="Plate, vehicle, shop, parts..." class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
        </label>
        <label class="text-sm font-medium text-slate-700">Service status
            <select name="status" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                <option value="">All statuses</option>
                <?php foreach ($serviceStatuses as $status): ?>
                    <option value="<?= $escape($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= $escape($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="text-sm font-medium text-slate-700">Service type
            <select name="type" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                <option value="">All service types</option>
                <?php foreach ($serviceTypes as $type): ?>
                    <option value="<?= $escape($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>><?= $escape($type) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Apply filters</button>
        <a href="<?= $escape($adminPageUrl('maintenance.php')) ?>" class="rounded-lg px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-white">Clear</a>
    </form>
    <div class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[1050px] text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-3 py-3">Vehicle</th>
                    <th scope="col" class="px-3 py-3">Service</th>
                    <th scope="col" class="px-3 py-3">Scheduled</th>
                    <th scope="col" class="px-3 py-3">Completed</th>
                    <th scope="col" class="px-3 py-3">Mechanic / shop</th>
                    <th scope="col" class="px-3 py-3">Status</th>
                    <th scope="col" class="px-3 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($records as $record): ?>
                    <?php
                    $statusStyle = match ($record['service_status']) {
                        'Completed' => 'bg-emerald-100 text-emerald-800',
                        'In-Progress' => 'bg-blue-100 text-blue-800',
                        'Cancelled' => 'bg-slate-200 text-slate-700',
                        default => 'bg-amber-100 text-amber-800',
                    };
                    ?>
                    <tr>
                        <td class="px-3 py-4">
                            <span class="block font-semibold text-slate-900"><?= $escape($record['plate_number']) ?></span>
                            <span class="mt-0.5 block text-xs text-slate-500"><?= $escape($record['vehicle_name']) ?></span>
                        </td>
                        <td class="px-3 py-4 font-medium"><?= $escape($record['service_type']) ?></td>
                        <td class="px-3 py-4"><?= $escape($record['scheduled_date']) ?></td>
                        <td class="px-3 py-4"><?= $escape($record['completion_date'] ?? '—') ?></td>
                        <td class="px-3 py-4"><?= $escape($record['mechanic_or_shop']) ?></td>
                        <td class="px-3 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusStyle ?>"><?= $escape($record['service_status']) ?></span></td>
                        <td class="px-3 py-4">
                            <div class="flex items-center gap-3">
                                <button type="button" class="edit-maintenance font-semibold text-blue-700 hover:text-blue-900"
                                    data-id="<?= (int) $record['id'] ?>"
                                    data-vehicle-id="<?= (int) $record['vehicle_id'] ?>"
                                    data-service-type="<?= $escape($record['service_type']) ?>"
                                    data-scheduled-date="<?= $escape($record['scheduled_date']) ?>"
                                    data-completion-date="<?= $escape($record['completion_date'] ?? '') ?>"
                                    data-mechanic-or-shop="<?= $escape($record['mechanic_or_shop']) ?>"
                                    data-parts-replaced="<?= $escape($record['parts_replaced'] ?? '') ?>"
                                    data-service-status="<?= $escape($record['service_status']) ?>"
                                    data-notes="<?= $escape($record['notes'] ?? '') ?>">Edit</button>
                                <button type="button" class="delete-maintenance font-semibold text-red-700 hover:text-red-900"
                                    data-id="<?= (int) $record['id'] ?>"
                                    data-plate="<?= $escape($record['plate_number']) ?>"
                                    data-service="<?= $escape($record['service_type']) ?>">Remove</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$records): ?>
                    <tr><td colspan="7" class="px-3 py-12 text-center text-slate-500">No maintenance records match these filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<dialog id="maintenance-modal" class="w-[min(48rem,calc(100%-2rem))] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50">
    <form method="post" class="space-y-5 p-6" id="maintenance-form">
        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
        <input type="hidden" name="action" id="maintenance-action" value="<?= $escape($formValues['action']) ?>">
        <input type="hidden" name="id" id="maintenance-id" value="<?= $escape($formValues['id']) ?>">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="maintenance-modal-title" class="text-xl font-bold text-slate-900">Schedule maintenance</h2>
                <p class="mt-1 text-sm text-slate-500">Record the planned service and technical work details.</p>
            </div>
            <button type="button" class="close-maintenance-modal rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Close dialog">✕</button>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium text-slate-700 sm:col-span-2">Vehicle
                <select required name="vehicle_id" id="maintenance-vehicle" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5">
                    <option value="">Choose a vehicle</option>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <option value="<?= (int) $vehicle['id'] ?>" <?= (string) $vehicle['id'] === $formValues['vehicle_id'] ? 'selected' : '' ?>><?= $escape($vehicle['plate_number']) ?> · <?= $escape($vehicle['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Service type
                <select required name="service_type" id="maintenance-service-type" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5">
                    <option value="">Choose a service</option>
                    <?php foreach ($serviceTypes as $type): ?>
                        <option value="<?= $escape($type) ?>" <?= $formValues['service_type'] === $type ? 'selected' : '' ?>><?= $escape($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Service status
                <select required name="service_status" id="maintenance-service-status" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5">
                    <?php foreach ($serviceStatuses as $status): ?>
                        <option value="<?= $escape($status) ?>" <?= $formValues['service_status'] === $status ? 'selected' : '' ?>><?= $escape($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Scheduled date
                <input required type="date" name="scheduled_date" id="maintenance-scheduled-date" value="<?= $escape($formValues['scheduled_date']) ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            <label class="text-sm font-medium text-slate-700">Completion date <span class="font-normal text-slate-500">(required when completed)</span>
                <input type="date" name="completion_date" id="maintenance-completion-date" value="<?= $escape($formValues['completion_date']) ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            <label class="text-sm font-medium text-slate-700 sm:col-span-2">Mechanic or shop
                <input required maxlength="200" name="mechanic_or_shop" id="maintenance-mechanic" value="<?= $escape($formValues['mechanic_or_shop']) ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            <label class="text-sm font-medium text-slate-700 sm:col-span-2">Parts replaced
                <textarea maxlength="10000" rows="2" name="parts_replaced" id="maintenance-parts" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5"><?= $escape($formValues['parts_replaced']) ?></textarea>
            </label>
            <label class="text-sm font-medium text-slate-700 sm:col-span-2">Work notes
                <textarea maxlength="10000" rows="3" name="notes" id="maintenance-notes" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5"><?= $escape($formValues['notes']) ?></textarea>
            </label>
        </div>
        <?php if (!$vehicles): ?>
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Add a vehicle to the fleet directory before scheduling maintenance.</p>
        <?php endif; ?>
        <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
            <button type="button" class="close-maintenance-modal rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" id="maintenance-submit" <?= !$vehicles ? 'disabled' : '' ?> class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">Save maintenance</button>
        </div>
    </form>
</dialog>

<dialog id="remove-maintenance-modal" class="w-[min(28rem,calc(100%-2rem))] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50">
    <form method="post" class="space-y-5 p-6">
        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="remove-maintenance-id">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Remove maintenance record?</h2>
            <p id="remove-maintenance-message" class="mt-2 text-sm text-slate-600">This action cannot be undone.</p>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" id="cancel-remove-maintenance" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Keep record</button>
            <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Remove record</button>
        </div>
    </form>
</dialog>
<script>
    const maintenanceModal = document.getElementById('maintenance-modal');
    const maintenanceFields = {
        vehicle: document.getElementById('maintenance-vehicle'),
        type: document.getElementById('maintenance-service-type'),
        status: document.getElementById('maintenance-service-status'),
        scheduled: document.getElementById('maintenance-scheduled-date'),
        completion: document.getElementById('maintenance-completion-date'),
        mechanic: document.getElementById('maintenance-mechanic'),
        parts: document.getElementById('maintenance-parts'),
        notes: document.getElementById('maintenance-notes')
    };

    function openMaintenanceModal(mode, values = {}) {
        document.getElementById('maintenance-action').value = mode;
        document.getElementById('maintenance-id').value = values.id || '';
        document.getElementById('maintenance-modal-title').textContent =
            mode === 'update' ? 'Update maintenance record' : 'Schedule maintenance';
        document.getElementById('maintenance-submit').textContent =
            mode === 'update' ? 'Save changes' : 'Save maintenance';
        maintenanceFields.vehicle.value = values.vehicleId || '';
        maintenanceFields.type.value = values.type || '';
        maintenanceFields.status.value = values.status || 'Scheduled';
        maintenanceFields.scheduled.value = values.scheduled || '';
        maintenanceFields.completion.value = values.completion || '';
        maintenanceFields.completion.required = maintenanceFields.status.value === 'Completed';
        maintenanceFields.mechanic.value = values.mechanic || '';
        maintenanceFields.parts.value = values.parts || '';
        maintenanceFields.notes.value = values.notes || '';
        maintenanceModal.showModal();
    }

    document.getElementById('add-maintenance').addEventListener('click', () => openMaintenanceModal('create'));
    document.querySelectorAll('.edit-maintenance').forEach((button) => {
        button.addEventListener('click', () => openMaintenanceModal('update', {
            id: button.dataset.id,
            vehicleId: button.dataset.vehicleId,
            type: button.dataset.serviceType,
            status: button.dataset.serviceStatus,
            scheduled: button.dataset.scheduledDate,
            completion: button.dataset.completionDate,
            mechanic: button.dataset.mechanicOrShop,
            parts: button.dataset.partsReplaced,
            notes: button.dataset.notes
        }));
    });
    document.querySelectorAll('.close-maintenance-modal').forEach((button) => {
        button.addEventListener('click', () => maintenanceModal.close());
    });
    maintenanceFields.status.addEventListener('change', () => {
        maintenanceFields.completion.required = maintenanceFields.status.value === 'Completed';
        if (maintenanceFields.status.value !== 'Completed') {
            maintenanceFields.completion.value = '';
        }
    });
    if (<?= $modalOpen ? 'true' : 'false' ?>) {
        maintenanceModal.showModal();
    }

    const removeModal = document.getElementById('remove-maintenance-modal');
    document.querySelectorAll('.delete-maintenance').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('remove-maintenance-id').value = button.dataset.id;
            document.getElementById('remove-maintenance-message').textContent =
                `Remove the ${button.dataset.service} record for ${button.dataset.plate}? This action cannot be undone.`;
            removeModal.showModal();
        });
    });
    document.getElementById('cancel-remove-maintenance').addEventListener('click', () => removeModal.close());
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>

<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'vendors_equipment';
$pageTitle = 'Vendors & Equipment';
$formError = '';
$modalOpen = '';
$vendorValues = [
    'action' => 'create',
    'id' => '',
    'shop_name' => '',
    'contact_person' => '',
    'phone' => '',
    'address' => '',
    'services_offered' => '',
];
$equipmentValues = [
    'action' => 'create',
    'id' => '',
    'vehicle_id' => '',
    'equipment_type' => '',
    'serial_number' => '',
    'installation_date' => '',
    'status' => 'Installed',
];
$equipmentTypes = ['GPS Tracker', 'Dashcam', 'RFID Tag', 'Fire Extinguisher'];
$equipmentStatuses = ['Installed', 'Needs Service', 'Removed'];
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$validDate = static function (string $date): bool {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) !== 1) {
        return false;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    return $parsed !== false && $parsed->format('Y-m-d') === $date
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
};

$requestedTab = $_GET['tab'] ?? 'vendors';
$activeTab = is_string($requestedTab) && $requestedTab === 'equipment' ? 'equipment' : 'vendors';
$tabUrl = static fn (string $tab): string => $adminPageUrl('vendors_equipment.php')
    . ($previewMode ? '&' : '?') . 'tab=' . rawurlencode($tab);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $entity = $_POST['entity'] ?? '';
    $action = $_POST['action'] ?? '';
    if (!is_string($entity) || !in_array($entity, ['vendor', 'equipment'], true)
        || !is_string($action) || !in_array($action, ['create', 'update', 'delete'], true)
    ) {
        http_response_code(400);
        exit('Invalid directory action.');
    }
    $activeTab = $entity === 'vendor' ? 'vendors' : 'equipment';
    $recordId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'delete') {
        if (!$recordId || $recordId < 1) {
            http_response_code(400);
            exit('Invalid directory record.');
        }
        if ($previewMode) {
            $sessionKey = $entity === 'vendor' ? 'admin_vendor_preview' : 'admin_equipment_preview';
            $records = &$_SESSION[$sessionKey];
            $recordIndex = null;
            foreach ($records as $index => $record) {
                if ((int) $record['id'] === $recordId) {
                    $recordIndex = $index;
                    break;
                }
            }
            if ($recordIndex === null) {
                $formError = 'That record no longer exists.';
            } else {
                array_splice($records, $recordIndex, 1);
                $_SESSION['flash_message'] = 'Record removed from this preview.';
                header('Location: ' . $tabUrl($activeTab));
                exit;
            }
        } else {
            $table = $entity === 'vendor' ? 'accredited_vendors' : 'vehicle_equipment';
            try {
                $delete = $pdo->prepare("DELETE FROM {$table} WHERE id = :id");
                $delete->execute(['id' => $recordId]);
                if ($delete->rowCount() !== 1) {
                    $formError = 'That record no longer exists.';
                } else {
                    $_SESSION['flash_message'] = 'Record removed.';
                    header('Location: ' . $tabUrl($activeTab));
                    exit;
                }
            } catch (PDOException $exception) {
                $formError = $entity === 'equipment'
                    ? 'This equipment record could not be removed because it is referenced elsewhere.'
                    : 'This vendor record could not be removed. Check for related records and try again.';
            }
        }
    } elseif ($entity === 'vendor') {
        $vendorValues = [
            'action' => $action,
            'id' => is_scalar($_POST['id'] ?? null) ? (string) $_POST['id'] : '',
            'shop_name' => trim(is_string($_POST['shop_name'] ?? null) ? $_POST['shop_name'] : ''),
            'contact_person' => trim(is_string($_POST['contact_person'] ?? null) ? $_POST['contact_person'] : ''),
            'phone' => trim(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : ''),
            'address' => trim(is_string($_POST['address'] ?? null) ? $_POST['address'] : ''),
            'services_offered' => trim(is_string($_POST['services_offered'] ?? null) ? $_POST['services_offered'] : ''),
        ];
        $recordId = filter_var($vendorValues['id'], FILTER_VALIDATE_INT);
        if (($action === 'update' && (!$recordId || $recordId < 1))
            || $vendorValues['shop_name'] === '' || strlen($vendorValues['shop_name']) > 150
            || $vendorValues['contact_person'] === '' || strlen($vendorValues['contact_person']) > 150
            || $vendorValues['phone'] === '' || strlen($vendorValues['phone']) > 40
            || $vendorValues['address'] === '' || strlen($vendorValues['address']) > 500
            || $vendorValues['services_offered'] === '' || strlen($vendorValues['services_offered']) > 5000
        ) {
            $formError = 'Complete all vendor fields and stay within the listed length limits.';
        } elseif ($previewMode) {
            $vendors = &$_SESSION['admin_vendor_preview'];
            if ($action === 'create') {
                $ids = array_column($vendors, 'id');
                $vendorValues['id'] = $ids ? max($ids) + 1 : 1;
                $vendors[] = $vendorValues;
            } else {
                $recordIndex = null;
                foreach ($vendors as $index => $vendor) {
                    if ((int) $vendor['id'] === $recordId) {
                        $recordIndex = $index;
                        break;
                    }
                }
                if ($recordIndex === null) {
                    $formError = 'That vendor record no longer exists.';
                } else {
                    $vendorValues['id'] = $recordId;
                    $vendors[$recordIndex] = $vendorValues;
                }
            }
        } else {
            try {
                if ($action === 'create') {
                    $statement = $pdo->prepare(
                        'INSERT INTO accredited_vendors
                            (shop_name, contact_person, phone, address, services_offered)
                         VALUES
                            (:shop_name, :contact_person, :phone, :address, :services_offered)'
                    );
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE accredited_vendors
                         SET shop_name = :shop_name, contact_person = :contact_person, phone = :phone,
                             address = :address, services_offered = :services_offered
                         WHERE id = :id'
                    );
                    $vendorValues['id'] = $recordId;
                }
                $parameters = $vendorValues;
                unset($parameters['action'], $parameters['id']);
                if ($action === 'update') {
                    $parameters['id'] = $recordId;
                }
                $statement->execute($parameters);
                if ($action === 'update' && $statement->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT id FROM accredited_vendors WHERE id = :id LIMIT 1');
                    $exists->execute(['id' => $recordId]);
                    if (!$exists->fetchColumn()) {
                        $formError = 'That vendor record no longer exists.';
                    }
                }
            } catch (PDOException $exception) {
                $formError = 'The vendor record could not be saved. Check the details and try again.';
            }
        }
        if ($formError === '') {
            $_SESSION['flash_message'] = ($action === 'create' ? 'Vendor added' : 'Vendor updated') . ($previewMode ? ' in this preview.' : '.');
            header('Location: ' . $tabUrl('vendors'));
            exit;
        }
        $modalOpen = 'vendor';
    } else {
        $equipmentValues = [
            'action' => $action,
            'id' => is_scalar($_POST['id'] ?? null) ? (string) $_POST['id'] : '',
            'vehicle_id' => is_scalar($_POST['vehicle_id'] ?? null) ? (string) $_POST['vehicle_id'] : '',
            'equipment_type' => is_string($_POST['equipment_type'] ?? null) ? $_POST['equipment_type'] : '',
            'serial_number' => trim(is_string($_POST['serial_number'] ?? null) ? $_POST['serial_number'] : ''),
            'installation_date' => is_string($_POST['installation_date'] ?? null) ? $_POST['installation_date'] : '',
            'status' => is_string($_POST['status'] ?? null) ? $_POST['status'] : '',
        ];
        $recordId = filter_var($equipmentValues['id'], FILTER_VALIDATE_INT);
        $vehicleId = filter_var($equipmentValues['vehicle_id'], FILTER_VALIDATE_INT);
        if (($action === 'update' && (!$recordId || $recordId < 1))
            || !$vehicleId || $vehicleId < 1
            || !in_array($equipmentValues['equipment_type'], $equipmentTypes, true)
            || $equipmentValues['serial_number'] === '' || strlen($equipmentValues['serial_number']) > 100
            || !$validDate($equipmentValues['installation_date'])
            || !in_array($equipmentValues['status'], $equipmentStatuses, true)
        ) {
            $formError = 'Check the vehicle, equipment type, serial number, installation date, and status.';
        } else {
            $vehicles = $previewMode
                ? $_SESSION['admin_preview_vehicles']
                : $pdo->query('SELECT id, name, plate_number FROM fleet_vehicles ORDER BY plate_number')->fetchAll();
            $vehicle = null;
            foreach ($vehicles as $candidate) {
                if ((int) $candidate['id'] === $vehicleId) {
                    $vehicle = $candidate;
                    break;
                }
            }
            if ($vehicle === null) {
                $formError = 'Select a vehicle that exists in the fleet directory.';
            } elseif ($previewMode) {
                $equipment = &$_SESSION['admin_equipment_preview'];
                $duplicateSerial = false;
                foreach ($equipment as $existing) {
                    if (strcasecmp($existing['serial_number'], $equipmentValues['serial_number']) === 0
                        && (int) $existing['id'] !== (int) $recordId
                    ) {
                        $duplicateSerial = true;
                        break;
                    }
                }
                if ($duplicateSerial) {
                    $formError = 'That serial number is already registered.';
                } else {
                    $equipmentRecord = [
                        'vehicle_id' => $vehicleId,
                        'equipment_type' => $equipmentValues['equipment_type'],
                        'serial_number' => $equipmentValues['serial_number'],
                        'installation_date' => $equipmentValues['installation_date'],
                        'status' => $equipmentValues['status'],
                    ];
                    if ($action === 'create') {
                        $ids = array_column($equipment, 'id');
                        $equipmentRecord['id'] = $ids ? max($ids) + 1 : 1;
                        $equipment[] = $equipmentRecord;
                    } else {
                        $recordIndex = null;
                        foreach ($equipment as $index => $item) {
                            if ((int) $item['id'] === $recordId) {
                                $recordIndex = $index;
                                break;
                            }
                        }
                        if ($recordIndex === null) {
                            $formError = 'That equipment record no longer exists.';
                        } else {
                            $equipmentRecord['id'] = $recordId;
                            $equipment[$recordIndex] = $equipmentRecord;
                        }
                    }
                }
            } else {
                try {
                    if ($action === 'create') {
                        $statement = $pdo->prepare(
                            'INSERT INTO vehicle_equipment
                                (vehicle_id, equipment_type, serial_number, installation_date, status)
                             VALUES
                                (:vehicle_id, :equipment_type, :serial_number, :installation_date, :status)'
                        );
                    } else {
                        $statement = $pdo->prepare(
                            'UPDATE vehicle_equipment
                             SET vehicle_id = :vehicle_id, equipment_type = :equipment_type,
                                 serial_number = :serial_number, installation_date = :installation_date,
                                 status = :status
                             WHERE id = :id'
                        );
                        $equipmentValues['id'] = $recordId;
                    }
                    $parameters = $equipmentValues;
                    unset($parameters['action'], $parameters['id']);
                    if ($action === 'update') {
                        $parameters['id'] = $recordId;
                    }
                    $statement->execute($parameters);
                    if ($action === 'update' && $statement->rowCount() === 0) {
                        $exists = $pdo->prepare('SELECT id FROM vehicle_equipment WHERE id = :id LIMIT 1');
                        $exists->execute(['id' => $recordId]);
                        if (!$exists->fetchColumn()) {
                            $formError = 'That equipment record no longer exists.';
                        }
                    }
                } catch (PDOException $exception) {
                    $formError = 'The equipment record could not be saved. Verify that the serial number is unique and try again.';
                }
            }
        }
        if ($formError === '') {
            $_SESSION['flash_message'] = ($action === 'create' ? 'Equipment registered' : 'Equipment updated') . ($previewMode ? ' in this preview.' : '.');
            header('Location: ' . $tabUrl('equipment'));
            exit;
        }
        $modalOpen = 'equipment';
    }
}

$vehicles = $previewMode
    ? $_SESSION['admin_preview_vehicles']
    : $pdo->query('SELECT id, name, plate_number FROM fleet_vehicles ORDER BY plate_number')->fetchAll();
if ($previewMode) {
    $vendors = $_SESSION['admin_vendor_preview'];
    $equipment = $_SESSION['admin_equipment_preview'];
    foreach ($equipment as &$item) {
        foreach ($vehicles as $vehicle) {
            if ((int) $vehicle['id'] === (int) $item['vehicle_id']) {
                $item['vehicle_name'] = $vehicle['name'];
                $item['plate_number'] = $vehicle['plate_number'];
                break;
            }
        }
    }
    unset($item);
} else {
    $vendors = $pdo->query(
        'SELECT id, shop_name, contact_person, phone, address, services_offered
         FROM accredited_vendors ORDER BY shop_name'
    )->fetchAll();
    $equipment = $pdo->query(
        'SELECT e.id, e.vehicle_id, v.name AS vehicle_name, v.plate_number,
                e.equipment_type, e.serial_number, e.installation_date, e.status
         FROM vehicle_equipment e
         JOIN fleet_vehicles v ON v.id = e.vehicle_id
         ORDER BY v.plate_number, e.equipment_type'
    )->fetchAll();
}

require __DIR__ . '/_layout_start.php';
?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Vendor & equipment directory</h2>
            <p class="mt-1 text-sm text-slate-500">Manage accredited repair shops and equipment installed across your fleet.</p>
        </div>
        <button type="button" id="add-directory-record" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">
            <?= $activeTab === 'vendors' ? 'Add repair shop' : 'Register equipment' ?>
        </button>
    </div>
    <div class="mt-5 flex gap-2 border-b border-slate-200" role="tablist" aria-label="Directory sections">
        <a role="tab" aria-selected="<?= $activeTab === 'vendors' ? 'true' : 'false' ?>" href="<?= $escape($tabUrl('vendors')) ?>" class="border-b-2 px-4 py-3 text-sm font-semibold <?= $activeTab === 'vendors' ? 'border-blue-700 text-blue-800' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">Accredited Repair Shops</a>
        <a role="tab" aria-selected="<?= $activeTab === 'equipment' ? 'true' : 'false' ?>" href="<?= $escape($tabUrl('equipment')) ?>" class="border-b-2 px-4 py-3 text-sm font-semibold <?= $activeTab === 'equipment' ? 'border-blue-700 text-blue-800' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">Installed Vehicle Equipment</a>
    </div>
    <?php if ($activeTab === 'vendors'): ?>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[850px] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                    <tr><th scope="col" class="px-3 py-3">Repair shop</th><th scope="col" class="px-3 py-3">Contact</th><th scope="col" class="px-3 py-3">Phone</th><th scope="col" class="px-3 py-3">Address</th><th scope="col" class="px-3 py-3">Services offered</th><th scope="col" class="px-3 py-3">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($vendors as $vendor): ?>
                        <tr>
                            <td class="px-3 py-4 font-semibold text-slate-900"><?= $escape($vendor['shop_name']) ?></td>
                            <td class="px-3 py-4"><?= $escape($vendor['contact_person']) ?></td>
                            <td class="px-3 py-4"><?= $escape($vendor['phone']) ?></td>
                            <td class="px-3 py-4"><?= $escape($vendor['address']) ?></td>
                            <td class="max-w-xs whitespace-pre-line px-3 py-4"><?= $escape($vendor['services_offered']) ?></td>
                            <td class="px-3 py-4"><div class="flex gap-3">
                                <button type="button" class="edit-vendor font-semibold text-blue-700" data-id="<?= (int) $vendor['id'] ?>" data-shop="<?= $escape($vendor['shop_name']) ?>" data-contact="<?= $escape($vendor['contact_person']) ?>" data-phone="<?= $escape($vendor['phone']) ?>" data-address="<?= $escape($vendor['address']) ?>" data-services="<?= $escape($vendor['services_offered']) ?>">Edit</button>
                                <button type="button" class="delete-record font-semibold text-red-700" data-entity="vendor" data-id="<?= (int) $vendor['id'] ?>" data-name="<?= $escape($vendor['shop_name']) ?>">Delete</button>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$vendors): ?><tr><td colspan="6" class="px-3 py-12 text-center text-slate-500">No accredited repair shops added.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[850px] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                    <tr><th scope="col" class="px-3 py-3">Vehicle</th><th scope="col" class="px-3 py-3">Equipment</th><th scope="col" class="px-3 py-3">Serial number</th><th scope="col" class="px-3 py-3">Installed</th><th scope="col" class="px-3 py-3">Status</th><th scope="col" class="px-3 py-3">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($equipment as $item): ?>
                        <?php $equipmentStatusStyle = match ($item['status']) { 'Installed' => 'bg-emerald-100 text-emerald-800', 'Needs Service' => 'bg-amber-100 text-amber-800', default => 'bg-slate-200 text-slate-700' }; ?>
                        <tr>
                            <td class="px-3 py-4"><span class="block font-semibold text-slate-900"><?= $escape($item['plate_number']) ?></span><span class="text-xs text-slate-500"><?= $escape($item['vehicle_name']) ?></span></td>
                            <td class="px-3 py-4"><?= $escape($item['equipment_type']) ?></td>
                            <td class="px-3 py-4 font-mono"><?= $escape($item['serial_number']) ?></td>
                            <td class="px-3 py-4"><?= $escape($item['installation_date']) ?></td>
                            <td class="px-3 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $equipmentStatusStyle ?>"><?= $escape($item['status']) ?></span></td>
                            <td class="px-3 py-4"><div class="flex gap-3">
                                <button type="button" class="edit-equipment font-semibold text-blue-700" data-id="<?= (int) $item['id'] ?>" data-vehicle-id="<?= (int) $item['vehicle_id'] ?>" data-type="<?= $escape($item['equipment_type']) ?>" data-serial="<?= $escape($item['serial_number']) ?>" data-date="<?= $escape($item['installation_date']) ?>" data-status="<?= $escape($item['status']) ?>">Edit</button>
                                <button type="button" class="delete-record font-semibold text-red-700" data-entity="equipment" data-id="<?= (int) $item['id'] ?>" data-name="<?= $escape($item['equipment_type'] . ' ' . $item['serial_number']) ?>">Delete</button>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$equipment): ?><tr><td colspan="6" class="px-3 py-12 text-center text-slate-500">No vehicle equipment registered.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<dialog id="vendor-modal" class="w-[min(42rem,calc(100%-2rem))] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50">
    <form method="post" class="space-y-5 p-6">
        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="entity" value="vendor"><input type="hidden" name="action" id="vendor-action" value="<?= $escape($vendorValues['action']) ?>"><input type="hidden" name="id" id="vendor-id" value="<?= $escape($vendorValues['id']) ?>">
        <div class="flex items-start justify-between"><div><h2 id="vendor-title" class="text-xl font-bold text-slate-900">Add accredited repair shop</h2><p class="mt-1 text-sm text-slate-500">Store vendor contact and service details.</p></div><button type="button" class="close-modal rounded px-2 py-1 text-slate-500" aria-label="Close dialog">✕</button></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium sm:col-span-2">Shop name<input required maxlength="150" id="vendor-shop" name="shop_name" value="<?= $escape($vendorValues['shop_name']) ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium">Contact person<input required maxlength="150" id="vendor-contact" name="contact_person" value="<?= $escape($vendorValues['contact_person']) ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium">Phone<input required maxlength="40" id="vendor-phone" name="phone" value="<?= $escape($vendorValues['phone']) ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium sm:col-span-2">Address<textarea required maxlength="500" rows="2" id="vendor-address" name="address" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2"><?= $escape($vendorValues['address']) ?></textarea></label>
            <label class="text-sm font-medium sm:col-span-2">Services offered<textarea required maxlength="5000" rows="3" id="vendor-services" name="services_offered" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2"><?= $escape($vendorValues['services_offered']) ?></textarea></label>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" class="close-modal rounded-lg border px-4 py-2 text-sm">Cancel</button><button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Save vendor</button></div>
    </form>
</dialog>

<dialog id="equipment-modal" class="w-[min(42rem,calc(100%-2rem))] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50">
    <form method="post" class="space-y-5 p-6">
        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="entity" value="equipment"><input type="hidden" name="action" id="equipment-action" value="<?= $escape($equipmentValues['action']) ?>"><input type="hidden" name="id" id="equipment-id" value="<?= $escape($equipmentValues['id']) ?>">
        <div class="flex items-start justify-between"><div><h2 id="equipment-title" class="text-xl font-bold text-slate-900">Register vehicle equipment</h2><p class="mt-1 text-sm text-slate-500">Link a hardware serial number to a fleet vehicle.</p></div><button type="button" class="close-modal rounded px-2 py-1 text-slate-500" aria-label="Close dialog">✕</button></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium sm:col-span-2">Vehicle<select required id="equipment-vehicle" name="vehicle_id" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="">Choose a vehicle</option><?php foreach ($vehicles as $vehicle): ?><option value="<?= (int) $vehicle['id'] ?>" <?= (string) $vehicle['id'] === $equipmentValues['vehicle_id'] ? 'selected' : '' ?>><?= $escape($vehicle['plate_number']) ?> · <?= $escape($vehicle['name']) ?></option><?php endforeach; ?></select></label>
            <label class="text-sm font-medium">Equipment type<select required id="equipment-type" name="equipment_type" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="">Choose equipment</option><?php foreach ($equipmentTypes as $type): ?><option value="<?= $escape($type) ?>" <?= $equipmentValues['equipment_type'] === $type ? 'selected' : '' ?>><?= $escape($type) ?></option><?php endforeach; ?></select></label>
            <label class="text-sm font-medium">Serial number<input required maxlength="100" id="equipment-serial" name="serial_number" value="<?= $escape($equipmentValues['serial_number']) ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium">Installation date<input required type="date" id="equipment-date" name="installation_date" value="<?= $escape($equipmentValues['installation_date']) ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium">Status<select required id="equipment-status" name="status" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><?php foreach ($equipmentStatuses as $status): ?><option value="<?= $escape($status) ?>" <?= $equipmentValues['status'] === $status ? 'selected' : '' ?>><?= $escape($status) ?></option><?php endforeach; ?></select></label>
        </div>
        <?php if (!$vehicles): ?><p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Add a vehicle to the fleet directory before registering equipment.</p><?php endif; ?>
        <div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" class="close-modal rounded-lg border px-4 py-2 text-sm">Cancel</button><button type="submit" <?= !$vehicles ? 'disabled' : '' ?> class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Save equipment</button></div>
    </form>
</dialog>

<dialog id="delete-modal" class="w-[min(28rem,calc(100%-2rem))] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50">
    <form method="post" class="space-y-5 p-6">
        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>"><input type="hidden" name="entity" id="delete-entity"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="delete-id"><input type="hidden" name="tab" id="delete-tab" value="<?= $activeTab ?>">
        <div><h2 class="text-lg font-bold text-slate-900">Delete this record?</h2><p id="delete-message" class="mt-2 text-sm text-slate-600">This action cannot be undone.</p></div>
        <div class="flex justify-end gap-3"><button type="button" id="cancel-delete" class="rounded-lg border px-4 py-2 text-sm">Cancel</button><button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Delete record</button></div>
    </form>
</dialog>
<script>
    const activeTab = <?= json_encode($activeTab, JSON_THROW_ON_ERROR) ?>;
    const vendorModal = document.getElementById('vendor-modal');
    const equipmentModal = document.getElementById('equipment-modal');
    document.getElementById('add-directory-record').addEventListener('click', () => {
        if (activeTab === 'vendors') {
            document.getElementById('vendor-action').value = 'create';
            document.getElementById('vendor-id').value = '';
            document.getElementById('vendor-title').textContent = 'Add accredited repair shop';
            vendorModal.showModal();
        } else {
            document.getElementById('equipment-action').value = 'create';
            document.getElementById('equipment-id').value = '';
            document.getElementById('equipment-title').textContent = 'Register vehicle equipment';
            equipmentModal.showModal();
        }
    });
    document.querySelectorAll('.edit-vendor').forEach((button) => button.addEventListener('click', () => {
        document.getElementById('vendor-action').value = 'update';
        document.getElementById('vendor-id').value = button.dataset.id;
        document.getElementById('vendor-title').textContent = 'Edit accredited repair shop';
        document.getElementById('vendor-shop').value = button.dataset.shop;
        document.getElementById('vendor-contact').value = button.dataset.contact;
        document.getElementById('vendor-phone').value = button.dataset.phone;
        document.getElementById('vendor-address').value = button.dataset.address;
        document.getElementById('vendor-services').value = button.dataset.services;
        vendorModal.showModal();
    }));
    document.querySelectorAll('.edit-equipment').forEach((button) => button.addEventListener('click', () => {
        document.getElementById('equipment-action').value = 'update';
        document.getElementById('equipment-id').value = button.dataset.id;
        document.getElementById('equipment-title').textContent = 'Edit vehicle equipment';
        document.getElementById('equipment-vehicle').value = button.dataset.vehicleId;
        document.getElementById('equipment-type').value = button.dataset.type;
        document.getElementById('equipment-serial').value = button.dataset.serial;
        document.getElementById('equipment-date').value = button.dataset.date;
        document.getElementById('equipment-status').value = button.dataset.status;
        equipmentModal.showModal();
    }));
    document.querySelectorAll('.close-modal').forEach((button) => button.addEventListener('click', () => {
        vendorModal.close();
        equipmentModal.close();
    }));
    const deleteModal = document.getElementById('delete-modal');
    document.querySelectorAll('.delete-record').forEach((button) => button.addEventListener('click', () => {
        document.getElementById('delete-entity').value = button.dataset.entity;
        document.getElementById('delete-id').value = button.dataset.id;
        document.getElementById('delete-tab').value = button.dataset.entity === 'vendor' ? 'vendors' : 'equipment';
        document.getElementById('delete-message').textContent = `Delete “${button.dataset.name}”? This action cannot be undone.`;
        deleteModal.showModal();
    }));
    document.getElementById('cancel-delete').addEventListener('click', () => deleteModal.close());
    if (<?= json_encode($modalOpen, JSON_THROW_ON_ERROR) ?> === 'vendor') vendorModal.showModal();
    if (<?= json_encode($modalOpen, JSON_THROW_ON_ERROR) ?> === 'equipment') equipmentModal.showModal();
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>

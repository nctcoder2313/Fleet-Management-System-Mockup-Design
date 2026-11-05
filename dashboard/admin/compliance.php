<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/rbac.php';
startSecureSession();
$admin = requireRole($pdo, 'admin');
$previewMode = false;
$adminPageUrl = static fn (string $page): string => $page;
$activePage = 'compliance';
$pageTitle = 'Document compliance';
$flashMessage = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
$formError = '';
$modalOpen = false;
$formValues = [
    'action' => 'create',
    'id' => '',
    'vehicle_id' => '',
    'document_type' => '',
    'document_no' => '',
    'issue_date' => '',
    'expiry_date' => '',
    'notes' => '',
];
$documentTypes = ['LTO', 'LTFRB', 'Insurance'];
$documentStatuses = ['Valid', 'Expiring Soon', 'Expired'];
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $action = $_POST['action'] ?? '';
    if (!is_string($action) || !in_array($action, ['create', 'update', 'delete'], true)) {
        http_response_code(400);
        exit('Invalid document action.');
    }

    $documentId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'delete') {
        if (!$documentId || $documentId < 1) {
            http_response_code(400);
            exit('Invalid document record.');
        }

        try {
            $delete = $pdo->prepare('DELETE FROM vehicle_documents WHERE id = :id');
            $delete->execute(['id' => $documentId]);
            if ($delete->rowCount() !== 1) {
                $formError = 'That document record no longer exists.';
            } else {
                $_SESSION['flash_message'] = 'Vehicle document removed.';
                header('Location: compliance.php');
                exit;
            }
        } catch (PDOException $exception) {
            $formError = 'The document could not be removed. Verify that no related records prevent deletion.';
        }
    } else {
        $formValues = [
            'action' => $action,
            'id' => is_scalar($_POST['id'] ?? null) ? (string) $_POST['id'] : '',
            'vehicle_id' => is_scalar($_POST['vehicle_id'] ?? null) ? (string) $_POST['vehicle_id'] : '',
            'document_type' => is_string($_POST['document_type'] ?? null) ? $_POST['document_type'] : '',
            'document_no' => trim(is_string($_POST['document_no'] ?? null) ? $_POST['document_no'] : ''),
            'issue_date' => is_string($_POST['issue_date'] ?? null) ? $_POST['issue_date'] : '',
            'expiry_date' => is_string($_POST['expiry_date'] ?? null) ? $_POST['expiry_date'] : '',
            'notes' => trim(is_string($_POST['notes'] ?? null) ? $_POST['notes'] : ''),
        ];
        $vehicleId = filter_var($formValues['vehicle_id'], FILTER_VALIDATE_INT);
        $documentId = filter_var($formValues['id'], FILTER_VALIDATE_INT);
        $validDate = static function (string $date): bool {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $errors = DateTimeImmutable::getLastErrors();
            return $parsed !== false
                && $parsed->format('Y-m-d') === $date
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
        };

        if (($action === 'update' && (!$documentId || $documentId < 1))
            || !$vehicleId || $vehicleId < 1
            || !in_array($formValues['document_type'], $documentTypes, true)
            || $formValues['document_no'] === '' || strlen($formValues['document_no']) > 100
            || !$validDate($formValues['issue_date']) || !$validDate($formValues['expiry_date'])
            || $formValues['issue_date'] > $formValues['expiry_date']
            || strlen($formValues['notes']) > 5000
        ) {
            $formError = 'Check the vehicle, document type, document number, dates, and notes. Expiry must be on or after the issue date.';
        } else {
            $vehicleLookup = $pdo->prepare('SELECT plate_number FROM fleet_vehicles WHERE id = :id LIMIT 1');
            $vehicleLookup->execute(['id' => $vehicleId]);
            $plateNumber = $vehicleLookup->fetchColumn();

            if (!is_string($plateNumber)) {
                $formError = 'Select a vehicle that exists in the fleet directory.';
            } else {
                $today = new DateTimeImmutable('today');
                $expiryDate = new DateTimeImmutable($formValues['expiry_date']);
                $status = $expiryDate < $today
                    ? 'Expired'
                    : ($expiryDate <= $today->modify('+30 days') ? 'Expiring Soon' : 'Valid');

                try {
                    if ($action === 'create') {
                        $statement = $pdo->prepare(
                            'INSERT INTO vehicle_documents
                                (vehicle_id, plate_number, document_type, document_no, issue_date, expiry_date, status, notes)
                             VALUES
                                (:vehicle_id, :plate_number, :document_type, :document_no, :issue_date, :expiry_date, :status, :notes)'
                        );
                    } else {
                        $statement = $pdo->prepare(
                            'UPDATE vehicle_documents
                             SET vehicle_id = :vehicle_id, plate_number = :plate_number, document_type = :document_type,
                                 document_no = :document_no, issue_date = :issue_date, expiry_date = :expiry_date,
                                 status = :status, notes = :notes
                             WHERE id = :id'
                        );
                    }

                    $parameters = [
                        'vehicle_id' => $vehicleId,
                        'plate_number' => $plateNumber,
                        'document_type' => $formValues['document_type'],
                        'document_no' => $formValues['document_no'],
                        'issue_date' => $formValues['issue_date'],
                        'expiry_date' => $formValues['expiry_date'],
                        'status' => $status,
                        'notes' => $formValues['notes'] === '' ? null : $formValues['notes'],
                    ];
                    if ($action === 'update') {
                        $parameters['id'] = $documentId;
                    }
                    $statement->execute($parameters);

                    if ($action === 'update' && $statement->rowCount() === 0) {
                        $exists = $pdo->prepare('SELECT id FROM vehicle_documents WHERE id = :id LIMIT 1');
                        $exists->execute(['id' => $documentId]);
                        if (!$exists->fetchColumn()) {
                            $formError = 'That document record no longer exists.';
                        }
                    }

                    if ($formError === '') {
                        $_SESSION['flash_message'] = $action === 'create'
                            ? 'Vehicle document registered.'
                            : 'Vehicle document updated.';
                        header('Location: compliance.php');
                        exit;
                    }
                } catch (PDOException $exception) {
                    $formError = 'The document could not be saved. Check the details and try again.';
                }
            }
        }

        $modalOpen = true;
    }
}

$vehiclesStatement = $pdo->query('SELECT id, name, plate_number FROM fleet_vehicles ORDER BY plate_number');
$vehicles = $vehiclesStatement->fetchAll();
$selectedType = $_GET['type'] ?? '';
$selectedStatus = $_GET['status'] ?? '';
$selectedType = is_string($selectedType) && in_array($selectedType, $documentTypes, true) ? $selectedType : '';
$selectedStatus = is_string($selectedStatus) && in_array($selectedStatus, $documentStatuses, true) ? $selectedStatus : '';
$where = [];
$parameters = [];
if ($selectedType !== '') {
    $where[] = 'd.document_type = :document_type';
    $parameters['document_type'] = $selectedType;
}
if ($selectedStatus === 'Expired') {
    $where[] = 'd.expiry_date < CURRENT_DATE';
} elseif ($selectedStatus === 'Expiring Soon') {
    $where[] = 'd.expiry_date >= CURRENT_DATE AND d.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)';
} elseif ($selectedStatus === 'Valid') {
    $where[] = 'd.expiry_date > DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)';
}
$sql = 'SELECT d.id, d.vehicle_id, d.plate_number, v.name AS vehicle_name, d.document_type, d.document_no, d.issue_date,
               d.expiry_date, d.notes,
               CASE
                   WHEN d.expiry_date < CURRENT_DATE THEN \'Expired\'
                   WHEN d.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN \'Expiring Soon\'
                   ELSE \'Valid\'
               END AS current_status
        FROM vehicle_documents d
        JOIN fleet_vehicles v ON v.id = d.vehicle_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY d.expiry_date ASC, d.plate_number ASC';
$documentsStatement = $pdo->prepare($sql);
$documentsStatement->execute($parameters);
$documents = $documentsStatement->fetchAll();
require __DIR__ . '/_layout_start.php';
?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Vehicle documents</h2>
            <p class="mt-1 text-sm text-slate-500">Track LTO, LTFRB, and insurance records and upcoming expirations.</p>
        </div>
        <button type="button" id="add-document" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">Add document</button>
    </div>
    <form method="get" class="mt-5 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end">
        <label class="text-sm font-medium text-slate-700">
            Document type
            <select name="type" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                <option value="">All types</option>
                <?php foreach ($documentTypes as $type): ?>
                    <option value="<?= $escape($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>><?= $escape($type) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="text-sm font-medium text-slate-700">
            Expiry status
            <select name="status" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                <option value="">All statuses</option>
                <?php foreach ($documentStatuses as $status): ?>
                    <option value="<?= $escape($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= $escape($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Apply filters</button>
        <a href="compliance.php" class="rounded-lg px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-white">Clear</a>
    </form>
    <div class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[850px] text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-3 py-3">Vehicle</th>
                    <th scope="col" class="px-3 py-3">Document</th>
                    <th scope="col" class="px-3 py-3">Document no.</th>
                    <th scope="col" class="px-3 py-3">Issue date</th>
                    <th scope="col" class="px-3 py-3">Expiry date</th>
                    <th scope="col" class="px-3 py-3">Status</th>
                    <th scope="col" class="px-3 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($documents as $document): ?>
                    <?php
                    $statusStyle = match ($document['current_status']) {
                        'Expired' => 'bg-red-100 text-red-800',
                        'Expiring Soon' => 'bg-amber-100 text-amber-800',
                        default => 'bg-emerald-100 text-emerald-800',
                    };
                    ?>
                    <tr>
                        <td class="px-3 py-4">
                            <span class="block font-semibold text-slate-900"><?= $escape($document['plate_number']) ?></span>
                            <span class="mt-0.5 block text-xs text-slate-500"><?= $escape($document['vehicle_name']) ?></span>
                        </td>
                        <td class="px-3 py-4"><?= $escape($document['document_type']) ?></td>
                        <td class="px-3 py-4 font-medium text-slate-700"><?= $escape($document['document_no']) ?></td>
                        <td class="px-3 py-4"><?= $escape($document['issue_date']) ?></td>
                        <td class="px-3 py-4"><?= $escape($document['expiry_date']) ?></td>
                        <td class="px-3 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusStyle ?>"><?= $escape($document['current_status']) ?></span></td>
                        <td class="px-3 py-4">
                            <div class="flex items-center gap-3">
                                <button type="button" class="edit-document font-semibold text-blue-700 hover:text-blue-900"
                                    data-id="<?= (int) $document['id'] ?>"
                                    data-vehicle-id="<?= (int) $document['vehicle_id'] ?>"
                                    data-document-type="<?= $escape($document['document_type']) ?>"
                                    data-document-no="<?= $escape($document['document_no']) ?>"
                                    data-issue-date="<?= $escape($document['issue_date']) ?>"
                                    data-expiry-date="<?= $escape($document['expiry_date']) ?>"
                                    data-notes="<?= $escape($document['notes'] ?? '') ?>">Edit</button>
                                <button type="button" class="delete-document font-semibold text-red-700 hover:text-red-900"
                                    data-id="<?= (int) $document['id'] ?>"
                                    data-plate="<?= $escape($document['plate_number']) ?>"
                                    data-type="<?= $escape($document['document_type']) ?>">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$documents): ?>
                    <tr><td colspan="7" class="px-3 py-12 text-center text-slate-500">No vehicle documents match these filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<dialog id="document-modal" class="w-[min(42rem,calc(100%-2rem))] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50">
    <form method="post" class="space-y-5 p-6" id="document-form">
        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
        <input type="hidden" name="action" id="document-action" value="<?= $escape($formValues['action']) ?>">
        <input type="hidden" name="id" id="document-id" value="<?= $escape($formValues['id']) ?>">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="document-modal-title" class="text-xl font-bold text-slate-900">Add vehicle document</h2>
                <p class="mt-1 text-sm text-slate-500">Enter the document details and validity dates.</p>
            </div>
            <button type="button" class="close-document-modal rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Close dialog">✕</button>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium text-slate-700 sm:col-span-2">Vehicle
                <select required name="vehicle_id" id="document-vehicle" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5">
                    <option value="">Choose a vehicle</option>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <option value="<?= (int) $vehicle['id'] ?>" <?= (string) $vehicle['id'] === $formValues['vehicle_id'] ? 'selected' : '' ?>><?= $escape($vehicle['plate_number']) ?> · <?= $escape($vehicle['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Document type
                <select required name="document_type" id="document-type" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5">
                    <option value="">Choose a type</option>
                    <?php foreach ($documentTypes as $type): ?>
                        <option value="<?= $escape($type) ?>" <?= $formValues['document_type'] === $type ? 'selected' : '' ?>><?= $escape($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Document number
                <input required maxlength="100" name="document_no" id="document-no" value="<?= $escape($formValues['document_no']) ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            <label class="text-sm font-medium text-slate-700">Issue date
                <input required type="date" name="issue_date" id="issue-date" value="<?= $escape($formValues['issue_date']) ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            <label class="text-sm font-medium text-slate-700">Expiry date
                <input required type="date" name="expiry_date" id="expiry-date" value="<?= $escape($formValues['expiry_date']) ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            <label class="text-sm font-medium text-slate-700 sm:col-span-2">Notes
                <textarea maxlength="5000" rows="3" name="notes" id="document-notes" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5"><?= $escape($formValues['notes']) ?></textarea>
            </label>
        </div>
        <?php if (!$vehicles): ?>
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Add a vehicle to the fleet directory before registering documents.</p>
        <?php endif; ?>
        <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
            <button type="button" class="close-document-modal rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" id="document-submit" <?= !$vehicles ? 'disabled' : '' ?> class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">Save document</button>
        </div>
    </form>
</dialog>

<dialog id="delete-modal" class="w-[min(28rem,calc(100%-2rem))] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50">
    <form method="post" class="space-y-5 p-6">
        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete-document-id">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Remove document record?</h2>
            <p id="delete-document-message" class="mt-2 text-sm text-slate-600">This action cannot be undone.</p>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" id="cancel-delete" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Remove record</button>
        </div>
    </form>
</dialog>
<script>
    const documentModal = document.getElementById('document-modal');
    const documentAction = document.getElementById('document-action');
    const documentId = document.getElementById('document-id');
    const documentModalTitle = document.getElementById('document-modal-title');
    const documentSubmit = document.getElementById('document-submit');
    const documentFields = {
        vehicle: document.getElementById('document-vehicle'),
        type: document.getElementById('document-type'),
        number: document.getElementById('document-no'),
        issue: document.getElementById('issue-date'),
        expiry: document.getElementById('expiry-date'),
        notes: document.getElementById('document-notes')
    };

    function openDocumentModal(mode, values = {}) {
        documentAction.value = mode;
        documentId.value = values.id || '';
        documentModalTitle.textContent = mode === 'update' ? 'Edit vehicle document' : 'Add vehicle document';
        documentSubmit.textContent = mode === 'update' ? 'Save changes' : 'Save document';
        documentFields.vehicle.value = values.vehicleId || '';
        documentFields.type.value = values.type || '';
        documentFields.number.value = values.number || '';
        documentFields.issue.value = values.issue || '';
        documentFields.expiry.value = values.expiry || '';
        documentFields.notes.value = values.notes || '';
        documentModal.showModal();
    }

    document.getElementById('add-document').addEventListener('click', () => openDocumentModal('create'));
    document.querySelectorAll('.edit-document').forEach((button) => {
        button.addEventListener('click', () => openDocumentModal('update', {
            id: button.dataset.id,
            vehicleId: button.dataset.vehicleId,
            type: button.dataset.documentType,
            number: button.dataset.documentNo,
            issue: button.dataset.issueDate,
            expiry: button.dataset.expiryDate,
            notes: button.dataset.notes
        }));
    });
    document.querySelectorAll('.close-document-modal').forEach((button) => button.addEventListener('click', () => documentModal.close()));
    if (<?= $modalOpen ? 'true' : 'false' ?>) {
        documentModal.showModal();
    }

    const deleteModal = document.getElementById('delete-modal');
    document.querySelectorAll('.delete-document').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('delete-document-id').value = button.dataset.id;
            document.getElementById('delete-document-message').textContent =
                `Remove the ${button.dataset.type} document for ${button.dataset.plate}? This action cannot be undone.`;
            deleteModal.showModal();
        });
    });
    document.getElementById('cancel-delete').addEventListener('click', () => deleteModal.close());
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>

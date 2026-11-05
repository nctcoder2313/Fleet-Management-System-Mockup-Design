<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'geofencing';
$pageTitle = 'Geofencing & Speed Setup';
$formError = '';
$modalOpen = false;
$formValues = [
    'action' => 'create',
    'id' => '',
    'zone_name' => '',
    'allowed_area_boundary' => '',
    'max_speed_limit_kmh' => '',
    'alert_email_notification' => '0',
    'status' => 'Active',
];

$parseBoundary = static function (string $boundary): ?string {
    $lines = preg_split('/\R/', trim($boundary));
    if ($lines === false || count($lines) < 3 || count($lines) > 50) {
        return null;
    }

    $points = [];
    $uniquePoints = [];
    foreach ($lines as $line) {
        if (preg_match('/^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/D', $line, $matches) !== 1) {
            return null;
        }
        $latitude = (float) $matches[1];
        $longitude = (float) $matches[2];
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }
        $points[] = $matches[1] . ', ' . $matches[2];
        $uniquePoints[] = sprintf('%.15g,%.15g', $latitude, $longitude);
    }

    if (count(array_unique($uniquePoints)) < 3) {
        return null;
    }

    return implode("\n", $points);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $action = $_POST['action'] ?? '';
    if (!is_string($action) || !in_array($action, ['create', 'update', 'delete', 'toggle'], true)) {
        http_response_code(400);
        exit('Invalid geofence action.');
    }

    $ruleId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'delete' || $action === 'toggle') {
        if (!$ruleId || $ruleId < 1) {
            http_response_code(400);
            exit('Invalid geofence rule.');
        }

        if ($previewMode) {
            $rules = &$_SESSION['admin_geofence_preview'];
            $ruleIndex = null;
            foreach ($rules as $index => $rule) {
                if ((int) $rule['id'] === $ruleId) {
                    $ruleIndex = $index;
                    break;
                }
            }
            if ($ruleIndex === null) {
                $formError = 'That geofence rule no longer exists.';
            } elseif ($action === 'delete') {
                array_splice($rules, $ruleIndex, 1);
                $_SESSION['flash_message'] = 'Geofence rule removed from this preview.';
                header('Location: ' . $adminPageUrl('geofencing.php'));
                exit;
            } else {
                $rules[$ruleIndex]['status'] = $rules[$ruleIndex]['status'] === 'Active' ? 'Disabled' : 'Active';
                $_SESSION['flash_message'] = 'Geofence rule status updated in this preview.';
                header('Location: ' . $adminPageUrl('geofencing.php'));
                exit;
            }
        } else {
            if ($action === 'delete') {
                $statement = $pdo->prepare('DELETE FROM geofence_rules WHERE id = :id');
                $statement->execute(['id' => $ruleId]);
            } else {
                $statement = $pdo->prepare(
                    "UPDATE geofence_rules
                     SET status = CASE WHEN status = 'Active' THEN 'Disabled' ELSE 'Active' END
                     WHERE id = :id"
                );
                $statement->execute(['id' => $ruleId]);
            }

            if ($statement->rowCount() !== 1) {
                $formError = 'That geofence rule no longer exists.';
            } else {
                $_SESSION['flash_message'] = $action === 'delete'
                    ? 'Geofence rule removed.'
                    : 'Geofence rule status updated.';
                header('Location: ' . $adminPageUrl('geofencing.php'));
                exit;
            }
        }
    } else {
        $formValues = [
            'action' => $action,
            'id' => is_scalar($_POST['id'] ?? null) ? (string) $_POST['id'] : '',
            'zone_name' => trim(is_string($_POST['zone_name'] ?? null) ? $_POST['zone_name'] : ''),
            'allowed_area_boundary' => trim(is_string($_POST['allowed_area_boundary'] ?? null) ? $_POST['allowed_area_boundary'] : ''),
            'max_speed_limit_kmh' => is_scalar($_POST['max_speed_limit_kmh'] ?? null) ? (string) $_POST['max_speed_limit_kmh'] : '',
            'alert_email_notification' => isset($_POST['alert_email_notification']) ? '1' : '0',
            'status' => is_string($_POST['status'] ?? null) ? $_POST['status'] : '',
        ];
        $ruleId = filter_var($formValues['id'], FILTER_VALIDATE_INT);
        $speedLimit = filter_var($formValues['max_speed_limit_kmh'], FILTER_VALIDATE_INT);
        $boundary = $parseBoundary($formValues['allowed_area_boundary']);

        if (($action === 'update' && (!$ruleId || $ruleId < 1))
            || $formValues['zone_name'] === '' || strlen($formValues['zone_name']) > 150
            || $speedLimit === false || $speedLimit < 1 || $speedLimit > 300
            || $boundary === null
            || !in_array($formValues['status'], ['Active', 'Disabled'], true)
        ) {
            $formError = 'Enter a zone name, a speed limit from 1 to 300 km/h, and at least three distinct valid latitude, longitude points. Choose a valid status.';
        } else {
            $rule = [
                'zone_name' => $formValues['zone_name'],
                'allowed_area_boundary' => $boundary,
                'max_speed_limit_kmh' => $speedLimit,
                'alert_email_notification' => $formValues['alert_email_notification'] === '1' ? 1 : 0,
                'status' => $formValues['status'],
            ];

            if ($previewMode) {
                $rules = &$_SESSION['admin_geofence_preview'];
                if ($action === 'create') {
                    $ids = array_column($rules, 'id');
                    $rule['id'] = $ids ? max($ids) + 1 : 1;
                    $rule['created_at'] = date('Y-m-d H:i:s');
                    $rules[] = $rule;
                } else {
                    $ruleIndex = null;
                    foreach ($rules as $index => $existingRule) {
                        if ((int) $existingRule['id'] === $ruleId) {
                            $ruleIndex = $index;
                            break;
                        }
                    }
                    if ($ruleIndex === null) {
                        $formError = 'That geofence rule no longer exists.';
                    } else {
                        $rules[$ruleIndex] = array_merge($rules[$ruleIndex], $rule);
                    }
                }
            } else {
                if ($action === 'create') {
                    $statement = $pdo->prepare(
                        'INSERT INTO geofence_rules
                            (zone_name, allowed_area_boundary, max_speed_limit_kmh,
                             alert_email_notification, status)
                         VALUES
                            (:zone_name, :allowed_area_boundary, :max_speed_limit_kmh,
                             :alert_email_notification, :status)'
                    );
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE geofence_rules
                         SET zone_name = :zone_name, allowed_area_boundary = :allowed_area_boundary,
                             max_speed_limit_kmh = :max_speed_limit_kmh,
                             alert_email_notification = :alert_email_notification, status = :status
                         WHERE id = :id'
                    );
                    $rule['id'] = $ruleId;
                }
                $statement->execute($rule);

                if ($action === 'update' && $statement->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT id FROM geofence_rules WHERE id = :id LIMIT 1');
                    $exists->execute(['id' => $ruleId]);
                    if (!$exists->fetchColumn()) {
                        $formError = 'That geofence rule no longer exists.';
                    }
                }
            }

            if ($formError === '') {
                $_SESSION['flash_message'] = $action === 'create'
                    ? 'Geofence rule created' . ($previewMode ? ' in this preview.' : '.')
                    : 'Geofence rule updated' . ($previewMode ? ' in this preview.' : '.');
                header('Location: ' . $adminPageUrl('geofencing.php'));
                exit;
            }
        }

        $modalOpen = true;
    }
}

if ($previewMode) {
    $rules = $_SESSION['admin_geofence_preview'];
} else {
    $rulesStatement = $pdo->query(
        'SELECT id, zone_name, allowed_area_boundary, max_speed_limit_kmh,
                alert_email_notification, status, created_at
         FROM geofence_rules
         ORDER BY status ASC, zone_name ASC'
    );
    $rules = $rulesStatement->fetchAll();
}
require __DIR__ . '/_layout_start.php';
?>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Geofence zones and speed limits</h2>
            <p class="mt-1 text-sm text-slate-500">Configure polygon boundaries, allowed speeds, and email alert preferences.</p>
        </div>
        <button type="button" id="add-rule" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">Add zone rule</button>
    </div>
    <div class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-3 py-3">Zone</th>
                    <th scope="col" class="px-3 py-3">Boundary coordinates</th>
                    <th scope="col" class="px-3 py-3">Max speed</th>
                    <th scope="col" class="px-3 py-3">Email alerts</th>
                    <th scope="col" class="px-3 py-3">Status</th>
                    <th scope="col" class="px-3 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($rules as $rule): ?>
                    <?php $enabled = $rule['status'] === 'Active'; ?>
                    <tr>
                        <td class="px-3 py-4">
                            <span class="block font-semibold text-slate-900"><?= $escape($rule['zone_name']) ?></span>
                            <span class="mt-0.5 block text-xs text-slate-500">Added <?= $escape(substr((string) $rule['created_at'], 0, 10)) ?></span>
                        </td>
                        <td class="max-w-sm px-3 py-4">
                            <code class="block whitespace-pre-line text-xs text-slate-600"><?= $escape($rule['allowed_area_boundary']) ?></code>
                        </td>
                        <td class="px-3 py-4 font-semibold text-slate-800"><?= (int) $rule['max_speed_limit_kmh'] ?> km/h</td>
                        <td class="px-3 py-4"><?= (int) $rule['alert_email_notification'] === 1 ? 'Enabled' : 'Disabled' ?></td>
                        <td class="px-3 py-4">
                            <form method="post" class="inline-flex items-center gap-2">
                                <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $rule['id'] ?>">
                                <button type="submit" role="switch" aria-checked="<?= $enabled ? 'true' : 'false' ?>" aria-label="Toggle <?= $escape($rule['zone_name']) ?> status" class="relative inline-flex h-6 w-11 items-center rounded-full transition <?= $enabled ? 'bg-emerald-600' : 'bg-slate-300' ?>">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition <?= $enabled ? 'translate-x-6' : 'translate-x-1' ?>"></span>
                                </button>
                                <span class="text-xs font-semibold <?= $enabled ? 'text-emerald-800' : 'text-slate-600' ?>"><?= $escape($rule['status']) ?></span>
                            </form>
                        </td>
                        <td class="px-3 py-4">
                            <div class="flex items-center gap-3">
                                <button type="button" class="edit-rule font-semibold text-blue-700 hover:text-blue-900"
                                    data-id="<?= (int) $rule['id'] ?>"
                                    data-zone-name="<?= $escape($rule['zone_name']) ?>"
                                    data-boundary="<?= $escape($rule['allowed_area_boundary']) ?>"
                                    data-speed="<?= (int) $rule['max_speed_limit_kmh'] ?>"
                                    data-alert="<?= (int) $rule['alert_email_notification'] ?>"
                                    data-status="<?= $escape($rule['status']) ?>">Edit</button>
                                <button type="button" class="delete-rule font-semibold text-red-700 hover:text-red-900"
                                    data-id="<?= (int) $rule['id'] ?>"
                                    data-zone-name="<?= $escape($rule['zone_name']) ?>">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rules): ?>
                    <tr><td colspan="6" class="px-3 py-12 text-center text-slate-500">No geofence rules configured yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<dialog id="rule-modal" class="w-[min(42rem,calc(100%-2rem))] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50">
    <form method="post" class="space-y-5 p-6" id="rule-form">
        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
        <input type="hidden" name="action" id="rule-action" value="<?= $escape($formValues['action']) ?>">
        <input type="hidden" name="id" id="rule-id" value="<?= $escape($formValues['id']) ?>">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="rule-modal-title" class="text-xl font-bold text-slate-900">Add geofence rule</h2>
                <p class="mt-1 text-sm text-slate-500">Enter at least three polygon points as latitude, longitude pairs, one per line.</p>
            </div>
            <button type="button" class="close-rule-modal rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Close dialog">✕</button>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium text-slate-700 sm:col-span-2">Zone name
                <input required maxlength="150" name="zone_name" id="zone-name" value="<?= $escape($formValues['zone_name']) ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            <label class="text-sm font-medium text-slate-700">Maximum speed (km/h)
                <input required type="number" min="1" max="300" step="1" name="max_speed_limit_kmh" id="zone-speed" value="<?= $escape($formValues['max_speed_limit_kmh']) ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            <label class="text-sm font-medium text-slate-700">Rule status
                <select required name="status" id="zone-status" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5">
                    <option value="Active" <?= $formValues['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Disabled" <?= $formValues['status'] === 'Disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700 sm:col-span-2">Perimeter coordinates
                <textarea required rows="6" name="allowed_area_boundary" id="zone-boundary" placeholder="14.6760, 121.0437&#10;14.6812, 121.0501&#10;14.6718, 121.0540" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-sm"><?= $escape($formValues['allowed_area_boundary']) ?></textarea>
                <span class="mt-1 block text-xs font-normal text-slate-500">Latitude: -90 to 90. Longitude: -180 to 180. Maximum 50 points.</span>
            </label>
            <label class="flex items-start gap-3 text-sm text-slate-700 sm:col-span-2">
                <input type="checkbox" name="alert_email_notification" id="zone-alert" value="1" <?= $formValues['alert_email_notification'] === '1' ? 'checked' : '' ?> class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-700">
                <span><span class="block font-medium">Enable email alerts</span><span class="mt-0.5 block text-xs text-slate-500">Store the notification preference for this geofence rule.</span></span>
            </label>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
            <button type="button" class="close-rule-modal rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Save rule</button>
        </div>
    </form>
</dialog>

<dialog id="delete-rule-modal" class="w-[min(28rem,calc(100%-2rem))] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50">
    <form method="post" class="space-y-5 p-6">
        <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete-rule-id">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Delete geofence rule?</h2>
            <p id="delete-rule-message" class="mt-2 text-sm text-slate-600">This action cannot be undone.</p>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" id="cancel-delete-rule" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Delete rule</button>
        </div>
    </form>
</dialog>
<script>
    const ruleModal = document.getElementById('rule-modal');
    const ruleFields = {
        zone: document.getElementById('zone-name'),
        speed: document.getElementById('zone-speed'),
        status: document.getElementById('zone-status'),
        boundary: document.getElementById('zone-boundary'),
        alert: document.getElementById('zone-alert')
    };

    function openRuleModal(mode, values = {}) {
        document.getElementById('rule-action').value = mode;
        document.getElementById('rule-id').value = values.id || '';
        document.getElementById('rule-modal-title').textContent =
            mode === 'update' ? 'Edit geofence rule' : 'Add geofence rule';
        ruleFields.zone.value = values.zone || '';
        ruleFields.speed.value = values.speed || '';
        ruleFields.status.value = values.status || 'Active';
        ruleFields.boundary.value = values.boundary || '';
        ruleFields.alert.checked = values.alert === '1';
        ruleModal.showModal();
    }

    document.getElementById('add-rule').addEventListener('click', () => openRuleModal('create'));
    document.querySelectorAll('.edit-rule').forEach((button) => {
        button.addEventListener('click', () => openRuleModal('update', {
            id: button.dataset.id,
            zone: button.dataset.zoneName,
            speed: button.dataset.speed,
            status: button.dataset.status,
            boundary: button.dataset.boundary,
            alert: button.dataset.alert
        }));
    });
    document.querySelectorAll('.close-rule-modal').forEach((button) => {
        button.addEventListener('click', () => ruleModal.close());
    });
    if (<?= $modalOpen ? 'true' : 'false' ?>) {
        ruleModal.showModal();
    }

    const deleteRuleModal = document.getElementById('delete-rule-modal');
    document.querySelectorAll('.delete-rule').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('delete-rule-id').value = button.dataset.id;
            document.getElementById('delete-rule-message').textContent =
                `Delete “${button.dataset.zoneName}”? This action cannot be undone.`;
            deleteRuleModal.showModal();
        });
    });
    document.getElementById('cancel-delete-rule').addEventListener('click', () => deleteRuleModal.close());
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>

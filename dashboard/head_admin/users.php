<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'users';
$pageTitle = 'User management';
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $action = $_POST['action'] ?? '';
    if (!is_string($action)) {
        http_response_code(400);
        exit('Invalid account action.');
    }

    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    if ($action === 'create' || $action === 'update') {
        $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        $email = strtolower(trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : ''));
        $role = $_POST['role'] ?? '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($name === '' || strlen($name) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || !is_string($role) || !in_array($role, $manageableRoles, true)
            || ($action === 'create' && strlen($password) < 12)
        ) {
            $formError = 'Check the account details. Names must be 150 characters or fewer, email must be valid, and initial passwords must contain at least 12 characters.';
        } elseif ($action === 'update' && (!$userId || $userId === (int) $headAdmin['id'])) {
            $formError = 'The Head Admin account cannot be changed from this directory.';
        } else {
            $existingAccount = null;
            if ($action === 'update') {
                if ($previewMode) {
                    foreach ($_SESSION['head_admin_preview_data']['users'] as $account) {
                        if ((int) $account['id'] === $userId) {
                            $existingAccount = $account;
                            break;
                        }
                    }
                } else {
                    $lookup = $pdo->prepare('SELECT id, role FROM users WHERE id = :id LIMIT 1');
                    $lookup->execute(['id' => $userId]);
                    $existingAccount = $lookup->fetch() ?: null;
                }

                if (!$existingAccount || $existingAccount['role'] === 'head_admin') {
                    $formError = 'This account does not exist or is protected from editing.';
                }
            }

            if ($formError === '') {
                if ($previewMode) {
                    $duplicateEmail = false;
                    foreach ($_SESSION['head_admin_preview_data']['users'] as $account) {
                        if (strcasecmp($account['email'], $email) === 0 && (int) $account['id'] !== (int) ($userId ?? 0)) {
                            $duplicateEmail = true;
                            break;
                        }
                    }
                } else {
                    $emailSql = 'SELECT id FROM users WHERE email = :email';
                    $emailParams = ['email' => $email];
                    if ($action === 'update') {
                        $emailSql .= ' AND id <> :user_id';
                        $emailParams['user_id'] = $userId;
                    }
                    $emailLookup = $pdo->prepare($emailSql . ' LIMIT 1');
                    $emailLookup->execute($emailParams);
                    $duplicateEmail = (bool) $emailLookup->fetchColumn();
                }

                if ($duplicateEmail) {
                    $formError = 'An account already uses that email address.';
                } elseif ($previewMode) {
                    if ($action === 'create') {
                        $ids = array_column($_SESSION['head_admin_preview_data']['users'], 'id');
                        $userId = $ids ? max($ids) + 1 : 1;
                        $_SESSION['head_admin_preview_data']['users'][] = [
                            'id' => $userId,
                            'name' => $name,
                            'email' => $email,
                            'role' => $role,
                            'status' => 'active',
                            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                            'created_at' => date('Y-m-d H:i:s'),
                        ];
                        headAdminRecordAudit('USER_CREATED', [
                            'user_id' => $userId,
                            'user_name' => $name,
                            'user_email' => $email,
                            'role' => $role,
                        ]);
                        headAdminRedirect(headAdminUrl('users.php'), 'User account created in the preview.');
                    }

                    foreach ($_SESSION['head_admin_preview_data']['users'] as &$account) {
                        if ((int) $account['id'] === $userId) {
                            $account['name'] = $name;
                            $account['email'] = $email;
                            $account['role'] = $role;
                            break;
                        }
                    }
                    unset($account);
                    foreach ($_SESSION['head_admin_preview_data']['pending_requests'] as &$request) {
                        if ((int) $request['requester_id'] === $userId && $request['status'] === 'PENDING') {
                            $request['requester_name'] = $name;
                            $request['requester_email'] = $email;
                        }
                    }
                    unset($request);
                    headAdminRecordAudit('USER_UPDATED', [
                        'user_id' => $userId,
                        'user_name' => $name,
                        'user_email' => $email,
                        'role' => $role,
                    ]);
                    headAdminRedirect(headAdminUrl('users.php'), 'User account updated in the preview.');
                } else {
                    try {
                        $pdo->beginTransaction();
                        if ($action === 'create') {
                            $insert = $pdo->prepare(
                                'INSERT INTO users (name, email, password_hash, role, status)
                                 VALUES (:name, :email, :password_hash, :role, \'active\')'
                            );
                            $insert->execute([
                                'name' => $name,
                                'email' => $email,
                                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                                'role' => $role,
                            ]);
                            $userId = (int) $pdo->lastInsertId();
                            headAdminRecordAudit('USER_CREATED', [
                                'user_id' => $userId,
                                'user_name' => $name,
                                'user_email' => $email,
                                'role' => $role,
                            ]);
                        } else {
                            $lockAccount = $pdo->prepare('SELECT role FROM users WHERE id = :id FOR UPDATE');
                            $lockAccount->execute(['id' => $userId]);
                            $lockedAccount = $lockAccount->fetch();
                            if (!$lockedAccount || $lockedAccount['role'] === 'head_admin') {
                                throw new RuntimeException('This account no longer exists or is protected.');
                            }
                            $updateSql = 'UPDATE users SET name = :name, email = :email, role = :role';
                            $params = ['name' => $name, 'email' => $email, 'role' => $role, 'id' => $userId];
                            $updateSql .= ' WHERE id = :id AND role <> \'head_admin\'';
                            $update = $pdo->prepare($updateSql);
                            $update->execute($params);
                            headAdminRecordAudit('USER_UPDATED', [
                                'user_id' => $userId,
                                'user_name' => $name,
                                'user_email' => $email,
                                'role' => $role,
                            ]);
                        }
                        $pdo->commit();
                    } catch (PDOException $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        if ($exception->getCode() === '23000') {
                            $formError = 'An account already uses that email address.';
                        } else {
                            throw $exception;
                        }
                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $exception;
                    }

                    if ($formError === '') {
                        headAdminRedirect(
                            headAdminUrl('users.php'),
                            $action === 'create' ? 'User account created.' : 'User account updated.'
                        );
                    }
                }
            }
        }
    } elseif ($action === 'reset_password') {
        $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
        if (!$userId || $userId === (int) $headAdmin['id']) {
            $formError = 'The Head Admin account cannot be reset from this directory.';
        } elseif (strlen($newPassword) < 12 || !hash_equals($newPassword, $confirmPassword)) {
            $formError = 'Enter a password with at least 12 characters and confirm it exactly.';
        } else {
            $targetAccount = null;
            if ($previewMode) {
                foreach ($_SESSION['head_admin_preview_data']['users'] as $account) {
                    if ((int) $account['id'] === $userId) {
                        $targetAccount = $account;
                        break;
                    }
                }
            } else {
                $lookup = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1');
                $lookup->execute(['id' => $userId]);
                $targetAccount = $lookup->fetch() ?: null;
            }

            if (!$targetAccount || $targetAccount['role'] === 'head_admin') {
                $formError = 'This account does not exist or is protected from password reset.';
            } elseif ($previewMode) {
                foreach ($_SESSION['head_admin_preview_data']['users'] as &$account) {
                    if ((int) $account['id'] === $userId) {
                        $account['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                        break;
                    }
                }
                unset($account);
                headAdminRecordAudit('USER_PASSWORD_RESET', [
                    'user_id' => $userId,
                    'user_name' => $targetAccount['name'],
                    'user_email' => $targetAccount['email'],
                ]);
                headAdminRedirect(headAdminUrl('users.php'), 'Sample password hash updated in the preview. No company credentials were changed.');
            } else {
                $pdo->beginTransaction();
                try {
                    $lockAccount = $pdo->prepare('SELECT name, email, role FROM users WHERE id = :id FOR UPDATE');
                    $lockAccount->execute(['id' => $userId]);
                    $lockedAccount = $lockAccount->fetch();
                    if (!$lockedAccount || $lockedAccount['role'] === 'head_admin') {
                        throw new RuntimeException('This account no longer exists or is protected from password reset.');
                    }
                    $update = $pdo->prepare(
                        'UPDATE users
                         SET password_hash = :password_hash
                         WHERE id = :id AND role <> \'head_admin\''
                    );
                    $update->execute([
                        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                        'id' => $userId,
                    ]);
                    if ($update->rowCount() !== 1) {
                        throw new RuntimeException('The account could not be reset. Refresh the directory and try again.');
                    }
                    headAdminRecordAudit('USER_PASSWORD_RESET', [
                        'user_id' => $userId,
                        'user_name' => $lockedAccount['name'],
                        'user_email' => $lockedAccount['email'],
                    ]);
                    $pdo->commit();
                    headAdminRedirect(headAdminUrl('users.php'), 'The user password has been reset.');
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            }
        }
    } elseif ($action === 'toggle_status') {
        if (!$userId || $userId === (int) $headAdmin['id']) {
            $formError = 'The Head Admin account cannot be deactivated or changed from this directory.';
        } else {
            if ($previewMode) {
                $accountIndex = null;
                foreach ($_SESSION['head_admin_preview_data']['users'] as $index => $account) {
                    if ((int) $account['id'] === $userId) {
                        $accountIndex = $index;
                        break;
                    }
                }
                if ($accountIndex === null || $_SESSION['head_admin_preview_data']['users'][$accountIndex]['role'] === 'head_admin') {
                    $formError = 'This account does not exist or is protected.';
                } else {
                    $account = &$_SESSION['head_admin_preview_data']['users'][$accountIndex];
                    $account['status'] = $account['status'] === 'active' ? 'inactive' : 'active';
                    $event = $account['status'] === 'active' ? 'USER_REACTIVATED' : 'USER_DEACTIVATED';
                    $accountDetails = [
                        'user_id' => $userId,
                        'user_name' => $account['name'],
                        'user_email' => $account['email'],
                    ];
                    unset($account);
                    headAdminRecordAudit($event, $accountDetails);
                    headAdminRedirect(headAdminUrl('users.php'), $event === 'USER_REACTIVATED' ? 'User account reactivated in the preview.' : 'User account deactivated in the preview.');
                }
            } else {
                $lookup = $pdo->prepare('SELECT name, email, role, status FROM users WHERE id = :id LIMIT 1');
                $lookup->execute(['id' => $userId]);
                $account = $lookup->fetch();
                if (!$account || $account['role'] === 'head_admin') {
                    $formError = 'This account does not exist or is protected.';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $lockAccount = $pdo->prepare('SELECT name, email, role, status FROM users WHERE id = :id FOR UPDATE');
                        $lockAccount->execute(['id' => $userId]);
                        $lockedAccount = $lockAccount->fetch();
                        if (!$lockedAccount || $lockedAccount['role'] === 'head_admin') {
                            throw new RuntimeException('This account no longer exists or is protected.');
                        }
                        $newStatus = $lockedAccount['status'] === 'active' ? 'inactive' : 'active';
                        $event = $newStatus === 'active' ? 'USER_REACTIVATED' : 'USER_DEACTIVATED';
                        $update = $pdo->prepare('UPDATE users SET status = :status WHERE id = :id AND role <> \'head_admin\'');
                        $update->execute(['status' => $newStatus, 'id' => $userId]);
                        if ($update->rowCount() !== 1) {
                            throw new RuntimeException('The account status was not changed. Refresh the directory and try again.');
                        }
                        headAdminRecordAudit($event, [
                            'user_id' => $userId,
                            'user_name' => $lockedAccount['name'],
                            'user_email' => $lockedAccount['email'],
                        ]);
                        $pdo->commit();
                        headAdminRedirect(headAdminUrl('users.php'), $newStatus === 'active' ? 'User account reactivated.' : 'User account deactivated.');
                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $exception;
                    }
                }
            }
        }
    } else {
        http_response_code(400);
        exit('Invalid account action.');
    }
}

$requestedRole = $_GET['role'] ?? '';
$roleFilter = is_string($requestedRole) && array_key_exists($requestedRole, $roleLabels) ? $requestedRole : '';
$requestedStatus = $_GET['status'] ?? 'all';
$statusFilter = is_string($requestedStatus) && in_array($requestedStatus, ['all', 'active', 'inactive'], true)
    ? $requestedStatus
    : 'all';
$requestedSearch = $_GET['q'] ?? '';
$search = is_string($requestedSearch) ? trim($requestedSearch) : '';
$requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$pageNumber = $requestedPage && $requestedPage > 0 ? $requestedPage : 1;
$pageSize = 25;
$accounts = [];

if ($previewMode) {
    $matchingAccounts = array_values(array_filter(
        $_SESSION['head_admin_preview_data']['users'],
        static function (array $account) use ($roleFilter, $statusFilter, $search): bool {
            $matchesRole = $roleFilter === '' || $account['role'] === $roleFilter;
            $matchesStatus = $statusFilter === 'all' || $account['status'] === $statusFilter;
            $matchesSearch = $search === '' || stripos($account['name'] . ' ' . $account['email'], $search) !== false;
            return $matchesRole && $matchesStatus && $matchesSearch;
        }
    ));
    $totalMatches = count($matchingAccounts);
    $pageCount = max(1, (int) ceil($totalMatches / $pageSize));
    $pageNumber = min($pageNumber, $pageCount);
    $accounts = array_slice($matchingAccounts, ($pageNumber - 1) * $pageSize, $pageSize);
} else {
    $conditions = [];
    $parameters = [];
    if ($roleFilter !== '') {
        $conditions[] = 'role = :role';
        $parameters['role'] = $roleFilter;
    }
    if ($statusFilter !== 'all') {
        $conditions[] = 'status = :status';
        $parameters['status'] = $statusFilter;
    }
    if ($search !== '') {
        $conditions[] = '(name LIKE :name_search OR email LIKE :email_search)';
        $parameters['name_search'] = '%' . $search . '%';
        $parameters['email_search'] = '%' . $search . '%';
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $countStatement = $pdo->prepare("SELECT COUNT(*) FROM users {$where}");
    $countStatement->execute($parameters);
    $totalMatches = (int) $countStatement->fetchColumn();
    $pageCount = max(1, (int) ceil($totalMatches / $pageSize));
    $pageNumber = min($pageNumber, $pageCount);
    $offset = ($pageNumber - 1) * $pageSize;
    $statement = $pdo->prepare(
        "SELECT id, name, email, role, status, created_at
         FROM users {$where}
         ORDER BY name ASC
         LIMIT {$pageSize} OFFSET {$offset}"
    );
    $statement->execute($parameters);
    $accounts = $statement->fetchAll();
}

$firstResult = $totalMatches === 0 ? 0 : (($pageNumber - 1) * $pageSize) + 1;
$lastResult = min($pageNumber * $pageSize, $totalMatches);
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$editAccount = null;
if ($editId) {
    foreach ($accounts as $account) {
        if ((int) $account['id'] === $editId) {
            $editAccount = $account;
            break;
        }
    }
    if (!$editAccount && $previewMode) {
        foreach ($_SESSION['head_admin_preview_data']['users'] as $account) {
            if ((int) $account['id'] === $editId) {
                $editAccount = $account;
                break;
            }
        }
    } elseif (!$editAccount && !$previewMode) {
        $lookup = $pdo->prepare('SELECT id, name, email, role, status FROM users WHERE id = :id LIMIT 1');
        $lookup->execute(['id' => $editId]);
        $editAccount = $lookup->fetch() ?: null;
    }
    if ($editAccount && ($editAccount['role'] === 'head_admin' || (int) $editAccount['id'] === (int) $headAdmin['id'])) {
        $editAccount = null;
    }
}
$resetId = filter_var($_GET['reset_password'] ?? null, FILTER_VALIDATE_INT);
$resetAccount = null;
if ($resetId) {
    if ($previewMode) {
        foreach ($_SESSION['head_admin_preview_data']['users'] as $account) {
            if ((int) $account['id'] === $resetId) {
                $resetAccount = $account;
                break;
            }
        }
    } else {
        $lookup = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1');
        $lookup->execute(['id' => $resetId]);
        $resetAccount = $lookup->fetch() ?: null;
    }
    if ($resetAccount && ($resetAccount['role'] === 'head_admin' || (int) $resetAccount['id'] === (int) $headAdmin['id'])) {
        $resetAccount = null;
    }
}

$activePage = 'users';
require __DIR__ . '/_layout_start.php';
?>
<?php if ($formError !== ''): ?>
    <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900"><?= $escape($formError) ?></p>
<?php endif; ?>

<section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-sm font-medium text-slate-500">Accounts and role assignments</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">User directory</h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-600">Create accounts, maintain role assignments, and deactivate access when a team member leaves. Account changes are recorded in audit history.</p>
    </div>
    <a href="<?= $escape(headAdminUrl('users.php', ['action' => 'create'])) ?>" class="inline-flex w-fit items-center rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800">Create user account</a>
</section>

<?php if (($_GET['action'] ?? '') === 'create' || $editAccount): ?>
    <?php $isEditing = is_array($editAccount); ?>
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-5">
            <h2 class="text-lg font-bold text-slate-900"><?= $isEditing ? 'Edit user account' : 'Create user account' ?></h2>
            <p class="mt-1 text-sm text-slate-500"><?= $isEditing ? 'Update the account profile and assigned role.' : 'New accounts are created as active. Head Admin access must be provisioned separately.' ?></p>
        </div>
        <form method="post" class="grid gap-4 sm:grid-cols-2">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
            <?php if ($isEditing): ?>
                <input type="hidden" name="user_id" value="<?= (int) $editAccount['id'] ?>">
            <?php endif; ?>
            <label class="text-sm font-medium text-slate-700">
                Full name
                <input name="name" required maxlength="150" value="<?= $escape($editAccount['name'] ?? '') ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </label>
            <label class="text-sm font-medium text-slate-700">
                Work email
                <input type="email" name="email" required maxlength="254" value="<?= $escape($editAccount['email'] ?? '') ?>" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </label>
            <label class="text-sm font-medium text-slate-700">
                Assigned role
                <select name="role" required class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-normal outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <?php foreach ($manageableRoles as $role): ?>
                        <option value="<?= $escape($role) ?>" <?= ($editAccount['role'] ?? 'user') === $role ? 'selected' : '' ?>><?= $escape($roleLabels[$role]) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">
                <?php if ($isEditing): ?>
                    Account password
                    <span class="mt-1.5 block rounded-lg bg-slate-50 px-3 py-2.5 font-normal text-slate-600">Use the separate password reset action after saving account changes.</span>
                <?php else: ?>
                    Initial password
                    <input type="password" name="password" required minlength="12" autocomplete="new-password" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <span class="mt-1 block text-xs font-normal text-slate-500">At least 12 characters. Passwords are stored as secure hashes.</span>
                <?php endif; ?>
            </label>
            <div class="flex flex-wrap gap-2 sm:col-span-2">
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800"><?= $isEditing ? 'Save changes' : 'Create account' ?></button>
                <a href="<?= $escape(headAdminUrl('users.php')) ?>" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php if ($resetAccount): ?>
    <section class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-5">
            <p class="text-sm font-medium text-amber-800">Credential administration</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">Reset password for <?= $escape($resetAccount['name']) ?></h2>
            <p class="mt-1 text-sm text-slate-500"><?= $escape($resetAccount['email']) ?> · <?= $escape($roleLabels[$resetAccount['role']] ?? $resetAccount['role']) ?></p>
            <p class="mt-2 text-sm text-slate-600">Choose a temporary password and communicate it to the employee through an approved company channel. The password itself is never written to audit history.</p>
        </div>
        <form method="post" class="grid gap-4 sm:max-w-2xl sm:grid-cols-2">
            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" value="<?= (int) $resetAccount['id'] ?>">
            <label class="text-sm font-medium text-slate-700">
                New temporary password
                <input type="password" name="new_password" required minlength="12" autocomplete="new-password" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </label>
            <label class="text-sm font-medium text-slate-700">
                Confirm password
                <input type="password" name="confirm_password" required minlength="12" autocomplete="new-password" class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </label>
            <div class="flex flex-wrap gap-2 sm:col-span-2">
                <button type="submit" class="rounded-lg bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-800">Reset password</button>
                <a href="<?= $escape(headAdminUrl('users.php')) ?>" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 p-5 sm:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Accounts</h2>
                <p class="mt-1 text-sm text-slate-500">Showing <?= $firstResult ?>–<?= $lastResult ?> of <?= $totalMatches ?> matching accounts. Head Admin accounts are protected.</p>
            </div>
        </div>
        <form method="get" class="mt-4 grid gap-3 sm:grid-cols-[minmax(12rem,1fr)_12rem_12rem_auto]">
            <?php if ($previewMode): ?><input type="hidden" name="preview" value="1"><?php endif; ?>
            <label class="sr-only" for="user-search">Search accounts</label>
            <input id="user-search" type="search" name="q" value="<?= $escape($search) ?>" placeholder="Search name or email" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            <label class="sr-only" for="role-filter">Filter by role</label>
            <select id="role-filter" name="role" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                <option value="">All roles</option>
                <?php foreach ($roleLabels as $role => $label): ?>
                    <option value="<?= $escape($role) ?>" <?= $roleFilter === $role ? 'selected' : '' ?>><?= $escape($label) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="sr-only" for="status-filter">Filter by status</label>
            <select id="status-filter" name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                <?php foreach (['all' => 'All statuses', 'active' => 'Active', 'inactive' => 'Inactive'] as $status => $label): ?>
                    <option value="<?= $escape($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= $escape($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Apply filters</button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 font-semibold">Account</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Role</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Joined</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (!$accounts): ?>
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No accounts match these filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($accounts as $account): ?>
                        <?php $protectedAccount = $account['role'] === 'head_admin' || (int) $account['id'] === (int) $headAdmin['id']; ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900"><?= $escape($account['name']) ?></p>
                                <p class="mt-0.5 text-slate-500"><?= $escape($account['email']) ?></p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-700"><?= $escape($roleLabels[$account['role']] ?? $account['role']) ?></td>
                            <td class="whitespace-nowrap px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $account['status'] === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>"><?= $escape(ucfirst($account['status'])) ?></span></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-500"><?= $escape(date('M j, Y', strtotime($account['created_at']))) ?></td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <?php if ($protectedAccount): ?>
                                    <span class="text-xs font-medium text-slate-400">Protected</span>
                                <?php else: ?>
                                    <div class="flex items-center gap-3">
                                        <a href="<?= $escape(headAdminUrl('users.php', ['edit' => (int) $account['id']])) ?>" class="font-semibold text-blue-700 hover:underline">Edit</a>
                                        <a href="<?= $escape(headAdminUrl('users.php', ['reset_password' => (int) $account['id']])) ?>" class="font-semibold text-amber-700 hover:underline">Reset password</a>
                                        <form method="post" class="confirm-action" data-confirm="<?= $account['status'] === 'active' ? 'Deactivate this account? The user will no longer be able to sign in.' : 'Reactivate this account?' ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                            <button type="submit" class="font-semibold <?= $account['status'] === 'active' ? 'text-rose-700 hover:text-rose-900' : 'text-emerald-700 hover:text-emerald-900' ?>"><?= $account['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pageCount > 1): ?>
        <nav class="flex items-center justify-between border-t border-slate-100 px-5 py-4 sm:px-6" aria-label="User directory pages">
            <span class="text-sm text-slate-500">Page <?= $pageNumber ?> of <?= $pageCount ?></span>
            <div class="flex gap-2">
                <?php if ($pageNumber > 1): ?>
                    <a href="<?= $escape(headAdminUrl('users.php', ['q' => $search, 'role' => $roleFilter, 'status' => $statusFilter, 'page' => $pageNumber - 1])) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Previous</a>
                <?php endif; ?>
                <?php if ($pageNumber < $pageCount): ?>
                    <a href="<?= $escape(headAdminUrl('users.php', ['q' => $search, 'role' => $roleFilter, 'status' => $statusFilter, 'page' => $pageNumber + 1])) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Next</a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_layout_end.php'; ?>

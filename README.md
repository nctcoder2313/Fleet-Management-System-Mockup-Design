# Fleet Management System

## RBAC setup (PHP and MySQL/MariaDB)

The RBAC layer uses database-backed roles and PHP sessions. It does not trust a role selected in the browser as the user's authority.

1. Create/import the schema in `database/migrations/20261103_rbac.sql` using phpMyAdmin or the MySQL command line. The migration creates the database `fleet_management` and the `users`, `role_requests`, and `audit_logs` tables. It is intended for a new database; reconcile it with an existing user schema before applying it.
2. Configure the database connection. The defaults in `config/database.php` are for a local XAMPP install (`127.0.0.1`, database `fleet_management`, user `root`, blank password). Set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in the PHP environment for other configurations.
3. Create the first `head_admin` account from a local terminal using the one-time CLI bootstrap. It reads a password from the process environment and stores only a PHP password hash; never ship a default account:

   ```powershell
   $env:HEAD_ADMIN_NAME = "Your Name"
   $env:HEAD_ADMIN_EMAIL = "you@example.com"
   $env:HEAD_ADMIN_PASSWORD = Read-Host "Choose a password (12+ characters)"
   C:\xampp\php\php.exe scripts\create_head_admin.php
   Remove-Item Env:\HEAD_ADMIN_NAME, Env:\HEAD_ADMIN_EMAIL, Env:\HEAD_ADMIN_PASSWORD
   ```

   The command refuses to create another Head Admin when one already exists. There is no public Head Admin self-registration.
4. Ensure the project URL prefix in `dashboardPathForRole()` in `includes/rbac.php` matches the folder name under your web root. The current paths assume the project is served from `/Fleet-Management-System-Mockup-Design`.

### Connect the login handler

The included `login.php` authenticates active users by email/password, calls `completeLogin()`, and redirects to the server-determined dashboard. It treats a different selected role as an access request, not authority. If integrating a different login UI, call `completeLogin()` only after password verification:

```php
$login = completeLogin(
    $pdo,
    (int) $user['id'],
    isset($_POST['role']) && is_string($_POST['role']) ? $_POST['role'] : null
);
header('Location: ' . $login['dashboard']);
exit;
```

The user lookup must also require `status = 'active'`. `completeLogin()` reloads the assigned role from the database, regenerates the session ID, and treats a different submitted role only as an access request; it does not change the user's authority. The dashboard can display the resulting session flash message. Do not set `$_SESSION['role']` from the form.

### Access control and elevation behavior

- Each protected dashboard loads `config/database.php` and `includes/rbac.php`, starts the secure session, and calls `requireRole($pdo, '<required-role>')` before rendering.
- The Head Admin dashboard is restricted to system oversight: account-role counts, pending access approvals, and recent audit events. It does not show fleet operations, tracking, maintenance, fuel, or accounting widgets.
- The sample fleet page is restricted to the `manager` role. The Admin dashboard is restricted to `admin`. Add equivalent role guards to every new role-specific page and API endpoint.
- An authenticated user who requests a different protected role gets HTTP 403 and a `PENDING` request (duplicate pending requests for the same role are reused). The response says: “Access request sent to Head Admin for approval.”
- Head Admins cannot request an elevation, and Head Admin is not an elevation target. A grant temporarily replaces—not adds to—the user's base role. The default grant is eight hours; `ROLE_ELEVATION_HOURS` can be set to an integer from 1 to 24.
- Head Admin approval and denial are POST-only, CSRF-protected, disallow self-approval, and write to `audit_logs`. The dashboard provides the review forms.
- The existing `dashboard/head_admin/request_access.php` endpoint can be used by a login or role-selection flow to create a request for an authenticated user. It also requires a CSRF token.

The current role set is `head_admin`, `admin`, `manager`, `user`, and `accounting`. The Manager, User, and Accounting dashboards and a login handler still need to be created and guarded before those roles can use the full application.

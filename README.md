# Fleet Management System

## RBAC setup (PHP and MySQL/MariaDB)

The RBAC layer uses database-backed roles and PHP sessions. It does not trust a role selected in the browser as the user's authority.

1. Create/import the schema in `database/migrations/20261103_rbac.sql` using phpMyAdmin or the MySQL command line. The migration creates the database `fleet_management` and the `users`, `role_requests`, and `audit_logs` tables. It is intended for a new database; reconcile it with an existing user schema before applying it. If the original RBAC migration was already applied, apply `database/migrations/20261104_add_driver_role.sql` to add the Driver role to existing installations.
2. Apply `database/migrations/20261104_fleet_operations.sql` to create the database-backed vehicle and trip records used by the Head Admin fleet workspace. It does not insert sample vehicles or trips into company data. Keep an existing fleet database backed up and reconcile table names before applying if it already has operational vehicle or trip tables.
3. Apply `database/migrations/20261105_vehicle_documents.sql` after the fleet operations migration to create persistent vehicle document and compliance records for the Admin dashboard.
4. Apply `database/migrations/20261105_maintenance_logs.sql` after the fleet operations migration to enable persistent Admin maintenance schedules and service logs.
5. Apply `database/migrations/20261105_geofence_rules.sql` after the fleet operations migration to create Admin geofence and speed-limit rules.
6. Apply `database/migrations/20261105_vendors_equipment.sql` after the fleet operations migration for accredited vendors and vehicle equipment records.
7. Apply `database/migrations/20261106_account_signup_requests.sql` to enable Ainie's public account-request flow and Head Admin approvals.
8. Configure the database connection. The defaults in `config/database.php` are for a local XAMPP install (`127.0.0.1`, database `fleet_management`, user `root`, blank password). Set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in the PHP environment for other configurations.
9. Create the first `head_admin` account from a local terminal using the one-time CLI bootstrap. It reads a password from the process environment and stores only a PHP password hash; never ship a default account:

   ```powershell
   $env:HEAD_ADMIN_NAME = "Your Name"
   $env:HEAD_ADMIN_EMAIL = "you@example.com"
   $env:HEAD_ADMIN_PASSWORD = Read-Host "Choose a password (12+ characters)"
   C:\xampp\php\php.exe scripts\create_head_admin.php
   Remove-Item Env:\HEAD_ADMIN_NAME, Env:\HEAD_ADMIN_EMAIL, Env:\HEAD_ADMIN_PASSWORD
   ```

   The command refuses to create another Head Admin when one already exists. There is no public Head Admin self-registration.
10. Ensure the project URL prefix in `dashboardPathForRole()` in `includes/rbac.php` matches the folder name under your web root. The current paths assume the project is served from `/Fleet-Management-System-Mockup-Design`.

### Accounting dashboard

- Assign the `accounting` role from the Head Admin Users page. Signing in with that account opens `dashboard/accounting/index.php`; the dashboard and its API require the same MySQL-backed session and Accounting role.
- The imported accounting workspace currently keeps its sample finance records in a separate local SQLite database at `dashboard/accounting/fleet.sqlite`. It is not connected to the operational MySQL fleet records. Do not treat its sample vehicles or transactions as live company data.
- The main login and account-request pages use the Paragon branding assets from Ainie's `origin/ainie` branch while keeping this project's MySQL authentication, active-account checks, CSRF validation, role routing, and Head Admin account approvals.
- Public account requests create pending records only; they do not activate users. Head Admin reviews requests from **Account requests**. The requested role is limited to Admin, Manager, User, Accounting, or Driver; Head Admin accounts are never self-requested.
- The SQLite database is initialized on first Accounting dashboard access. XAMPP PHP must have `pdo_sqlite` enabled, and Apache must be able to write to `dashboard/accounting`. The module's `.htaccess` blocks direct downloads of its database and SQL files.
- Accounting API writes require the shared application's CSRF token. Use the Accounting dashboard page rather than calling `api.php` directly.

### Connect the login handler

The included Ainie-branded `login.php` authenticates active users by email/password, optionally sends a different selected role as an access request (never as authority), and redirects to the server-determined dashboard. New users submit a separate account request; Head Admin approval creates their account. If integrating a different login UI, call `completeLogin()` only after password verification:

```php
$login = completeLogin(
    $pdo,
    (int) $user['id'],
    isset($_POST['role']) && is_string($_POST['role']) ? $_POST['role'] : null
);
header('Location: ' . $login['dashboard']);
exit;
```

The user lookup must also require `status = 'active'`. `completeLogin()` reloads the assigned role from the database and regenerates the session ID. Its optional selected-role argument creates an access request only; it does not change the user's authority. Do not set `$_SESSION['role']` from a form.

### Access control and elevation behavior

- Each protected dashboard loads `config/database.php` and `includes/rbac.php`, starts the secure session, and calls `requireRole($pdo, '<required-role>')` before rendering.
- The Head Admin dashboard is restricted to system oversight. Its overview links to separate user management, access approval, and audit history pages; it does not show fleet operations, tracking, maintenance, fuel, or accounting widgets.
- Head Admin user management can create and edit non-Head-Admin accounts for Admin, Manager, User, Accounting, and Driver roles; reset passwords; and deactivate or reactivate access. Normal account changes are stored in the MySQL `users` table and persist across sessions and browser restarts. Passwords are stored as secure hashes and never included in audit details. Deactivation is reversible and preserves account references in approval and audit history; Head Admin accounts cannot be changed through this directory.
- Adding `?preview=1` to a Head Admin page opens an interactive sample-data preview. Its account and approval changes are session-only and never write to the company database.
- The separate Head Admin Fleet Operations workspace links each vehicle summary to a status-filtered vehicle directory. Vehicle profiles show the reported location, condition, fuel level, active trip, and recent trip history.
- The Admin Document Compliance page is a separate, role-protected workspace for managing LTO, LTFRB, and insurance records. Its expiry status is evaluated from the expiry date so badges and filters stay current as dates pass.
- The Admin Maintenance & Service Logs page is a separate, role-protected workspace for scheduling and tracking vehicle maintenance without financial or cost fields.
- The Admin Vendors & Equipment page is a separate, role-protected workspace with tabs for accredited repair shops and installed vehicle equipment.
- The Admin dashboard, Maintenance, Geofencing & Speed Setup, and Vendors & Equipment pages support an explicit `?preview=1` local preview for loopback connections only. Preview data is stored in the PHP session and does not read or modify company records. Normal requests remain protected by the Admin role check.
- Emergency trip overrides are available from the active-trip review page. Force-complete and cancel actions require a reason, lock and update the trip and vehicle in one database transaction, and write an audit event. Closed trips cannot be overridden again. Vehicles already under maintenance remain under maintenance after trip recovery.
- Fleet preview data includes sample vehicles and active trips; preview overrides modify only the browser's session sample data. The fleet migration deliberately does not seed demonstration vehicles into a company's live database.
- The sample fleet page is restricted to the `manager` role. The Admin dashboard is restricted to `admin`. Add equivalent role guards to every new role-specific page and API endpoint.
- An authenticated user who requests a different protected role gets HTTP 403 and a `PENDING` request (duplicate pending requests for the same role are reused). The response says: “Access request sent to Head Admin for approval.”
- Head Admins cannot request an elevation, and Head Admin is not an elevation target. A grant temporarily replaces—not adds to—the user's base role. The default grant is eight hours; `ROLE_ELEVATION_HOURS` can be set to an integer from 1 to 24.
- Head Admin approval and denial are POST-only, CSRF-protected, disallow self-approval, and write to `audit_logs`. The dashboard provides the review forms.
- The existing `dashboard/head_admin/request_access.php` endpoint can be used by a login or role-selection flow to create a request for an authenticated user. It also requires a CSRF token.

The current role set is `head_admin`, `admin`, `manager`, `user`, `accounting`, and `driver`. Driver accounts use a dedicated role-guarded workspace; trip assignment and driver mobile workflows are separate follow-up work.

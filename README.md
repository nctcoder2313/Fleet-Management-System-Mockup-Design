# Fleetwise — Fleet Management System

A fleet management sign-in page built with PHP, Tailwind CSS, JavaScript, and MySQL.

## Run locally

Requirements: PHP 8.1+ with PDO MySQL enabled, and MySQL 8+.

1. Start MySQL in XAMPP and run `database/schema.sql` in phpMyAdmin or the MySQL client. This creates the `fleetwise` database, user table, and access-request table.
2. The app connects through `database/connection.php`. For a standard local XAMPP install it defaults to `localhost`, database `fleetwise`, user `root`, and an empty password. For other setups, configure `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`.
3. Ensure PHP has the PDO MySQL extension enabled.
4. From the project directory, start PHP's local server:

   ```sh
   php -S localhost:8000
   ```

5. Open `http://localhost:8000`.

The login accepts accounts in `users` whose `password_hash` was created with PHP's `password_hash()` function. Supported roles are `accounting`, `manager`, `admin`, and `head_admin`; a successful login stores the user ID, name, and role in the PHP session. The request-access form saves requests as `pending` in `access_requests`, including a hashed password for use if the request is approved. It does not create user accounts or role-specific dashboards; those can be added on top of this database and session foundation.

For an existing database created before password creation was added to the request form, run `database/migrate-add-request-password.sql` once in phpMyAdmin. Fresh databases get the column directly from `database/schema.sql`.

The schema seeds a sample Accounting account: `ainiemac@gmail.com` with password `1234`. Re-run `database/schema.sql` in phpMyAdmin to add it to an existing database; `INSERT IGNORE` leaves any existing account with that email unchanged.

To add a user manually, generate a hash with `password_hash('your-password', PASSWORD_DEFAULT)` in PHP and insert the user's name, email, hash, and role into `users`.

# Fleetwise — Fleet Management System

A fleet management sign-in page built with PHP, Tailwind CSS, JavaScript, and MySQL.

## Run locally

Requirements: PHP 8.1+ with PDO MySQL enabled, and MySQL 8+.

1. Create the database and `users` table by running `database/schema.sql`.
2. Set the database connection environment variables: `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`.
3. Copy the Paragon fleet photo to `assets/paragon-fleet.jpg`. The existing fleet illustration is used as a fallback until the photo is present.
4. From the project directory, start PHP's local server:

   ```sh
   php -S localhost:8000
   ```

5. Open `http://localhost:8000`.

The login accepts accounts whose `password_hash` was created with PHP's `password_hash()` function. Supported roles are `accounting`, `manager`, `admin`, and `head_admin`. Account creation and role-specific dashboards are not included.

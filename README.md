# Fleet Management System Mockup

A PHP-rendered manager dashboard for monitoring fleet status, active trips,
driver assignments, maintenance schedules, and operational performance. The
interface uses Tailwind CSS, vanilla JavaScript, and SQLite.

## Requirements

- PHP 8.1+ with the PDO SQLite extension enabled
- Internet access to load the Tailwind Play CDN

## Run locally

From the project directory, start PHP's built-in development server:

```sh
php -S localhost:8000
```

Open [http://localhost:8000](http://localhost:8000). On first run, the app
creates `fleet.sqlite` and loads the sample data from `schema.sql`.

## Stack and interactions

- Use the sidebar to switch between Overview, Fleet Status, Trips & Assignments,
  Drivers, Maintenance, Reports, and Settings workspaces.
- Overview is read-only. Search and edit vehicle, trip, driver duty, and
  maintenance records in their respective workspaces; changes are saved to SQLite.
- Review monthly utilization and fuel trends by Monday–Sunday week, alongside
  weekly trip-status distribution and maintenance due by department. Reporting uses UTC.
- On Overview, use the date selector to filter weekly performance charts and
  recent activity by a custom range, Today, Last 7 days, or This month. Missing
  daily metrics are clearly identified as sample data; current KPI cards remain
  fleet snapshots.
- Track reported issues in the Maintenance workspace. New reports begin in Awaiting Review, and managers can move work through In Progress or Delayed to Completed; issue records remain in SQLite throughout the workflow.
- The Maintenance workspace shows clearly labeled mock-up issue examples based on the existing sample vehicle maintenance schedule. Reports entered through the form are saved as user-submitted mock-up data.

## Database update

When an existing `fleet.sqlite` is first opened after this update, `database.php`
applies compatible, data-preserving SQLite migrations automatically. Back up
`fleet.sqlite` before deploying the update. The migration makes vehicle
`fuel_level` nullable, adds its update timestamp, and adds a `trip_date` field.
Existing non-null fuel values are retained; their update time remains unknown
until the next save. Existing active trips are dated to the migration day
because they are known to be in progress; completed and upcoming legacy trips
remain undated rather than being assigned fabricated dates. Assign their actual
trip dates in Trips & Assignments to include them in weekly reports.

Blank fuel levels save as SQL `NULL` and display as “Not reported.” Fuel
consumption is calculated separately by summing reported daily liters for the
week. Weekly utilization averages the available daily records, and trip
completion is completed trips divided by dated started trips; upcoming trips
are excluded from that rate. The daily metric records remain unchanged.

The manager workspace contains operational information only. It does not expose
accounting totals, payroll or employee records, head-admin user management, or
system configuration. The role and sample data are for a mock-up; the project
does not implement user authentication or connect to live fleet data.

Typography uses the system sans-serif stack. Tailwind's Play CDN is intended
for mockups and development, not production deployment.

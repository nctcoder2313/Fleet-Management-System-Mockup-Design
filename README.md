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

- Filter the fleet table using the summary cards and status tabs.
- Search database-backed vehicles and trips from the top bar (or press Ctrl/Cmd+K).
- Review the fleet status map, utilization and trip-completion charts, fuel trend,
  maintenance issues by department, and recent activity.
- Open vehicle, trip, and alert details.
- Submit maintenance requests and issue reports; both are saved to SQLite.

The manager workspace contains operational information only. It does not expose
accounting totals, payroll or employee records, head-admin user management, or
system configuration. The role and sample data are for a mock-up; the project
does not implement user authentication or connect to live fleet data.

Typography uses the system sans-serif stack. Tailwind's Play CDN is intended
for mockups and development, not production deployment.

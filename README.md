# Fleet Accounting Dashboard

A PHP-rendered accounting workspace for reviewing fleet expenses, unpaid bills,
and driver cash advances and liquidations. The interface uses Tailwind CSS,
vanilla JavaScript, and SQLite.

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

- Use Overview for financial summary cards, expense trend, category spend, recent expenses, and open driver advances.
- Use Expenses to filter and review the full ledger, record expenses, and export CSV reports.
- Use Driver Advances to record advances, filter the full register, and save partial or complete liquidations with receipt references.
- Use Reports to review period totals, monthly trends, and expense categories.

The dashboard uses local sample data, including 30 expense records and 18
driver advances. The project does not implement user authentication, payroll,
head-admin user management, or connect to live fleet data. Accounting records
are stored in the local SQLite database. The API only accepts accounting write
actions in this workspace.

Typography uses the system sans-serif stack. Tailwind's Play CDN is intended
for mockups and development, not production deployment.

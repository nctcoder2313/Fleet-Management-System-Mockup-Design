# Fleet Management Dashboard

A PHP-rendered fleet finance dashboard for reviewing expenses, unpaid bills,
and driver cash advances and liquidations. Amounts are shown in Philippine
pesos (PHP), and dashboard dates use the Asia/Manila timezone. The interface
uses Tailwind CSS, vanilla JavaScript, and SQLite.

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
- Use Expenses to search and filter the full ledger, record or edit expenses, attach receipts, and export CSV reports.
- Use Bills & Payments to manage supplier invoices, record partial or full payments, and review payment history. Each bill is linked to one expense; bill payments only reduce its balance.
- Use Driver Advances to record advances, submit receipt-backed liquidations, review submissions, and track remaining balances.
- Use Reports to filter expense summaries by period, vehicle, and category, then export CSV for Excel or print/save as PDF.

The dashboard uses local sample data and stores fleet finance records in the
local SQLite database. Attachments are limited to PDF, JPEG, PNG, or WebP files
up to 5 MB. PDF output uses the browser's print dialog. The project does not
implement user authentication, payroll, head-admin user management, or connect
to live fleet data.

Typography uses the system sans-serif stack. Tailwind's Play CDN is intended
for mockups and development, not production deployment.

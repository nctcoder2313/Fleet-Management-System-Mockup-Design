<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

function csvSafe($value): string
{
    $value = (string) $value;
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}

$db = require __DIR__ . '/database.php';

$attachmentType = $_GET['attachment'] ?? '';
if ($attachmentType !== '') {
    $attachmentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$attachmentId || !in_array($attachmentType, ['expense', 'bill', 'liquidation'], true)) {
        http_response_code(404);
        exit('Attachment not found.');
    }
    $attachmentQuery = $attachmentType === 'expense'
        ? $db->prepare('SELECT file_name, mime_type, file_data FROM expense_receipts WHERE expense_id = :id')
        : ($attachmentType === 'bill'
            ? $db->prepare('SELECT invoice_name AS file_name, invoice_mime AS mime_type, invoice_data AS file_data FROM bills WHERE id = :id AND invoice_data IS NOT NULL')
            : $db->prepare('SELECT receipt_name AS file_name, receipt_mime AS mime_type, receipt_data AS file_data FROM advance_liquidations WHERE id = :id AND receipt_data IS NOT NULL'));
    $attachmentQuery->execute(['id' => $attachmentId]);
    $attachment = $attachmentQuery->fetch(PDO::FETCH_ASSOC);
    if (!$attachment) {
        http_response_code(404);
        exit('Attachment not found.');
    }
    header('Content-Type: ' . $attachment['mime_type']);
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', $attachment['file_name']) . '"');
    header('Content-Length: ' . strlen($attachment['file_data']));
    echo $attachment['file_data'];
    exit;
}

$requestedView = $_GET['view'] ?? 'home';
$validViews = ['home', 'expenses', 'bills', 'advances', 'financial-reports'];
$activeView = is_string($requestedView) && in_array($requestedView, $validViews, true)
    ? $requestedView
    : 'home';
$views = [
    'home' => ['title' => 'Overview', 'description' => 'Key fleet expenses, bills, and driver advances at a glance.'],
    'expenses' => ['title' => 'Expenses', 'description' => 'Review, record, edit, and export fleet expense transactions.'],
    'bills' => ['title' => 'Bills & Payments', 'description' => 'Track supplier bills, due dates, payments, and remaining balances.'],
    'advances' => ['title' => 'Driver advances', 'description' => 'Track cash issued to drivers and reconcile receipts.'],
    'financial-reports' => ['title' => 'Reports', 'description' => 'Compare fleet spend by month and expense category.'],
];
$categories = ['Fuel', 'Maintenance', 'Repairs', 'Parts', 'Registration', 'Inspection', 'Insurance', 'Tolls', 'Parking', 'Cleaning', 'Permits', 'Fines', 'Other'];
$categoryColors = ['Fuel' => '#2563eb', 'Maintenance' => '#16a34a', 'Repairs' => '#dc2626', 'Parts' => '#f59e0b', 'Registration' => '#7c3aed', 'Inspection' => '#0891b2', 'Insurance' => '#64748b', 'Tolls' => '#0f766e', 'Parking' => '#9333ea', 'Tolls & Parking' => '#0f766e', 'Cleaning' => '#65a30d', 'Permits' => '#c2410c', 'Fines' => '#be123c', 'Other' => '#94a3b8'];
$accountingVehicles = $db->query('SELECT vehicle_code, make, driver_name FROM vehicles ORDER BY vehicle_code')->fetchAll(PDO::FETCH_ASSOC);
$accountingDrivers = $db->query('SELECT name FROM drivers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$defaultPeriod = in_array($activeView, ['financial-reports', 'bills'], true) ? 'year' : 'month';
$period = in_array($_GET['period'] ?? $defaultPeriod, ['month', 'last-month', 'year', 'custom'], true) ? $_GET['period'] ?? $defaultPeriod : $defaultPeriod;
$today = date('Y-m-d');
$currentYear = (int) date('Y');
$yearOptions = range($currentYear - 5, $currentYear);
$selectedYear = filter_var($_GET['year'] ?? $currentYear, FILTER_VALIDATE_INT);
if ($selectedYear === false || !in_array($selectedYear, $yearOptions, true)) {
    $selectedYear = $currentYear;
}
if ($period === 'year') {
    $periodStart = sprintf('%04d-01-01', $selectedYear);
    $periodEnd = min(sprintf('%04d-12-31', $selectedYear), $selectedYear === $currentYear ? $today : sprintf('%04d-12-31', $selectedYear));
} elseif ($period === 'last-month') {
    $periodStart = date('Y-m-01', strtotime('first day of last month'));
    $periodEnd = date('Y-m-t', strtotime('first day of last month'));
} else {
    $periodStart = date('Y-m-01');
    $periodEnd = $today;
}
if ($period === 'custom') {
    $requestedStart = is_string($_GET['from'] ?? null) ? $_GET['from'] : '';
    $requestedEnd = is_string($_GET['to'] ?? null) ? $_GET['to'] : '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $requestedStart) && strtotime($requestedStart) !== false) {
        $periodStart = $requestedStart;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $requestedEnd) && strtotime($requestedEnd) !== false) {
        $periodEnd = min($requestedEnd, $today);
    }
    if ($periodStart > $periodEnd) {
        $periodStart = date('Y-m-01');
        $periodEnd = $today;
        $period = 'month';
    }
}
$selectedVehicle = is_string($_GET['vehicle'] ?? null) ? $_GET['vehicle'] : '';
if ($selectedVehicle !== '' && !in_array($selectedVehicle, array_column($accountingVehicles, 'vehicle_code'), true)) {
    $selectedVehicle = '';
}
$selectedCategory = is_string($_GET['category'] ?? null) && in_array($_GET['category'], $categories, true) ? $_GET['category'] : '';
$selectedExpenseGroup = is_string($_GET['expense_group'] ?? null) && in_array($_GET['expense_group'], ['maintenance'], true) ? $_GET['expense_group'] : '';
$selectedExpenseStatus = is_string($_GET['expense_status'] ?? null) && in_array($_GET['expense_status'], ['Paid', 'Pending'], true) ? $_GET['expense_status'] : '';
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$selectedBillStatus = is_string($_GET['bill_status'] ?? null) && in_array($_GET['bill_status'], ['Pending', 'Paid', 'Overdue', 'Partially Paid'], true) ? $_GET['bill_status'] : '';
$selectedDriver = is_string($_GET['driver'] ?? null) && in_array($_GET['driver'], array_column($accountingDrivers, 'name'), true) ? $_GET['driver'] : '';
$selectedAdvanceStatus = is_string($_GET['advance_status'] ?? null) && in_array($_GET['advance_status'], ['Pending Liquidation', 'Submitted for Review', 'Approved', 'Rejected', 'Fully Liquidated'], true) ? $_GET['advance_status'] : '';

$expenseWhere = 'WHERE expenses.expense_date BETWEEN :start_date AND :end_date';
$expenseParams = ['start_date' => $periodStart, 'end_date' => $periodEnd];
if ($selectedVehicle !== '') {
    $expenseWhere .= ' AND expenses.vehicle_code = :vehicle_code';
    $expenseParams['vehicle_code'] = $selectedVehicle;
}
if ($selectedCategory !== '') {
    $expenseWhere .= ' AND expenses.category = :category';
    $expenseParams['category'] = $selectedCategory;
}
if ($selectedExpenseGroup === 'maintenance') {
    $expenseWhere .= " AND expenses.category IN ('Maintenance', 'Repairs', 'Parts', 'Inspection')";
}
if ($selectedExpenseStatus !== '') {
    $expenseWhere .= ' AND expenses.status = :expense_status';
    $expenseParams['expense_status'] = $selectedExpenseStatus;
}
if ($search !== '' && in_array($activeView, ['expenses', 'bills'], true)) {
    $escapedSearch = '%' . $search . '%';
    $expenseWhere .= ' AND (expenses.description LIKE :expense_description OR expenses.vehicle_code LIKE :expense_vehicle OR CAST(expenses.id AS TEXT) LIKE :expense_id OR expenses.recorded_by LIKE :expense_recorder)';
    $expenseParams += [
        'expense_description' => $escapedSearch,
        'expense_vehicle' => $escapedSearch,
        'expense_id' => $escapedSearch,
        'expense_recorder' => $escapedSearch,
    ];
}
$summaryQuery = $db->prepare(
    'SELECT COALESCE(SUM(amount_cents), 0) AS total_cents,
            COALESCE(SUM(CASE WHEN category = \'Fuel\' THEN amount_cents ELSE 0 END), 0) AS fuel_cents,
            COALESCE(SUM(CASE WHEN category IN (\'Maintenance\', \'Repairs\', \'Parts\', \'Inspection\') THEN amount_cents ELSE 0 END), 0) AS maintenance_cents,
            COALESCE(SUM(CASE WHEN status = \'Pending\' THEN amount_cents ELSE 0 END), 0) AS pending_cents,
            COALESCE(SUM(CASE WHEN status = \'Paid\' AND source_bill_id IS NULL THEN amount_cents ELSE 0 END), 0) AS paid_expense_cents,
            SUM(CASE WHEN status = \'Pending\' THEN 1 ELSE 0 END) AS pending_count
     FROM expenses ' . $expenseWhere
);
$summaryQuery->execute($expenseParams);
$expenseSummary = $summaryQuery->fetch(PDO::FETCH_ASSOC);
$expenseSql = 'SELECT expenses.id, expenses.expense_date, expenses.vehicle_code, vehicles.make,
    expenses.driver_name, expenses.recorded_by, expenses.source_bill_id,
        expenses.category, expenses.description, expenses.amount_cents, expenses.status,
        EXISTS(SELECT 1 FROM expense_receipts WHERE expense_receipts.expense_id = expenses.id) AS has_receipt
    FROM expenses JOIN vehicles ON vehicles.vehicle_code = expenses.vehicle_code ' . $expenseWhere .
    ' ORDER BY expenses.expense_date DESC, expenses.id DESC';
$expenseLimit = $activeView === 'home' ? ' LIMIT 8' : '';
$expenseQuery = $db->prepare($expenseSql . (($_GET['export'] ?? '') === 'csv' ? '' : $expenseLimit));
$expenseQuery->execute($expenseParams);
$expenses = $expenseQuery->fetchAll(PDO::FETCH_ASSOC);
$recentExpenses = array_slice($expenses, 0, 6);
$categoryQuery = $db->prepare(
    'SELECT CASE WHEN category IN (\'Tolls\', \'Parking\') THEN \'Tolls & Parking\' ELSE category END AS category,
            SUM(amount_cents) AS total_cents
     FROM expenses ' . $expenseWhere . ' GROUP BY CASE WHEN category IN (\'Tolls\', \'Parking\') THEN \'Tolls & Parking\' ELSE category END ORDER BY total_cents DESC'
);
$categoryQuery->execute($expenseParams);
$expenseCategories = $categoryQuery->fetchAll(PDO::FETCH_ASSOC);
$billWhere = 'WHERE bills.bill_date BETWEEN :bill_start AND :bill_end';
$billParams = ['bill_start' => $periodStart, 'bill_end' => $periodEnd];
if ($selectedVehicle !== '') {
    $billWhere .= ' AND bills.vehicle_code = :bill_vehicle';
    $billParams['bill_vehicle'] = $selectedVehicle;
}
if ($selectedCategory !== '') {
    $billWhere .= ' AND bills.category = :bill_category';
    $billParams['bill_category'] = $selectedCategory;
}
if ($search !== '') {
    $escapedBillSearch = '%' . $search . '%';
    $billWhere .= ' AND (bills.supplier LIKE :bill_supplier OR bills.description LIKE :bill_description OR bills.vehicle_code LIKE :bill_search_vehicle OR CAST(bills.id AS TEXT) LIKE :bill_id)';
    $billParams += [
        'bill_supplier' => $escapedBillSearch,
        'bill_description' => $escapedBillSearch,
        'bill_search_vehicle' => $escapedBillSearch,
        'bill_id' => $escapedBillSearch,
    ];
}
if ($selectedBillStatus !== '') {
    $billQueryStatus = $selectedBillStatus;
}
$billQuery = $db->prepare(
    'SELECT bills.id, bills.supplier, bills.description, bills.category, bills.vehicle_code, vehicles.make,
            bills.bill_date, bills.due_date, bills.amount_cents, bills.expense_id,
            bills.invoice_name, bills.invoice_mime,
            COALESCE(SUM(bill_payments.amount_cents), 0) AS paid_cents,
            bills.amount_cents - COALESCE(SUM(bill_payments.amount_cents), 0) AS balance_cents,
            CASE
                WHEN COALESCE(SUM(bill_payments.amount_cents), 0) >= bills.amount_cents THEN \'Paid\'
                WHEN bills.due_date < :today_overdue THEN \'Overdue\'
                WHEN COALESCE(SUM(bill_payments.amount_cents), 0) > 0 THEN \'Partially Paid\'
                ELSE \'Pending\'
            END AS payment_status
     FROM bills
     JOIN vehicles ON vehicles.vehicle_code = bills.vehicle_code
     LEFT JOIN bill_payments ON bill_payments.bill_id = bills.id
     ' . $billWhere . '
     GROUP BY bills.id
     ' . (!empty($billQueryStatus) ? 'HAVING payment_status = :bill_status' : '') . '
     ORDER BY bills.due_date ASC, bills.id DESC'
);
$billQuery->execute($billParams + ['today_overdue' => $today] + (!empty($billQueryStatus) ? ['bill_status' => $billQueryStatus] : []));
$billQueryStatus = null;
$bills = $billQuery->fetchAll(PDO::FETCH_ASSOC);
$billPaymentsById = [];
if ($bills !== []) {
    $billIds = array_column($bills, 'id');
    $paymentHistoryQuery = $db->prepare(
        'SELECT bill_id, payment_date, amount_cents, reference, notes
         FROM bill_payments WHERE bill_id IN (' . implode(',', array_fill(0, count($billIds), '?')) . ')
         ORDER BY bill_id, payment_date DESC, id DESC'
    );
    $paymentHistoryQuery->execute($billIds);
    foreach ($billIds as $billId) {
        $billPaymentsById[$billId] = [];
    }
    foreach ($paymentHistoryQuery->fetchAll(PDO::FETCH_ASSOC) as $payment) {
        $billPaymentsById[$payment['bill_id']][] = $payment;
    }
}
$billSummaryQuery = $db->prepare(
    'SELECT COALESCE(SUM(bills.amount_cents - COALESCE(payments.paid_cents, 0)), 0) AS outstanding_cents,
            COALESCE(SUM(CASE WHEN bills.due_date < :today
                THEN bills.amount_cents - COALESCE(payments.paid_cents, 0) ELSE 0 END), 0) AS overdue_cents,
            COALESCE(SUM(COALESCE(payments.paid_cents, 0)), 0) AS paid_cents
     FROM bills
     LEFT JOIN (SELECT bill_id, SUM(amount_cents) AS paid_cents FROM bill_payments GROUP BY bill_id) payments
        ON payments.bill_id = bills.id
     ' . $billWhere
);
$billSummaryQuery->execute($billParams + ['today' => $today]);
$billSummary = $billSummaryQuery->fetch(PDO::FETCH_ASSOC);
$paymentWhere = 'WHERE bill_payments.payment_date BETWEEN :payment_start AND :payment_end';
$paymentParams = ['payment_start' => $periodStart, 'payment_end' => $periodEnd];
if ($selectedVehicle !== '') {
    $paymentWhere .= ' AND bills.vehicle_code = :payment_vehicle';
    $paymentParams['payment_vehicle'] = $selectedVehicle;
}
if ($selectedCategory !== '') {
    $paymentWhere .= ' AND bills.category = :payment_category';
    $paymentParams['payment_category'] = $selectedCategory;
}
$paymentQuery = $db->prepare(
    'SELECT COALESCE(SUM(bill_payments.amount_cents), 0)
     FROM bill_payments JOIN bills ON bills.id = bill_payments.bill_id ' . $paymentWhere
);
$paymentQuery->execute($paymentParams);
$billPaymentsCents = (int) $paymentQuery->fetchColumn();
$totalPaymentsCents = (int) $expenseSummary['paid_expense_cents'] + $billPaymentsCents;
$legacyOutstandingQuery = $db->prepare(
    'SELECT COALESCE(SUM(amount_cents), 0) FROM expenses ' . $expenseWhere .
    ' AND expenses.status = \'Pending\' AND expenses.source_bill_id IS NULL'
);
$legacyOutstandingQuery->execute($expenseParams);
$outstandingBillsCents = (int) $billSummary['outstanding_cents'] + (int) $legacyOutstandingQuery->fetchColumn();
$trendStart = sprintf('%04d-01-01', $selectedYear);
$trendEnd = $selectedYear === $currentYear ? $today : sprintf('%04d-12-31', $selectedYear);
$trendParams = ['trend_start' => $trendStart, 'trend_end' => $trendEnd];
$trendVehicleFilter = '';
if ($selectedVehicle !== '') {
    $trendVehicleFilter = ' AND vehicle_code = :trend_vehicle';
    $trendParams['trend_vehicle'] = $selectedVehicle;
}
$trendQuery = $db->prepare(
    'SELECT strftime(\'%Y-%m\', expense_date) AS month, SUM(amount_cents) AS total_cents
     FROM expenses WHERE expense_date BETWEEN :trend_start AND :trend_end' . $trendVehicleFilter . ' GROUP BY month ORDER BY month'
);
$trendQuery->execute($trendParams);
$monthlyTotals = array_column($trendQuery->fetchAll(PDO::FETCH_ASSOC), 'total_cents', 'month');
$expenseTrend = [];
$trendMonthCount = $selectedYear === $currentYear ? (int) date('n') : 12;
for ($month = 1; $month <= $trendMonthCount; $month++) {
    $monthKey = sprintf('%04d-%02d', $selectedYear, $month);
    $expenseTrend[] = ['month' => $monthKey, 'total_cents' => (int) ($monthlyTotals[$monthKey] ?? 0)];
}

$advanceStatusSql = "CASE
    WHEN cash_advances.liquidated_cents >= cash_advances.amount_cents THEN 'Fully Liquidated'
    WHEN (SELECT COALESCE(SUM(amount_cents), 0) FROM advance_liquidations
          WHERE advance_id = cash_advances.id AND status = 'Submitted') > 0 THEN 'Submitted for Review'
    WHEN (SELECT status FROM advance_liquidations WHERE advance_id = cash_advances.id
          ORDER BY id DESC LIMIT 1) = 'Rejected' THEN 'Rejected'
    WHEN cash_advances.liquidated_cents > 0 THEN 'Approved'
    ELSE 'Pending Liquidation'
END";
$advanceWhere = 'WHERE cash_advances.advance_date BETWEEN :start_date AND :end_date';
$advanceParams = ['start_date' => $periodStart, 'end_date' => $periodEnd];
if ($selectedVehicle !== '') {
    $advanceWhere .= ' AND cash_advances.vehicle_code = :vehicle_code';
    $advanceParams['vehicle_code'] = $selectedVehicle;
}
if ($selectedDriver !== '') {
    $advanceWhere .= ' AND cash_advances.driver_name = :driver_name';
    $advanceParams['driver_name'] = $selectedDriver;
}
if ($selectedAdvanceStatus !== '') {
    $advanceWhere .= ' AND ' . $advanceStatusSql . ' = :advance_status';
    $advanceParams['advance_status'] = $selectedAdvanceStatus;
}
$advanceQuery = $db->prepare(
    'SELECT cash_advances.id, cash_advances.advance_date, cash_advances.driver_name, cash_advances.vehicle_code,
            cash_advances.purpose, cash_advances.notes, cash_advances.amount_cents, cash_advances.liquidated_cents,
            cash_advances.liquidated_at, cash_advances.receipt_reference, cash_advances.liquidation_notes,
            (SELECT COALESCE(SUM(amount_cents), 0) FROM advance_liquidations
             WHERE advance_id = cash_advances.id AND status = \'Submitted\') AS submitted_cents,
            ' . $advanceStatusSql . ' AS status
     FROM cash_advances ' . $advanceWhere . ' ORDER BY advance_date DESC, id DESC'
);
$advanceQuery->execute($advanceParams);
$cashAdvances = $advanceQuery->fetchAll(PDO::FETCH_ASSOC);
$recentAdvances = array_slice($cashAdvances, 0, 6);
$advanceSummaryQuery = $db->prepare(
    'SELECT COALESCE(SUM(amount_cents), 0) AS issued_cents,
            COALESCE(SUM(liquidated_cents), 0) AS liquidated_cents,
            COALESCE(SUM(amount_cents - liquidated_cents), 0) AS outstanding_cents,
            SUM(CASE WHEN liquidated_cents < amount_cents THEN 1 ELSE 0 END) AS open_count,
            (SELECT COUNT(*) FROM advance_liquidations
             WHERE advance_liquidations.status = \'Submitted\'
               AND advance_liquidations.advance_id IN (
                   SELECT cash_advances.id FROM cash_advances WHERE cash_advances.advance_date BETWEEN :start_date AND :end_date
               )) AS submitted_count
     FROM cash_advances ' . $advanceWhere
);
$advanceSummaryQuery->execute($advanceParams);
$advanceSummary = $advanceSummaryQuery->fetch(PDO::FETCH_ASSOC);
$liquidationWhere = 'WHERE advance_liquidations.liquidated_at BETWEEN :start_date AND :end_date';
$liquidationParams = ['start_date' => $periodStart, 'end_date' => $periodEnd];
if ($selectedVehicle !== '') {
    $liquidationWhere .= ' AND cash_advances.vehicle_code = :vehicle_code';
    $liquidationParams['vehicle_code'] = $selectedVehicle;
}
if ($selectedDriver !== '') {
    $liquidationWhere .= ' AND cash_advances.driver_name = :driver_name';
    $liquidationParams['driver_name'] = $selectedDriver;
}
if ($selectedAdvanceStatus !== '') {
    $liquidationWhere .= ' AND ' . $advanceStatusSql . ' = :advance_status';
    $liquidationParams['advance_status'] = $selectedAdvanceStatus;
}
$liquidationQuery = $db->prepare(
    'SELECT advance_liquidations.liquidated_at, advance_liquidations.liquidated_by, advance_liquidations.amount_cents,
            advance_liquidations.receipt_reference, advance_liquidations.notes, advance_liquidations.id, advance_liquidations.status,
            advance_liquidations.receipt_name,
            cash_advances.driver_name, cash_advances.vehicle_code, cash_advances.purpose
     FROM advance_liquidations
     JOIN cash_advances ON cash_advances.id = advance_liquidations.advance_id ' . $liquidationWhere .
     ' ORDER BY advance_liquidations.liquidated_at DESC, advance_liquidations.id DESC'
);
$liquidationQuery->execute($liquidationParams);
$liquidationHistory = $liquidationQuery->fetchAll(PDO::FETCH_ASSOC);

$pendingLiquidationQuery = $db->prepare(
    'SELECT advance_liquidations.id, advance_liquidations.advance_id, advance_liquidations.liquidated_at,
            advance_liquidations.liquidated_by, advance_liquidations.amount_cents,
            advance_liquidations.receipt_reference, advance_liquidations.notes, advance_liquidations.receipt_name,
            cash_advances.driver_name, cash_advances.vehicle_code, cash_advances.purpose
     FROM advance_liquidations
     JOIN cash_advances ON cash_advances.id = advance_liquidations.advance_id
     WHERE advance_liquidations.status = \'Submitted\'
     ORDER BY advance_liquidations.liquidated_at ASC, advance_liquidations.id ASC'
);
$pendingLiquidationQuery->execute();
$pendingLiquidations = $pendingLiquidationQuery->fetchAll(PDO::FETCH_ASSOC);

if (($_GET['export'] ?? '') === 'csv' && in_array($activeView, ['expenses', 'financial-reports'], true)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fleet-expenses-' . $periodStart . '-to-' . $periodEnd . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Expense ID', 'Date', 'Vehicle', 'Assigned Driver', 'Category', 'Description', 'Amount (PHP)', 'Recorded By', 'Status']);
    foreach ($expenses as $expense) {
            fputcsv($output, [
                'EXP-' . str_pad((string) $expense['id'], 5, '0', STR_PAD_LEFT),
                $expense['expense_date'],
                csvSafe($expense['vehicle_code']),
                csvSafe($expense['driver_name'] ?: 'Unassigned'),
                csvSafe($expense['category']),
                csvSafe($expense['description']),
                number_format($expense['amount_cents'] / 100, 2, '.', ''),
                csvSafe($expense['recorded_by']),
                csvSafe($expense['status']),
            ]);
    }
    fclose($output);
    exit;
}
if (($_GET['export'] ?? '') === 'csv' && $activeView === 'bills') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fleet-bills-' . $periodStart . '-to-' . $periodEnd . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Bill ID', 'Supplier', 'Description', 'Category', 'Vehicle', 'Bill Date', 'Due Date', 'Amount (PHP)', 'Paid (PHP)', 'Remaining (PHP)', 'Status']);
    foreach ($bills as $bill) {
        fputcsv($output, [
            'BILL-' . str_pad((string) $bill['id'], 4, '0', STR_PAD_LEFT),
            csvSafe($bill['supplier']),
            csvSafe($bill['description']),
            csvSafe($bill['category']),
            csvSafe($bill['vehicle_code']),
            $bill['bill_date'],
            $bill['due_date'],
            number_format($bill['amount_cents'] / 100, 2, '.', ''),
            number_format($bill['paid_cents'] / 100, 2, '.', ''),
            number_format($bill['balance_cents'] / 100, 2, '.', ''),
            csvSafe($bill['payment_status']),
        ]);
    }
    fclose($output);
    exit;
}

require __DIR__ . '/dashboard.php';

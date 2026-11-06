<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

$db = require __DIR__ . '/database.php';

$requestedView = $_GET['view'] ?? 'home';
$validViews = ['home', 'expenses', 'advances', 'financial-reports'];
$activeView = is_string($requestedView) && in_array($requestedView, $validViews, true)
    ? $requestedView
    : 'home';
$views = [
    'home' => ['title' => 'Overview', 'description' => 'Key fleet expenses, bills, and driver advances at a glance.'],
    'expenses' => ['title' => 'Expenses', 'description' => 'Review, filter, record, and export fleet expense transactions.'],
    'advances' => ['title' => 'Driver advances', 'description' => 'Track cash issued to drivers and reconcile receipts.'],
    'financial-reports' => ['title' => 'Reports', 'description' => 'Compare fleet spend by month and expense category.'],
];
$categories = ['Fuel', 'Maintenance', 'Repairs', 'Parts', 'Registration', 'Inspection', 'Insurance', 'Tolls', 'Parking', 'Cleaning', 'Permits', 'Fines', 'Other'];
$categoryColors = ['Fuel' => '#2563eb', 'Maintenance' => '#16a34a', 'Repairs' => '#dc2626', 'Parts' => '#f59e0b', 'Registration' => '#7c3aed', 'Inspection' => '#0891b2', 'Insurance' => '#64748b', 'Tolls' => '#0f766e', 'Parking' => '#9333ea', 'Cleaning' => '#65a30d', 'Permits' => '#c2410c', 'Fines' => '#be123c', 'Other' => '#94a3b8'];
$accountingVehicles = $db->query('SELECT vehicle_code, make, driver_name FROM vehicles ORDER BY vehicle_code')->fetchAll(PDO::FETCH_ASSOC);
$accountingDrivers = $db->query('SELECT name FROM drivers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$defaultPeriod = $activeView === 'financial-reports' ? 'year' : 'month';
$period = in_array($_GET['period'] ?? $defaultPeriod, ['month', 'year', 'custom'], true) ? $_GET['period'] ?? $defaultPeriod : $defaultPeriod;
$today = date('Y-m-d');
$currentYear = (int) date('Y');
$yearOptions = range($currentYear - 5, $currentYear);
$selectedYear = filter_var($_GET['year'] ?? $currentYear, FILTER_VALIDATE_INT);
if ($selectedYear === false || !in_array($selectedYear, $yearOptions, true)) {
    $selectedYear = $currentYear;
}
$periodStart = $period === 'year' ? sprintf('%04d-01-01', $selectedYear) : date('Y-m-01');
$periodEnd = $period === 'year'
    ? min(sprintf('%04d-12-31', $selectedYear), $selectedYear === $currentYear ? $today : sprintf('%04d-12-31', $selectedYear))
    : $today;
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
$selectedDriver = is_string($_GET['driver'] ?? null) && in_array($_GET['driver'], array_column($accountingDrivers, 'name'), true) ? $_GET['driver'] : '';
$selectedAdvanceStatus = is_string($_GET['advance_status'] ?? null) && in_array($_GET['advance_status'], ['Pending', 'Partial', 'Liquidated'], true) ? $_GET['advance_status'] : '';

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
$summaryQuery = $db->prepare(
    'SELECT COALESCE(SUM(amount_cents), 0) AS total_cents,
            COALESCE(SUM(CASE WHEN category = \'Fuel\' THEN amount_cents ELSE 0 END), 0) AS fuel_cents,
            COALESCE(SUM(CASE WHEN category IN (\'Maintenance\', \'Repairs\', \'Parts\', \'Inspection\') THEN amount_cents ELSE 0 END), 0) AS maintenance_cents,
            COALESCE(SUM(CASE WHEN status = \'Pending\' THEN amount_cents ELSE 0 END), 0) AS pending_cents,
            SUM(CASE WHEN status = \'Pending\' THEN 1 ELSE 0 END) AS pending_count
     FROM expenses ' . $expenseWhere
);
$summaryQuery->execute($expenseParams);
$expenseSummary = $summaryQuery->fetch(PDO::FETCH_ASSOC);
$expenseSql = 'SELECT expenses.id, expenses.expense_date, expenses.vehicle_code, vehicles.make,
    expenses.driver_name,
        expenses.category, expenses.description, expenses.amount_cents, expenses.status
    FROM expenses JOIN vehicles ON vehicles.vehicle_code = expenses.vehicle_code ' . $expenseWhere .
    ' ORDER BY expenses.expense_date DESC, expenses.id DESC';
$expenseLimit = $activeView === 'home' ? ' LIMIT 8' : '';
$expenseQuery = $db->prepare($expenseSql . (($_GET['export'] ?? '') === 'csv' ? '' : $expenseLimit));
$expenseQuery->execute($expenseParams);
$expenses = $expenseQuery->fetchAll(PDO::FETCH_ASSOC);
$recentExpenses = array_slice($expenses, 0, 6);
$categoryQuery = $db->prepare(
    'SELECT category, SUM(amount_cents) AS total_cents FROM expenses ' . $expenseWhere . ' GROUP BY category ORDER BY total_cents DESC'
);
$categoryQuery->execute($expenseParams);
$expenseCategories = $categoryQuery->fetchAll(PDO::FETCH_ASSOC);
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
for ($month = 1; $month <= 12; $month++) {
    $monthKey = sprintf('%04d-%02d', $selectedYear, $month);
    $expenseTrend[] = ['month' => $monthKey, 'total_cents' => (int) ($monthlyTotals[$monthKey] ?? 0)];
}

$advanceStatusSql = 'CASE WHEN cash_advances.liquidated_cents >= cash_advances.amount_cents THEN \'Liquidated\' WHEN cash_advances.liquidated_cents > 0 THEN \'Partial\' ELSE \'Pending\' END';
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
            SUM(CASE WHEN liquidated_cents < amount_cents THEN 1 ELSE 0 END) AS open_count
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
            advance_liquidations.receipt_reference, advance_liquidations.notes,
            cash_advances.driver_name, cash_advances.vehicle_code, cash_advances.purpose
     FROM advance_liquidations
     JOIN cash_advances ON cash_advances.id = advance_liquidations.advance_id ' . $liquidationWhere .
     ' ORDER BY advance_liquidations.liquidated_at DESC, advance_liquidations.id DESC'
);
$liquidationQuery->execute($liquidationParams);
$liquidationHistory = $liquidationQuery->fetchAll(PDO::FETCH_ASSOC);

if (($_GET['export'] ?? '') === 'csv' && $activeView === 'expenses') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fleet-expenses-' . $periodStart . '-to-' . $periodEnd . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Vehicle', 'Assigned Driver', 'Category', 'Description', 'Amount (PHP)', 'Status']);
    foreach ($expenses as $expense) {
        $description = preg_match('/^[=+@\\-\\t\\r]/', $expense['description']) ? "'" . $expense['description'] : $expense['description'];
        fputcsv($output, [$expense['expense_date'], $expense['vehicle_code'], $expense['driver_name'] ?: 'Unassigned', $expense['category'], $description, number_format($expense['amount_cents'] / 100, 2, '.', ''), $expense['status']]);
    }
    fclose($output);
    exit;
}

require __DIR__ . '/dashboard.php';

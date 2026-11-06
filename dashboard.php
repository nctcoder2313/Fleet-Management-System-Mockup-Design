<?php
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$money = static fn ($centavos) => '₱' . number_format((int) $centavos / 100, 2);
$expenseLedgerParams = [
  'view' => 'expenses',
  'period' => $period,
  'year' => $selectedYear,
  'from' => $periodStart,
  'to' => $periodEnd,
  'vehicle' => $selectedVehicle,
];
$categoryTotal = max(1, array_sum(array_column($expenseCategories, 'total_cents')));
$categoryOffset = 0;
$donutStops = [];
$categoryPercentages = [];
$percentageUnits = [];
$percentageRemainders = [];
$allocatedPercentageUnits = 0;
foreach ($expenseCategories as $categoryIndex => $categoryItem) {
  $rawUnits = $categoryItem['total_cents'] / $categoryTotal * 1000;
  $percentageUnits[$categoryIndex] = (int) floor($rawUnits);
  $percentageRemainders[$categoryIndex] = $rawUnits - $percentageUnits[$categoryIndex];
  $allocatedPercentageUnits += $percentageUnits[$categoryIndex];
}
arsort($percentageRemainders);
foreach (array_slice(array_keys($percentageRemainders), 0, 1000 - $allocatedPercentageUnits) as $categoryIndex) {
  $percentageUnits[$categoryIndex]++;
}
foreach ($percentageUnits as $categoryIndex => $units) {
  $categoryPercentages[$categoryIndex] = $units / 10;
}
foreach ($expenseCategories as $categoryItem) {
  $slice = $categoryItem['total_cents'] / $categoryTotal * 100;
  $donutStops[] = $categoryColors[$categoryItem['category']] . ' ' . $categoryOffset . '% ' . ($categoryOffset + $slice) . '%';
  $categoryOffset += $slice;
}
$trendMax = max(1, ...array_column($expenseTrend, 'total_cents'));
$trendPoints = [];
$trendCount = max(1, count($expenseTrend) - 1);
foreach ($expenseTrend as $trendIndex => $trendItem) {
  $trendPoints[] = round(24 + ($trendIndex * 552 / $trendCount), 1) . ',' . round(130 - ($trendItem['total_cents'] / $trendMax * 100), 1);
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#dbeafe">
    <title><?= h($views[$activeView]['title']) ?> | Fleet Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body class="text-slate-900">
    <div class="app-shell min-h-screen">
      <aside class="sidebar bg-white" aria-label="Main navigation">
        <a class="brand" href="?view=home" aria-label="Fleet Management overview">
          <img class="brand-logo" src="assets/paragon.webp" alt="Paragon Communications Corp">
          <span class="brand-caption">Fleet Management</span>
        </a>

        <div class="workspace-label">ACCOUNTING DASHBOARD</div>
        <nav class="nav-list">
          <a class="nav-item <?= $activeView === 'home' ? 'is-active' : '' ?>" href="?view=home" title="Overview" <?= $activeView === 'home' ? 'aria-current="page"' : '' ?>><span class="nav-icon">◫</span>Overview</a>
          <a class="nav-item <?= $activeView === 'expenses' ? 'is-active' : '' ?>" href="?view=expenses" title="Expenses" <?= $activeView === 'expenses' ? 'aria-current="page"' : '' ?>><span class="nav-icon">＄</span>Expenses <span class="nav-count"><?= h($expenseSummary['pending_count'] ?? 0) ?> pending</span></a>
          <a class="nav-item <?= $activeView === 'bills' ? 'is-active' : '' ?>" href="?view=bills" title="Bills & Payments" <?= $activeView === 'bills' ? 'aria-current="page"' : '' ?>><span class="nav-icon">▤</span>Bills &amp; Payments <span class="nav-count"><?= h(count(array_filter($bills, static fn ($bill) => $bill['payment_status'] !== 'Paid'))) ?></span></a>
          <a class="nav-item <?= $activeView === 'advances' ? 'is-active' : '' ?>" href="?view=advances" title="Driver advances · <?= h($advanceSummary['open_count'] ?? 0) ?> open" <?= $activeView === 'advances' ? 'aria-current="page"' : '' ?>><span class="nav-icon">♙</span>Driver Advances <span class="nav-count"><?= h($advanceSummary['open_count'] ?? 0) ?></span></a>
          <a class="nav-item <?= $activeView === 'financial-reports' ? 'is-active' : '' ?>" href="?view=financial-reports" title="Financial reports" <?= $activeView === 'financial-reports' ? 'aria-current="page"' : '' ?>><span class="nav-icon">▥</span>Reports</a>
        </nav>

        <div class="sidebar-bottom">
          <div class="help-card">
            <div class="help-icon">?</div>
            <strong>Accounting</strong>
            <p>Fleet costs, pending bills, and driver liquidations.</p>
            <a href="mailto:finance@northstarfleet.example">Accounting support <span>↗</span></a>
          </div>
          <div class="profile-button" aria-label="Accounting profile">
            <span class="avatar avatar-sidebar">AC</span>
            <span class="profile-copy"><strong>Fleet Management</strong><small>Accounting</small></span>
            <span class="profile-more">···</span>
          </div>
        </div>
      </aside>

      <main class="main-content">
        <header class="topbar bg-white">
          <div class="breadcrumb"><strong>Accounting Dashboard</strong><span class="crumb-divider">/</span><span><?= h($views[$activeView]['title']) ?></span></div>
          <div class="topbar-actions">
            <span class="avatar avatar-top" aria-label="Fleet Management profile">FM</span>
          </div>
        </header>

        <div class="page-content">
          <section class="page-heading">
            <div>
              <h1><?= h($views[$activeView]['title']) ?></h1>
              <p><?= h($views[$activeView]['description']) ?></p>
            </div>
            <div class="heading-actions">
              <?php if (in_array($activeView, ['home', 'expenses'], true)): ?>
              <button class="button button-primary bg-blue-700 text-white" id="open-expense-dialog"><span>＋</span> Record expense</button>
              <?php elseif ($activeView === 'bills'): ?>
              <button class="button button-primary bg-blue-700 text-white" id="open-bill-dialog"><span>＋</span> Record bill</button>
              <?php elseif ($activeView === 'advances'): ?>
              <button class="button button-primary bg-blue-700 text-white" id="open-advance-dialog"><span>＋</span> Record advance</button>
              <?php endif; ?>
            </div>
          </section>

          <?php if (in_array($activeView, ['home', 'expenses', 'bills'], true)): ?>
          <form class="accounting-filters" method="get" aria-label="Filter expense records">
            <input type="hidden" name="view" value="<?= h($activeView) ?>">
            <label>Period<select name="period" id="accounting-period"><option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This month</option><option value="last-month" <?= $period === 'last-month' ? 'selected' : '' ?>>Last month</option><option value="year" <?= $period === 'year' ? 'selected' : '' ?>>This year</option><option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom date range</option></select></label>
            <label>Trend year<select name="year"><?php foreach ($yearOptions as $yearOption): ?><option value="<?= h($yearOption) ?>" <?= $selectedYear === $yearOption ? 'selected' : '' ?>><?= h($yearOption) ?></option><?php endforeach; ?></select></label>
            <label class="custom-date-field" <?= $period !== 'custom' ? 'hidden' : '' ?>>From<input type="date" name="from" value="<?= h($periodStart) ?>"></label>
            <label class="custom-date-field" <?= $period !== 'custom' ? 'hidden' : '' ?>>To<input type="date" name="to" value="<?= h($periodEnd) ?>"></label>
            <label>Vehicle<select name="vehicle"><option value="">All vehicles</option><?php foreach ($accountingVehicles as $vehicle): ?><option value="<?= h($vehicle['vehicle_code']) ?>" <?= $selectedVehicle === $vehicle['vehicle_code'] ? 'selected' : '' ?>><?= h($vehicle['vehicle_code']) ?> · <?= h($vehicle['make']) ?></option><?php endforeach; ?></select></label>
            <?php if ($activeView === 'expenses'): ?>
            <label>Category<select name="category"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= h($category) ?>" <?= $selectedCategory === $category ? 'selected' : '' ?>><?= h($category) ?></option><?php endforeach; ?></select></label>
            <label>Category group<select name="expense_group"><option value="">All groups</option><option value="maintenance" <?= $selectedExpenseGroup === 'maintenance' ? 'selected' : '' ?>>Maintenance &amp; repairs</option></select></label>
            <label>Payment status<select name="expense_status"><option value="">All statuses</option><?php foreach (['Paid', 'Pending'] as $status): ?><option value="<?= h($status) ?>" <?= $selectedExpenseStatus === $status ? 'selected' : '' ?>><?= h($status) ?></option><?php endforeach; ?></select></label>
            <?php elseif ($activeView === 'bills'): ?>
            <label>Category<select name="category"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= h($category) ?>" <?= $selectedCategory === $category ? 'selected' : '' ?>><?= h($category) ?></option><?php endforeach; ?></select></label>
            <label>Payment status<select name="bill_status"><option value="">All statuses</option><?php foreach (['Pending', 'Partially Paid', 'Overdue', 'Paid'] as $status): ?><option value="<?= h($status) ?>" <?= $selectedBillStatus === $status ? 'selected' : '' ?>><?= h($status) ?></option><?php endforeach; ?></select></label>
            <?php endif; ?>
            <?php if (in_array($activeView, ['expenses', 'bills'], true)): ?><label>Search<input type="search" name="search" value="<?= h($search) ?>" placeholder="ID, supplier, vehicle..."></label><?php endif; ?>
            <button class="button filter-submit" type="submit">Apply filters</button>
            <?php if ($activeView === 'expenses'): ?><a class="button export-button" href="?<?= h(http_build_query(['view' => 'expenses', 'period' => $period, 'year' => $selectedYear, 'from' => $periodStart, 'to' => $periodEnd, 'vehicle' => $selectedVehicle, 'category' => $selectedCategory, 'expense_group' => $selectedExpenseGroup, 'expense_status' => $selectedExpenseStatus, 'search' => $search, 'export' => 'csv'])) ?>" aria-label="Export filtered expenses as Excel-compatible CSV">↓ Export for Excel</a><?php endif; ?>
              <?php if ($activeView === 'bills'): ?><a class="button export-button" href="?<?= h(http_build_query(['view' => 'bills', 'period' => $period, 'year' => $selectedYear, 'from' => $periodStart, 'to' => $periodEnd, 'vehicle' => $selectedVehicle, 'category' => $selectedCategory, 'bill_status' => $selectedBillStatus, 'search' => $search, 'export' => 'csv'])) ?>">↓ Export for Excel</a><?php endif; ?>
          </form>
          <?php if ($activeView !== 'bills'): ?>
          <section class="metrics-grid accounting-metrics" aria-label="Financial summary">
            <a class="metric-card metric-link" href="?<?= h(http_build_query($expenseLedgerParams)) ?>" aria-label="View all fleet expenses for this period"><span class="metric-top"><span class="metric-label">Total fleet expenses</span><span class="metric-icon icon-blue">↗</span></span><strong class="metric-value"><?= h($money($expenseSummary['total_cents'])) ?></strong><span class="metric-foot">Recorded this period <span aria-hidden="true">↗</span></span></a>
            <a class="metric-card metric-link" href="?<?= h(http_build_query(array_merge($expenseLedgerParams, ['category' => 'Fuel']))) ?>" aria-label="View all fuel expenses for this period"><span class="metric-top"><span class="metric-label">Fuel costs</span><span class="metric-icon icon-amber">◉</span></span><strong class="metric-value"><?= h($money($expenseSummary['fuel_cents'])) ?></strong><span class="metric-foot">Gasoline and diesel <span aria-hidden="true">↗</span></span></a>
            <a class="metric-card metric-link" href="?<?= h(http_build_query(array_merge($expenseLedgerParams, ['expense_group' => 'maintenance']))) ?>" aria-label="View maintenance and repair expenses"><span class="metric-top"><span class="metric-label">Maintenance &amp; repairs</span><span class="metric-icon icon-green">⚙</span></span><strong class="metric-value"><?= h($money($expenseSummary['maintenance_cents'])) ?></strong><span class="metric-foot">Service, parts and repairs <span aria-hidden="true">↗</span></span></a>
            <a class="metric-card metric-link" href="?<?= h(http_build_query(['view' => 'bills', 'period' => $period, 'year' => $selectedYear, 'from' => $periodStart, 'to' => $periodEnd, 'vehicle' => $selectedVehicle])) ?>" aria-label="View outstanding bills"><span class="metric-top"><span class="metric-label">Outstanding bills</span><span class="metric-icon icon-red">!</span></span><strong class="metric-value"><?= h($money($outstandingBillsCents)) ?></strong><span class="metric-foot"><?= h(count(array_filter($bills, static fn ($bill) => $bill['payment_status'] === 'Overdue'))) ?> overdue in range <span aria-hidden="true">↗</span></span></a>
            <a class="metric-card metric-link" href="?<?= h(http_build_query(['view' => 'bills', 'period' => $period, 'year' => $selectedYear, 'from' => $periodStart, 'to' => $periodEnd])) ?>" aria-label="View payments made this period"><span class="metric-top"><span class="metric-label">Total payments</span><span class="metric-icon icon-violet">✓</span></span><strong class="metric-value"><?= h($money($totalPaymentsCents)) ?></strong><span class="metric-foot">Paid expenses and bill payments <span aria-hidden="true">↗</span></span></a>
            <a class="metric-card metric-link" href="?<?= h(http_build_query(['view' => 'advances', 'period' => $period, 'year' => $selectedYear, 'from' => $periodStart, 'to' => $periodEnd])) ?>" aria-label="View outstanding driver advances"><span class="metric-top"><span class="metric-label">Driver advances</span><span class="metric-icon icon-teal">♙</span></span><strong class="metric-value"><?= h($money($advanceSummary['outstanding_cents'])) ?></strong><span class="metric-foot"><?= h($advanceSummary['open_count'] ?? 0) ?> open advances <span aria-hidden="true">↗</span></span></a>
          </section>
          <?php else: ?>
          <section class="metrics-grid accounting-metrics" aria-label="Bills and payment summary">
            <article class="metric-card"><span class="metric-top"><span class="metric-label">Outstanding bills</span><span class="metric-icon icon-red">!</span></span><strong class="metric-value"><?= h($money($outstandingBillsCents)) ?></strong><span class="metric-foot"><?= h(count(array_filter($bills, static fn ($bill) => $bill['payment_status'] === 'Overdue'))) ?> overdue</span></article>
            <article class="metric-card"><span class="metric-top"><span class="metric-label">Payments this period</span><span class="metric-icon icon-green">✓</span></span><strong class="metric-value"><?= h($money($billPaymentsCents)) ?></strong><span class="metric-foot">Applied to supplier bills</span></article>
          </section>
          <?php endif; ?>
          <?php endif; ?>

          <?php if (in_array($activeView, ['home', 'financial-reports'], true)): ?>
          <?php if ($activeView === 'financial-reports'): ?>
          <form class="accounting-filters" method="get" aria-label="Filter financial reports">
            <input type="hidden" name="view" value="financial-reports">
            <label>Period<select name="period" id="accounting-period"><option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This month</option><option value="last-month" <?= $period === 'last-month' ? 'selected' : '' ?>>Last month</option><option value="year" <?= $period === 'year' ? 'selected' : '' ?>>This year</option><option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom date range</option></select></label>
            <label>Report year<select name="year" id="report-year"><?php foreach ($yearOptions as $yearOption): ?><option value="<?= h($yearOption) ?>" <?= $selectedYear === $yearOption ? 'selected' : '' ?>><?= h($yearOption) ?></option><?php endforeach; ?></select></label>
            <label class="custom-date-field" <?= $period !== 'custom' ? 'hidden' : '' ?>>From<input type="date" name="from" value="<?= h($periodStart) ?>"></label>
            <label class="custom-date-field" <?= $period !== 'custom' ? 'hidden' : '' ?>>To<input type="date" name="to" value="<?= h($periodEnd) ?>"></label>
            <label>Vehicle<select name="vehicle"><option value="">All vehicles</option><?php foreach ($accountingVehicles as $vehicle): ?><option value="<?= h($vehicle['vehicle_code']) ?>" <?= $selectedVehicle === $vehicle['vehicle_code'] ? 'selected' : '' ?>><?= h($vehicle['vehicle_code']) ?> · <?= h($vehicle['make']) ?></option><?php endforeach; ?></select></label>
            <label>Category<select name="category"><option value="">All expense categories</option><?php foreach ($categories as $category): ?><option value="<?= h($category) ?>" <?= $selectedCategory === $category ? 'selected' : '' ?>><?= h($category) ?></option><?php endforeach; ?></select></label>
            <button class="button filter-submit" type="submit">Apply filters</button>
            <a class="button export-button" href="?<?= h(http_build_query(['view' => 'financial-reports', 'period' => $period, 'year' => $selectedYear, 'from' => $periodStart, 'to' => $periodEnd, 'vehicle' => $selectedVehicle, 'category' => $selectedCategory, 'export' => 'csv'])) ?>">↓ Export for Excel</a>
            <button class="button export-button" type="button" data-print>↓ Save as PDF</button>
          </form>
          <?php endif; ?>
          <section class="accounting-chart-grid" aria-label="Expense reports">
            <article class="panel accounting-chart-panel">
              <div class="panel-heading"><div><h2>Monthly expense trend</h2><p>January–<?= h(date('F', mktime(0, 0, 0, $trendMonthCount, 1))) ?> <?= h($selectedYear) ?></p></div><span class="chart-unit">PHP</span></div>
              <?php if ($expenseTrend !== []): ?><div class="line-chart-wrap"><svg class="expense-line-chart" viewBox="0 0 600 170" role="img" aria-label="Monthly fleet expenses for <?= h($selectedYear) ?>"><path class="line-grid" d="M24 30H576 M24 80H576 M24 130H576"/><polyline points="<?= h(implode(' ', $trendPoints)) ?>"/><g><?php foreach ($expenseTrend as $trendIndex => $trendItem): ?><circle cx="<?= h(explode(',', $trendPoints[$trendIndex])[0]) ?>" cy="<?= h(explode(',', $trendPoints[$trendIndex])[1]) ?>" r="4"><title><?= h(date('F Y', strtotime($trendItem['month'] . '-01'))) ?>: <?= h($money($trendItem['total_cents'])) ?></title></circle><?php endforeach; ?></g></svg><div class="trend-labels"><?php foreach ($expenseTrend as $trendItem): ?><span><?= h(date('M', strtotime($trendItem['month'] . '-01'))) ?></span><?php endforeach; ?></div></div><?php else: ?><p class="chart-empty">No expense data for this year.</p><?php endif; ?>
            </article>
            <article class="panel accounting-chart-panel">
              <div class="panel-heading"><div><h2>Spend by category</h2><p>Share of selected-period expenses</p></div></div>
              <?php if ($expenseCategories !== []): ?><div class="category-breakdown"><div class="expense-donut" style="--donut-stops: <?= h(implode(', ', $donutStops)) ?>"><span><?= h($money($categoryTotal)) ?><small>Total</small></span></div><div class="category-legend"><?php foreach ($expenseCategories as $categoryIndex => $categoryItem): ?><div><i style="--category-color: <?= h($categoryColors[$categoryItem['category']] ?? '#94a3b8') ?>"></i><span><?= h($categoryItem['category']) ?></span><strong><?= h(number_format($categoryPercentages[$categoryIndex], 1)) ?>%</strong><em><?= h($money($categoryItem['total_cents'])) ?></em></div><?php endforeach; ?></div></div><?php else: ?><p class="chart-empty">No expense data for this period.</p><?php endif; ?>
            </article>
          </section>
          <?php endif; ?>

          <?php if ($activeView === 'home'): ?>
          <section class="accounting-table-grid">
            <article class="panel accounting-table-panel">
              <div class="panel-heading"><div><h2>Recent expenses</h2><p>Latest fleet transactions</p></div><a class="text-button" href="?view=expenses">All expenses <span>→</span></a></div>
              <div class="table-scroll"><table class="accounting-table"><thead><tr><th>TRANSACTION</th><th>DATE</th><th>VEHICLE</th><th>CATEGORY</th><th>DESCRIPTION</th><th>AMOUNT</th><th>STATUS</th></tr></thead><tbody>
                <?php foreach ($recentExpenses as $expense): ?><tr><td>EXP-<?= h(str_pad((string) $expense['id'], 5, '0', STR_PAD_LEFT)) ?></td><td><?= h(date('d M', strtotime($expense['expense_date']))) ?></td><td><strong><?= h($expense['vehicle_code']) ?></strong><small><?= h($expense['make']) ?></small></td><td><?= h($expense['category']) ?></td><td><?= h($expense['description']) ?></td><td class="amount-cell"><?= h($money($expense['amount_cents'])) ?></td><td><span class="expense-status status-<?= strtolower(h($expense['status'])) ?>"><?= h($expense['status']) ?></span></td></tr><?php endforeach; ?>
                <?php if ($recentExpenses === []): ?><tr><td class="table-empty" colspan="7">No expenses recorded for this period.</td></tr><?php endif; ?>
              </tbody></table></div>
            </article>
            <article class="panel accounting-table-panel">
              <div class="panel-heading"><div><h2>Driver advances</h2><p><?= h($advanceSummary['open_count'] ?? 0) ?> open · <?= h($advanceSummary['submitted_count'] ?? 0) ?> pending review · <?= h($money($advanceSummary['outstanding_cents'])) ?> outstanding</p></div><a class="text-button" href="?view=advances">All advances <span>→</span></a></div>
              <div class="table-scroll"><table class="accounting-table advance-table"><thead><tr><th>DRIVER / VEHICLE</th><th>PURPOSE</th><th>ISSUED</th><th>OUTSTANDING</th><th>STATUS</th></tr></thead><tbody>
                <?php foreach ($recentAdvances as $advance): ?><tr><td><strong><?= h($advance['driver_name']) ?></strong><small>ADV-<?= h(str_pad((string) $advance['id'], 4, '0', STR_PAD_LEFT)) ?> · <?= h($advance['vehicle_code']) ?> · <?= h(date('d M', strtotime($advance['advance_date']))) ?></small></td><td><?= h($advance['purpose']) ?></td><td><?= h($money($advance['amount_cents'])) ?></td><td><?= h($money($advance['amount_cents'] - $advance['liquidated_cents'])) ?></td><td><span class="expense-status status-<?= h(strtolower(str_replace(' ', '-', $advance['status']))) ?>"><?= h($advance['status']) ?></span></td></tr><?php endforeach; ?>
                <?php if ($recentAdvances === []): ?><tr><td class="table-empty" colspan="5">No driver advances for this period.</td></tr><?php endif; ?>
              </tbody></table></div>
            </article>
          </section>
          <?php elseif ($activeView === 'expenses'): ?>
          <section class="panel accounting-table-panel">
            <div class="panel-heading"><div><h2>Expense ledger</h2><p><?= h(count($expenses)) ?> recorded transactions</p></div></div>
            <div class="table-scroll"><table class="accounting-table"><thead><tr><th>EXPENSE ID</th><th>DATE</th><th>VEHICLE</th><th>CATEGORY</th><th>DESCRIPTION</th><th>AMOUNT</th><th>RECORDED BY</th><th>STATUS</th><th>ACTIONS</th></tr></thead><tbody>
              <?php foreach ($expenses as $expense): ?><tr><td>EXP-<?= h(str_pad((string) $expense['id'], 5, '0', STR_PAD_LEFT)) ?></td><td><?= h(date('d M Y', strtotime($expense['expense_date']))) ?></td><td><strong><?= h($expense['vehicle_code']) ?></strong><small><?= h($expense['make']) ?> · <?= h($expense['driver_name'] ?: 'Unassigned') ?></small></td><td><?= h($expense['category']) ?></td><td><?= h($expense['description']) ?></td><td class="amount-cell"><?= h($money($expense['amount_cents'])) ?></td><td><?= h($expense['recorded_by']) ?></td><td><span class="expense-status status-<?= strtolower(h($expense['status'])) ?>"><?= h($expense['status']) ?></span></td><td class="action-cell"><button class="table-action" type="button" data-expense-detail data-id="<?= h($expense['id']) ?>" data-date="<?= h($expense['expense_date']) ?>" data-vehicle="<?= h($expense['vehicle_code']) ?>" data-category="<?= h($expense['category']) ?>" data-status="<?= h($expense['status']) ?>" data-description="<?= h($expense['description']) ?>" data-amount="<?= h(number_format($expense['amount_cents'] / 100, 2, '.', '')) ?>" data-recorded-by="<?= h($expense['recorded_by']) ?>" data-has-receipt="<?= h($expense['has_receipt']) ?>">Details</button><?php if (!$expense['source_bill_id']): ?><button class="table-action" type="button" data-edit-expense="<?= h($expense['id']) ?>" data-date="<?= h($expense['expense_date']) ?>" data-vehicle="<?= h($expense['vehicle_code']) ?>" data-category="<?= h($expense['category']) ?>" data-status="<?= h($expense['status']) ?>" data-description="<?= h($expense['description']) ?>" data-amount="<?= h(number_format($expense['amount_cents'] / 100, 2, '.', '')) ?>" data-recorded-by="<?= h($expense['recorded_by']) ?>">Edit</button><?php else: ?><a class="table-action" href="?view=bills">Bill</a><?php endif; ?><?php if ($expense['has_receipt']): ?> <a class="table-action" href="?attachment=expense&amp;id=<?= h($expense['id']) ?>" target="_blank" rel="noopener">Receipt</a><?php endif; ?></td></tr><?php endforeach; ?>
              <?php if ($expenses === []): ?><tr><td class="table-empty" colspan="9">No expenses match these filters.</td></tr><?php endif; ?>
            </tbody></table></div>
          </section>
          <?php elseif ($activeView === 'bills'): ?>
          <section class="panel accounting-table-panel">
            <div class="panel-heading"><div><h2>Supplier bills</h2><p><?= h(count($bills)) ?> invoices · bill expenses are recorded once, and payments only reduce the balance</p></div><button class="button export-button" type="button" data-print>Print / Save as PDF</button></div>
            <div class="table-scroll"><table class="accounting-table bills-table"><thead><tr><th>BILL ID</th><th>SUPPLIER</th><th>DESCRIPTION</th><th>AMOUNT</th><th>PAID</th><th>REMAINING</th><th>DUE DATE</th><th>PAYMENT STATUS</th><th>ACTIONS</th></tr></thead><tbody>
              <?php foreach ($bills as $bill): ?><tr><td>BILL-<?= h(str_pad((string) $bill['id'], 4, '0', STR_PAD_LEFT)) ?></td><td><strong><?= h($bill['supplier']) ?></strong><small><?= h($bill['vehicle_code']) ?> · <?= h($bill['category']) ?></small></td><td><?= h($bill['description']) ?></td><td><?= h($money($bill['amount_cents'])) ?></td><td><?= h($money($bill['paid_cents'])) ?></td><td class="amount-cell"><?= h($money($bill['balance_cents'])) ?></td><td><?= h(date('d M Y', strtotime($bill['due_date']))) ?></td><td><span class="expense-status status-<?= h(strtolower(str_replace(' ', '-', $bill['payment_status']))) ?>"><?= h($bill['payment_status']) ?></span></td><td class="action-cell"><button class="table-action" type="button" data-edit-bill="<?= h($bill['id']) ?>" data-supplier="<?= h($bill['supplier']) ?>" data-description="<?= h($bill['description']) ?>" data-category="<?= h($bill['category']) ?>" data-vehicle="<?= h($bill['vehicle_code']) ?>" data-bill-date="<?= h($bill['bill_date']) ?>" data-due-date="<?= h($bill['due_date']) ?>" data-amount="<?= h(number_format($bill['amount_cents'] / 100, 2, '.', '')) ?>" data-has-payments="<?= (int) $bill['paid_cents'] > 0 ? '1' : '0' ?>">Edit</button><?php if ($bill['invoice_name']): ?><a class="table-action" href="?attachment=bill&amp;id=<?= h($bill['id']) ?>" target="_blank" rel="noopener">Invoice</a><?php endif; ?><?php if ((int) $bill['balance_cents'] > 0): ?> <button class="table-action" type="button" data-pay-bill="<?= h($bill['id']) ?>" data-balance="<?= h(number_format($bill['balance_cents'] / 100, 2, '.', '')) ?>" data-supplier="<?= h($bill['supplier']) ?>">Record payment</button><?php endif; ?><details class="bill-payment-details"><summary>History (<?= h(count($billPaymentsById[$bill['id']] ?? [])) ?>)</summary><?php foreach ($billPaymentsById[$bill['id']] ?? [] as $payment): ?><p><?= h(date('d M Y', strtotime($payment['payment_date']))) ?> · <?= h($money($payment['amount_cents'])) ?> · <?= h($payment['reference'] ?: 'No reference') ?></p><?php endforeach; ?></details></td></tr><?php endforeach; ?>
              <?php if ($bills === []): ?><tr><td class="table-empty" colspan="9">No bills match these filters.</td></tr><?php endif; ?>
            </tbody></table></div>
          </section>
          <?php elseif ($activeView === 'advances'): ?>
          <form class="accounting-filters" method="get" aria-label="Filter driver advances">
            <input type="hidden" name="view" value="advances">
            <label>Period<select name="period" id="accounting-period"><option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This month</option><option value="last-month" <?= $period === 'last-month' ? 'selected' : '' ?>>Last month</option><option value="year" <?= $period === 'year' ? 'selected' : '' ?>>This year</option><option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom date range</option></select></label>
            <label class="custom-date-field" <?= $period !== 'custom' ? 'hidden' : '' ?>>From<input type="date" name="from" value="<?= h($periodStart) ?>"></label>
            <label class="custom-date-field" <?= $period !== 'custom' ? 'hidden' : '' ?>>To<input type="date" name="to" value="<?= h($periodEnd) ?>"></label>
            <label>Driver<select name="driver"><option value="">All drivers</option><?php foreach ($accountingDrivers as $driver): ?><option value="<?= h($driver['name']) ?>" <?= $selectedDriver === $driver['name'] ? 'selected' : '' ?>><?= h($driver['name']) ?></option><?php endforeach; ?></select></label>
            <label>Vehicle<select name="vehicle"><option value="">All vehicles</option><?php foreach ($accountingVehicles as $vehicle): ?><option value="<?= h($vehicle['vehicle_code']) ?>" <?= $selectedVehicle === $vehicle['vehicle_code'] ? 'selected' : '' ?>><?= h($vehicle['vehicle_code']) ?></option><?php endforeach; ?></select></label>
            <label>Status<select name="advance_status"><option value="">All statuses</option><?php foreach (['Pending Liquidation', 'Submitted for Review', 'Approved', 'Rejected', 'Fully Liquidated'] as $status): ?><option value="<?= h($status) ?>" <?= $selectedAdvanceStatus === $status ? 'selected' : '' ?>><?= h($status) ?></option><?php endforeach; ?></select></label>
            <button class="button filter-submit" type="submit">Apply filters</button>
          </form>
          <section class="metrics-grid accounting-metrics" aria-label="Driver advance summary">
            <article class="metric-card"><span class="metric-top"><span class="metric-label">Cash issued</span><span class="metric-icon icon-blue">↗</span></span><strong class="metric-value"><?= h($money($advanceSummary['issued_cents'])) ?></strong><span class="metric-foot">Selected period</span></article>
            <article class="metric-card"><span class="metric-top"><span class="metric-label">Liquidated</span><span class="metric-icon icon-green">✓</span></span><strong class="metric-value"><?= h($money($advanceSummary['liquidated_cents'])) ?></strong><span class="metric-foot">Receipts submitted</span></article>
            <article class="metric-card"><span class="metric-top"><span class="metric-label">Outstanding</span><span class="metric-icon icon-amber">◷</span></span><strong class="metric-value"><?= h($money($advanceSummary['outstanding_cents'])) ?></strong><span class="metric-foot">Awaiting liquidation</span></article>
            <article class="metric-card"><span class="metric-top"><span class="metric-label">Open records</span><span class="metric-icon icon-red">!</span></span><strong class="metric-value"><?= h($advanceSummary['open_count'] ?? 0) ?></strong><span class="metric-foot">Need receipt follow-up</span></article>
          </section>
          <section class="panel accounting-table-panel liquidation-history-panel">
            <div class="panel-heading"><div><h2>Liquidations pending review</h2><p><?= h(count($pendingLiquidations)) ?> submissions awaiting approval</p></div></div>
            <div class="table-scroll"><table class="accounting-table"><thead><tr><th>RECEIPT DATE</th><th>DRIVER / VEHICLE</th><th>ADVANCE</th><th>AMOUNT</th><th>RECEIPT</th><th>NOTES</th><th>REVIEW</th></tr></thead><tbody>
              <?php foreach ($pendingLiquidations as $submission): ?><tr><td><?= h(date('d M Y', strtotime($submission['liquidated_at']))) ?></td><td><strong><?= h($submission['driver_name']) ?></strong><small><?= h($submission['vehicle_code']) ?></small></td><td><?= h($submission['purpose']) ?></td><td class="amount-cell"><?= h($money($submission['amount_cents'])) ?></td><td><?= h($submission['receipt_reference'] ?: '—') ?><?php if ($submission['receipt_name']): ?><small><a href="?attachment=liquidation&amp;id=<?= h($submission['id']) ?>" target="_blank" rel="noopener">View receipt</a></small><?php endif; ?></td><td><?= h($submission['notes'] ?: '—') ?></td><td class="action-cell"><button class="table-action" type="button" data-review-liquidation="<?= h($submission['id']) ?>" data-review="approve">Approve</button> <button class="table-action danger-action" type="button" data-review-liquidation="<?= h($submission['id']) ?>" data-review="reject">Reject</button></td></tr><?php endforeach; ?>
              <?php if ($pendingLiquidations === []): ?><tr><td class="table-empty" colspan="7">No liquidation submissions are awaiting review.</td></tr><?php endif; ?>
            </tbody></table></div>
          </section>
          <section class="panel accounting-table-panel">
            <div class="panel-heading"><div><h2>Driver advance register</h2><p><?= h(count($cashAdvances)) ?> records · issued, liquidated, and outstanding by trip</p></div></div>
            <div class="table-scroll"><table class="accounting-table advance-ledger"><thead><tr><th>ADVANCE ID</th><th>ISSUED</th><th>DRIVER / VEHICLE</th><th>PURPOSE / NOTES</th><th>RELEASED</th><th>APPROVED</th><th>SUBMITTED</th><th>BALANCE</th><th>LAST RECEIPT</th><th>STATUS</th><th>ACTIONS</th></tr></thead><tbody>
              <?php foreach ($cashAdvances as $advance): ?><tr><td>ADV-<?= h(str_pad((string) $advance['id'], 4, '0', STR_PAD_LEFT)) ?></td><td><?= h(date('d M Y', strtotime($advance['advance_date']))) ?></td><td><strong><?= h($advance['driver_name']) ?></strong><small><?= h($advance['vehicle_code']) ?></small></td><td><strong><?= h($advance['purpose']) ?></strong><small><?= h($advance['notes'] ?: 'No notes') ?></small></td><td><?= h($money($advance['amount_cents'])) ?></td><td><?= h($money($advance['liquidated_cents'])) ?></td><td><?= h($money($advance['submitted_cents'])) ?></td><td class="amount-cell"><?= h($money($advance['amount_cents'] - $advance['liquidated_cents'])) ?></td><td><?= h($advance['receipt_reference'] ?: '—') ?></td><td><span class="expense-status status-<?= h(strtolower(str_replace(' ', '-', $advance['status']))) ?>"><?= h($advance['status']) ?></span></td><td><?php if ((int) $advance['amount_cents'] > (int) $advance['liquidated_cents'] + (int) $advance['submitted_cents']): ?><button class="table-action" type="button" data-liquidate="<?= h($advance['id']) ?>" data-balance="<?= h(number_format(($advance['amount_cents'] - $advance['liquidated_cents'] - $advance['submitted_cents']) / 100, 2, '.', '')) ?>" data-driver="<?= h($advance['driver_name']) ?>">Submit receipts</button><?php endif; ?></td></tr><?php endforeach; ?>
              <?php if ($cashAdvances === []): ?><tr><td class="table-empty" colspan="11">No driver advances match these filters.</td></tr><?php endif; ?>
            </tbody></table></div>
          </section>
          <section class="panel accounting-table-panel liquidation-history-panel">
            <div class="panel-heading"><div><h2>Liquidation history</h2><p><?= h(count($liquidationHistory)) ?> receipt records · each partial submission is kept separately</p></div></div>
            <div class="table-scroll"><table class="accounting-table liquidation-history"><thead><tr><th>RECEIPT DATE</th><th>LIQUIDATED BY</th><th>DRIVER / VEHICLE</th><th>ADVANCE PURPOSE</th><th>AMOUNT SUBMITTED</th><th>STATUS</th><th>RECEIPT</th><th>NOTES</th></tr></thead><tbody>
              <?php foreach ($liquidationHistory as $liquidation): ?><tr><td><?= h(date('d M Y', strtotime($liquidation['liquidated_at']))) ?></td><td><strong><?= h($liquidation['liquidated_by']) ?></strong></td><td><strong><?= h($liquidation['driver_name']) ?></strong><small><?= h($liquidation['vehicle_code']) ?></small></td><td><?= h($liquidation['purpose']) ?></td><td class="amount-cell"><?= h($money($liquidation['amount_cents'])) ?></td><td><span class="expense-status status-<?= h(strtolower($liquidation['status'])) ?>"><?= h($liquidation['status']) ?></span></td><td><?php if ($liquidation['receipt_name']): ?><a href="?attachment=liquidation&amp;id=<?= h($liquidation['id']) ?>" target="_blank" rel="noopener"><?= h($liquidation['receipt_name']) ?></a><?php else: ?><?= h($liquidation['receipt_reference'] ?: '—') ?><?php endif; ?></td><td><?= h($liquidation['notes'] ?: '—') ?></td></tr><?php endforeach; ?>
              <?php if ($liquidationHistory === []): ?><tr><td class="table-empty" colspan="8">No liquidation receipts match these filters.</td></tr><?php endif; ?>
            </tbody></table></div>
          </section>
          <?php elseif ($activeView === 'financial-reports'): ?>
          <section class="metrics-grid accounting-metrics" aria-label="Report totals">
            <article class="metric-card"><span class="metric-top"><span class="metric-label">Total expenses</span><span class="metric-icon icon-blue">↗</span></span><strong class="metric-value"><?= h($money($expenseSummary['total_cents'])) ?></strong><span class="metric-foot">Selected period</span></article>
            <article class="metric-card"><span class="metric-top"><span class="metric-label">Outstanding bills</span><span class="metric-icon icon-red">!</span></span><strong class="metric-value"><?= h($money($outstandingBillsCents)) ?></strong><span class="metric-foot">Includes unpaid expenses</span></article>
            <article class="metric-card"><span class="metric-top"><span class="metric-label">Payments</span><span class="metric-icon icon-green">✓</span></span><strong class="metric-value"><?= h($money($totalPaymentsCents)) ?></strong><span class="metric-foot">Paid expenses and bill payments</span></article>
          </section>
          <section class="report-shortcuts" aria-label="Other accounting reports">
            <a class="button export-button" href="?<?= h(http_build_query(['view' => 'expenses', 'period' => $period, 'year' => $selectedYear, 'from' => $periodStart, 'to' => $periodEnd, 'vehicle' => $selectedVehicle, 'category' => 'Fuel'])) ?>">Fuel expense report</a>
            <a class="button export-button" href="?<?= h(http_build_query(['view' => 'expenses', 'period' => $period, 'year' => $selectedYear, 'from' => $periodStart, 'to' => $periodEnd, 'vehicle' => $selectedVehicle, 'expense_group' => 'maintenance'])) ?>">Maintenance &amp; repairs</a>
            <a class="button export-button" href="?<?= h(http_build_query(['view' => 'bills', 'period' => $period, 'year' => $selectedYear, 'from' => $periodStart, 'to' => $periodEnd, 'vehicle' => $selectedVehicle, 'bill_status' => 'Overdue'])) ?>">Outstanding bills</a>
            <a class="button export-button" href="?<?= h(http_build_query(['view' => 'advances', 'period' => $period, 'year' => $selectedYear, 'from' => $periodStart, 'to' => $periodEnd, 'vehicle' => $selectedVehicle])) ?>">Driver advances</a>
          </section>
          <?php endif; ?>

          <?php if ($activeView === 'overview'): ?>
          <section class="metrics-grid manager-metrics" aria-label="Fleet summary">
            <div class="metric-card bg-white">
              <span class="metric-top"><span class="metric-label">Active Vehicles</span><span class="metric-icon icon-green">↗</span></span>
              <span class="metric-value"><?= h($vehicleCounts['active']) ?></span>
              <span class="metric-foot"><span class="status-dot dot-green"></span><span>Currently on the road</span></span>
            </div>
            <div class="metric-card bg-white">
              <span class="metric-top"><span class="metric-label">Available Vehicles</span><span class="metric-icon icon-violet">◷</span></span>
              <span class="metric-value"><?= h($vehicleCounts['idle']) ?></span>
              <span class="metric-foot"><span class="status-dot dot-violet"></span><span>Ready for assignment</span></span>
            </div>
            <div class="metric-card bg-white">
              <span class="metric-top"><span class="metric-label">In-Transit Vehicles</span><span class="metric-icon icon-blue">⇢</span></span>
              <span class="metric-value"><?= h($activeTrips) ?></span>
              <span class="metric-foot"><span class="status-dot dot-green"></span><span>Vehicles on active trips</span></span>
            </div>
            <div class="metric-card bg-white">
              <span class="metric-top"><span class="metric-label">Pending Maintenance</span><span class="metric-icon icon-amber">⚙</span></span>
              <span class="metric-value"><?= h($pendingMaintenance) ?></span>
              <span class="metric-foot"><span class="trend-warn"><?= h($maintenanceDue) ?> due soon</span><span> · needs attention</span></span>
            </div>
          </section>

          <section class="manager-overview-grid" aria-label="Fleet map and recent activity">
            <article class="panel map-panel bg-white">
              <div class="panel-heading">
                <div>
                  <div class="panel-title-row"><h2>Fleet status map</h2><span class="count-pill count-green">Sample locations</span></div>
                  <p>Illustrative vehicle locations across the service area</p>
                </div>
                <span class="map-region">Pacific Northwest</span>
              </div>
              <div class="map-canvas">
                <svg viewBox="0 0 760 270" role="img" aria-label="Map showing fleet routes between Portland, Salem, Eugene, and Vancouver">
                  <path class="map-river" d="M0 85 C100 65 130 110 220 92 S355 55 430 84 560 112 760 66"/>
                  <path class="map-road" d="M70 230 C160 180 190 135 270 112 S430 111 510 70 645 66 712 25"/>
                  <path class="map-road map-road-secondary" d="M260 257 C270 192 290 150 270 112 S255 63 286 18"/>
                  <path class="map-route" d="M165 178 C204 149 231 130 270 112 S382 108 450 85"/>
                  <path class="map-route map-route-secondary" d="M270 112 C282 147 302 173 330 202"/>
                  <g class="map-city" transform="translate(165 178)"><circle r="7"/><text x="-30" y="24">Eugene</text></g>
                  <g class="map-city" transform="translate(270 112)"><circle r="8"/><text x="-27" y="-15">Portland</text></g>
                  <g class="map-city" transform="translate(450 85)"><circle r="6"/><text x="12" y="4">Vancouver</text></g>
                  <g class="map-city" transform="translate(330 202)"><circle r="6"/><text x="12" y="4">Salem</text></g>
                  <g class="map-vehicle" transform="translate(210 144)"><circle r="9"/><text y="3">1</text></g>
                  <g class="map-vehicle map-vehicle-alt" transform="translate(375 99)"><circle r="9"/><text y="3">2</text></g>
                  <g class="map-vehicle map-vehicle-third" transform="translate(180 166)"><circle r="9"/><text y="3">3</text></g>
                </svg>
              </div>
              <div class="map-legend"><span><i class="legend-active"></i>On trip</span><span><i class="legend-available"></i>Available</span><span><i class="legend-service"></i>Maintenance</span></div>
            </article>

            <article class="panel activity-panel bg-white">
              <div class="panel-heading">
                <div><h2>Recent activity</h2><p>Latest operational updates</p></div>
                <span class="count-pill"><?= h(count($recentActivity)) ?> updates</span>
              </div>
              <div class="activity-list">
                <?php foreach ($recentActivity as $activity): ?>
                <div class="activity-item">
                  <span class="activity-marker activity-<?= h($activity['category']) ?>"></span>
                  <span class="activity-copy"><strong><?= h($activity['message']) ?></strong><small><?= h(date('d M, g:i A', strtotime($activity['occurred_at']))) ?></small></span>
                </div>
                <?php endforeach; ?>
              </div>
            </article>
          </section>

          <section class="performance-grid" id="reports" aria-label="Fleet performance charts">
            <article class="panel chart-panel bg-white">
              <div class="panel-heading"><div><h2>Daily vehicle utilization</h2><p>Vehicles in use each day</p></div><span class="chart-unit">%</span></div>
              <div class="bar-chart" role="img" aria-label="Daily utilization percentages">
                <?php foreach ($dailyMetrics as $metric): ?>
                <div class="bar-column"><span class="bar-value"><?= h($metric['utilization']) ?>%</span><i class="chart-bar utilization-bar" style="--bar-height: <?= h($metric['utilization']) ?>%"></i><small><?= h(date('D', strtotime($metric['metric_date']))) ?></small></div>
                <?php endforeach; ?>
              </div>
            </article>
            <article class="panel chart-panel bg-white">
              <div class="panel-heading"><div><h2>Trip completion rate</h2><p>Completed trips per day</p></div><span class="chart-unit">%</span></div>
              <div class="bar-chart" role="img" aria-label="Daily trip completion rates">
                <?php foreach ($dailyMetrics as $metric): ?>
                <div class="bar-column"><span class="bar-value"><?= h($metric['completion_rate']) ?>%</span><i class="chart-bar completion-bar" style="--bar-height: <?= h($metric['completion_rate']) ?>%"></i><small><?= h(date('D', strtotime($metric['metric_date']))) ?></small></div>
                <?php endforeach; ?>
              </div>
            </article>
            <article class="panel chart-panel bg-white">
              <div class="panel-heading"><div><h2>Maintenance issues by department</h2><p>Open scheduled work</p></div><span class="chart-unit">items</span></div>
              <div class="department-chart">
                <?php $maxDepartmentIssues = max(1, ...array_column($maintenanceByDepartment, 'issue_count')); ?>
                <?php foreach ($maintenanceByDepartment as $department): ?>
                <div class="department-row"><div><span><?= h($department['department']) ?></span><strong><?= h($department['issue_count']) ?></strong></div><i style="--bar-width: <?= h((int) round($department['issue_count'] / $maxDepartmentIssues * 100)) ?>%"></i></div>
                <?php endforeach; ?>
                <?php if ($maintenanceByDepartment === []): ?><p class="chart-empty">No open maintenance issues.</p><?php endif; ?>
              </div>
            </article>
            <article class="panel chart-panel bg-white">
              <div class="panel-heading"><div><h2>Fuel consumption trend</h2><p>Fleet usage by day</p></div><span class="chart-unit">L</span></div>
              <div class="bar-chart" role="img" aria-label="Daily fuel consumption in liters">
                <?php $maxFuel = max(1, ...array_column($dailyMetrics, 'fuel_liters')); ?>
                <?php foreach ($dailyMetrics as $metric): ?>
                <div class="bar-column"><span class="bar-value"><?= h($metric['fuel_liters']) ?></span><i class="chart-bar fuel-bar" style="--bar-height: <?= h((int) round($metric['fuel_liters'] / $maxFuel * 100)) ?>%"></i><small><?= h(date('D', strtotime($metric['metric_date']))) ?></small></div>
                <?php endforeach; ?>
              </div>
            </article>
          </section>

          <section class="content-grid" hidden>
            <article class="panel fleet-panel bg-white" id="fleet">
              <div class="panel-heading">
                <div>
                  <div class="panel-title-row"><h2>Fleet status</h2><span class="count-pill"><?= h($totalVehicles) ?> vehicles</span></div>
                  <p>Live status and latest reported location</p>
                </div>
                <button class="text-button" type="button" data-toast="Showing all fleet vehicles.">View all <span>→</span></button>
              </div>
              <div class="table-toolbar">
                <div class="filter-tabs" role="group" aria-label="Filter vehicles">
                  <button class="filter-tab active" type="button" data-table-filter="all">All <span><?= h($totalVehicles) ?></span></button>
                  <button class="filter-tab" type="button" data-table-filter="active">On trip <span><?= h($vehicleCounts['active']) ?></span></button>
                  <button class="filter-tab" type="button" data-table-filter="idle">Available <span><?= h($vehicleCounts['idle']) ?></span></button>
                  <button class="filter-tab" type="button" data-table-filter="maintenance">Service <span><?= h($vehicleCounts['maintenance']) ?></span></button>
                  <button class="filter-tab" type="button" data-table-filter="offline">Offline <span><?= h($vehicleCounts['offline']) ?></span></button>
                </div>
                <button class="table-filter-button" type="button" data-toast="Additional fleet filters are coming soon.">☷ <span>Filter</span></button>
              </div>
              <div class="table-scroll">
                <table>
                  <thead><tr><th>VEHICLE ID</th><th>STATUS</th><th>DRIVER</th><th>ROUTE / LOCATION</th><th>LAST SERVICE</th><th>FUEL</th><th>ETA</th><th>DELIVERY</th><th></th></tr></thead>
                  <tbody id="vehicle-rows">
                    <?php foreach ($vehicles as $vehicle): ?>
                    <?php
                    $statusLabel = ['active' => 'On trip', 'idle' => 'Available', 'maintenance' => 'In service', 'offline' => 'Offline'][$vehicle['status']];
                    $statusClass = ['active' => 'status-active', 'idle' => 'status-idle', 'maintenance' => 'status-service', 'offline' => 'status-offline'][$vehicle['status']];
                    $driverMarkup = $vehicle['driver_name'] !== null
                        ? '<span class="driver-cell"><span class="mini-avatar avatar-' . h($vehicle['driver_color']) . '">' . h($vehicle['driver_initials']) . '</span>' . h($vehicle['driver_name']) . '</span>'
                        : '<span class="assignment-muted">—</span>';
                    $detail = implode('|', [
                        $vehicle['vehicle_code'] . ' · ' . $vehicle['make'] . ' ' . $vehicle['vehicle_type'],
                        $statusLabel . ($vehicle['driver_name'] ? ' with ' . $vehicle['driver_name'] : ''),
                        'Current location: ' . $vehicle['location'],
                        'Assignment: ' . ($vehicle['assignment'] ?: 'Unassigned'),
                        'Last service: ' . date('d M Y', strtotime($vehicle['last_service'])),
                        'Fuel level: ' . $vehicle['fuel_level'] . '%',
                    ]);
                    $search = strtolower(implode(' ', array_filter($vehicle)));
                    ?>
                    <tr data-status="<?= h($vehicle['status']) ?>" data-search="<?= h($search) ?>">
                      <td><span class="vehicle-name"><?= h($vehicle['vehicle_code']) ?></span><span class="vehicle-type"><?= h($vehicle['make']) ?> · <?= h($vehicle['vehicle_type']) ?></span></td>
                      <td><span class="status-badge <?= h($statusClass) ?>"><i></i><?= h($statusLabel) ?></span></td>
                      <td><?= $driverMarkup ?></td>
                      <td><span class="location-cell"><i></i><?= h($vehicle['assignment'] !== '' ? $vehicle['assignment'] : $vehicle['location']) ?></span><span class="vehicle-type"><?= h($vehicle['location']) ?></span></td>
                      <td><?= h(date('d M Y', strtotime($vehicle['last_service']))) ?></td>
                      <td><span class="fuel-level <?= $vehicle['fuel_level'] < 30 ? 'fuel-low' : '' ?>"><?= h($vehicle['fuel_level']) ?>%</span></td>
                      <td><?= h($vehicle['eta'] ?: '—') ?></td>
                      <td><span class="delivery-status <?= $vehicle['eta'] === null ? 'delivery-neutral' : ((int) $vehicle['delay_minutes'] > 0 ? 'delivery-late' : 'delivery-on-time') ?>"><?= $vehicle['eta'] === null ? '—' : ((int) $vehicle['delay_minutes'] > 0 ? 'Delayed' : 'On time') ?></span></td>
                      <td><button class="row-more" type="button" data-detail="<?= h($detail) ?>">···<span class="sr-only">View <?= h($vehicle['vehicle_code']) ?> details</span></button></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <div class="empty-state" id="fleet-empty" hidden>No vehicles match that filter.</div>
              </div>
              <div class="table-footer"><span id="table-count" data-total="<?= h($totalVehicles) ?>">Showing <?= h($totalVehicles) ?> of <?= h($totalVehicles) ?> vehicles</span><div class="pagination"><button type="button" aria-label="Previous page" disabled>‹</button><span>1 <i>of</i> 1</span><button type="button" aria-label="Next page" disabled>›</button></div></div>
            </article>

            <article class="panel trips-panel bg-white" id="trips">
              <div class="panel-heading">
                <div>
                  <div class="panel-title-row"><h2>Active trips</h2><span class="count-pill count-green"><?= h($activeTrips) ?> live</span></div>
                  <p>Trips currently in progress</p>
                </div>
                <button class="icon-button small-icon-button" type="button" aria-label="More trip options" data-toast="Trip options are coming soon.">···</button>
              </div>
              <div class="table-scroll">
                <table class="compact-table">
                  <thead><tr><th>TRIP / ROUTE</th><th>VEHICLE</th><th>DRIVER</th><th>ETA</th><th>STATUS</th><th></th></tr></thead>
                  <tbody id="trip-rows">
                    <?php foreach ($trips as $trip): ?>
                    <?php
                    $tripDetail = implode('|', [
                        $trip['id'] . ' · ' . $trip['origin'] . ' → ' . $trip['destination'],
                        'Vehicle ' . $trip['vehicle_code'] . ' · Driver ' . $trip['driver_name'],
                        'ETA ' . $trip['eta'] . ($trip['delay_minutes'] > 0 ? ' · ' . $trip['delay_minutes'] . ' minutes behind schedule' : ' · On schedule'),
                    ]);
                    $tripSearch = strtolower(implode(' ', $trip));
                    ?>
                    <tr class="trip-item" data-search="<?= h($tripSearch) ?>">
                      <td><span class="vehicle-name"><?= h($trip['id']) ?></span><span class="vehicle-type"><?= h($trip['origin']) ?> → <?= h($trip['destination']) ?></span></td>
                      <td><?= h($trip['vehicle_code']) ?></td>
                      <td><?= h($trip['driver_name']) ?></td>
                      <td><?= h($trip['eta']) ?></td>
                      <td><span class="delivery-status <?= $trip['delay_minutes'] > 0 ? 'delivery-late' : 'delivery-on-time' ?>"><?= $trip['delay_minutes'] > 0 ? 'Delayed' : 'On time' ?></span></td>
                      <td><button class="row-more" type="button" data-detail="<?= h($tripDetail) ?>" aria-label="View <?= h($trip['id']) ?> details">↗</button></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <div class="empty-state" id="trips-empty" hidden>No trips match your search.</div>
              </div>
              <button class="panel-link" type="button" data-toast="Opening the trip schedule...">View trip schedule <span>→</span></button>
            </article>

            <article class="panel alerts-panel bg-white" id="alerts">
              <div class="panel-heading">
                <div>
                  <div class="panel-title-row"><h2>Needs attention</h2><span class="count-pill count-red"><?= h($openAlerts) ?> open</span></div>
                  <p>Issues that may need your action</p>
                </div>
                <button class="text-button" type="button" data-toast="Showing all operational alerts.">View all <span>→</span></button>
              </div>
              <div class="alert-list">
                <?php foreach ($alerts as $alert): ?>
                <?php $alertDetail = implode('|', [$alert['title'], $alert['detail'], ucfirst($alert['severity']) . ' · Reported at ' . $alert['time_label']]); ?>
                <button class="alert-item" type="button" data-detail="<?= h($alertDetail) ?>">
                  <span class="alert-symbol <?= $alert['severity'] === 'urgent' ? 'alert-symbol-red' : 'alert-symbol-amber' ?>"><?= $alert['severity'] === 'urgent' ? '!' : '↗' ?></span>
                  <span class="alert-copy"><strong><?= h($alert['title']) ?></strong><span><?= h($alert['detail']) ?></span></span>
                  <span class="alert-trailing"><i class="severity-dot <?= $alert['severity'] === 'urgent' ? 'severity-high' : 'severity-medium' ?>"></i><small><?= h($alert['time_label']) ?></small></span>
                </button>
                <?php endforeach; ?>
              </div>
            </article>

            <article class="panel maintenance-panel bg-white" id="maintenance">
              <div class="panel-heading">
                <div>
                  <div class="panel-title-row"><h2>Upcoming maintenance</h2><span class="count-pill"><?= h(count($maintenanceItems)) ?> scheduled</span></div>
                  <p>Keep vehicles ready for the road</p>
                </div>
                <button class="text-button" type="button" data-toast="Opening the maintenance schedule...">Schedule <span>→</span></button>
              </div>
              <div class="table-scroll">
                <table class="compact-table">
                  <thead><tr><th>VEHICLE</th><th>TASK / DEPARTMENT</th><th>DUE DATE</th><th>STATUS</th><th>ACTION</th></tr></thead>
                  <tbody>
                    <?php foreach ($maintenanceItems as $item): ?>
                    <?php $isOverdue = $item['state'] === 'overdue'; ?>
                    <tr>
                      <td><span class="vehicle-name"><?= h($item['vehicle_code']) ?></span><span class="vehicle-type"><?= h($item['depot']) ?></span></td>
                      <td><?= h($item['task']) ?><span class="vehicle-type"><?= h($item['department']) ?></span></td>
                      <td><?= h(date('d M Y', strtotime($item['due_date']))) ?></td>
                      <td><span class="maintenance-tag <?= $isOverdue ? 'tag-overdue' : 'tag-soon' ?>"><?= h(ucfirst($item['state'])) ?></span></td>
                      <td><a class="maintenance-action" href="?view=maintenance">Manage</a></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </article>

            <article class="panel driver-panel bg-white" id="driver-schedule">
              <div class="panel-heading">
                <div><h2>Driver assignments</h2><p><?= h($onDutyDrivers) ?> of <?= h($totalDrivers) ?> drivers on duty</p></div>
                <span class="count-pill count-green">Today</span>
              </div>
              <div class="table-scroll">
                <table class="compact-table">
                  <thead><tr><th>DRIVER</th><th>VEHICLE</th><th>ASSIGNMENT</th><th>STATUS</th></tr></thead>
                  <tbody>
                    <?php foreach ($drivers as $driver): ?>
                    <tr>
                      <td><?= h($driver['name']) ?></td>
                      <td><?= h($driver['vehicle_code'] ?: '—') ?></td>
                      <td><?= h($driver['assignment'] ?: 'Available for assignment') ?></td>
                      <td><span class="delivery-status <?= $driver['on_duty'] ? 'delivery-on-time' : 'delivery-neutral' ?>"><?= $driver['on_duty'] ? 'On duty' : 'Off duty' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </article>
          </section>
          <?php elseif ($activeView === 'fleet'): ?>
          <section class="workspace-grid" aria-label="Editable fleet records">
            <?php foreach ($vehicles as $vehicle): ?>
            <article class="panel workspace-card search-item" data-search="<?= h(strtolower(implode(' ', array_filter($vehicle)))) ?>">
              <div class="panel-heading">
                <div><h2><?= h($vehicle['vehicle_code']) ?></h2><p><?= h($vehicle['make']) ?> · <?= h($vehicle['vehicle_type']) ?></p></div>
                <span class="status-badge status-<?= h($vehicle['status']) ?>"><?= h(ucfirst($vehicle['status'])) ?></span>
              </div>
              <form class="record-form" data-record-form>
                <input type="hidden" name="action" value="update-vehicle">
                <input type="hidden" name="vehicle_code" value="<?= h($vehicle['vehicle_code']) ?>">
                <label>Make<input name="make" maxlength="80" required value="<?= h($vehicle['make']) ?>"></label>
                <label>Type<input name="vehicle_type" maxlength="60" required value="<?= h($vehicle['vehicle_type']) ?>"></label>
                <label>Status<select name="status"><?php foreach (['active', 'idle', 'maintenance', 'offline'] as $status): ?><option value="<?= h($status) ?>" <?= $vehicle['status'] === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option><?php endforeach; ?></select></label>
                <label>Driver<input name="driver_name" maxlength="100" value="<?= h($vehicle['driver_name'] ?? '') ?>"></label>
                <label>Location<input name="location" maxlength="120" required value="<?= h($vehicle['location']) ?>"></label>
                <label>Assignment<input name="assignment" maxlength="120" value="<?= h($vehicle['assignment']) ?>"></label>
                <label>Last service<input name="last_service" type="date" required value="<?= h($vehicle['last_service']) ?>"></label>
                <label>Fuel level (%)<input name="fuel_level" type="number" min="0" max="100" required value="<?= h($vehicle['fuel_level']) ?>"></label>
                <button class="button button-primary bg-blue-700 text-white" type="submit">Save vehicle</button>
              </form>
            </article>
            <?php endforeach; ?>
          </section>
          <?php elseif ($activeView === 'trips'): ?>
          <section class="workspace-grid" aria-label="Editable trip records">
            <?php foreach ($allTrips as $trip): ?>
            <article class="panel workspace-card search-item" data-search="<?= h(strtolower(implode(' ', $trip))) ?>">
              <div class="panel-heading"><div><h2><?= h($trip['id']) ?></h2><p><?= h($trip['vehicle_code']) ?> · <?= h($trip['driver_name']) ?></p></div><span class="count-pill"><?= h(ucfirst($trip['status'])) ?></span></div>
              <form class="record-form" data-record-form>
                <input type="hidden" name="action" value="update-trip">
                <input type="hidden" name="id" value="<?= h($trip['id']) ?>">
                <label>Vehicle<select name="vehicle_code"><?php foreach ($vehicles as $vehicle): ?><option value="<?= h($vehicle['vehicle_code']) ?>" <?= $trip['vehicle_code'] === $vehicle['vehicle_code'] ? 'selected' : '' ?>><?= h($vehicle['vehicle_code']) ?></option><?php endforeach; ?></select></label>
                <label>Driver<select name="driver_name"><?php foreach ($drivers as $driver): ?><option value="<?= h($driver['name']) ?>" <?= $trip['driver_name'] === $driver['name'] ? 'selected' : '' ?>><?= h($driver['name']) ?></option><?php endforeach; ?></select></label>
                <label>Origin<input name="origin" maxlength="120" required value="<?= h($trip['origin']) ?>"></label>
                <label>Destination<input name="destination" maxlength="120" required value="<?= h($trip['destination']) ?>"></label>
                <label>Status<select name="status"><?php foreach (['active', 'upcoming', 'completed'] as $status): ?><option value="<?= h($status) ?>" <?= $trip['status'] === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option><?php endforeach; ?></select></label>
                <label>Delay (minutes)<input name="delay_minutes" type="number" min="0" required value="<?= h($trip['delay_minutes']) ?>"></label>
                <label>ETA<input name="eta" maxlength="30" required value="<?= h($trip['eta']) ?>"></label>
                <button class="button button-primary bg-blue-700 text-white" type="submit">Save trip</button>
              </form>
            </article>
            <?php endforeach; ?>
          </section>
          <?php elseif ($activeView === 'drivers'): ?>
          <section class="workspace-grid" aria-label="Editable driver records">
            <?php foreach ($drivers as $driver): ?>
            <article class="panel workspace-card search-item" data-search="<?= h(strtolower(implode(' ', $driver))) ?>">
              <div class="panel-heading"><div><h2><?= h($driver['name']) ?></h2><p><?= h($driver['vehicle_code'] ?: 'No vehicle assigned') ?></p></div><span class="count-pill"><?= $driver['on_duty'] ? 'On duty' : 'Off duty' ?></span></div>
              <div class="driver-assignment"><strong>Current assignment</strong><span><?= h($driver['assignment'] ?: 'Available for assignment') ?></span></div>
              <form class="record-form" data-record-form>
                <input type="hidden" name="action" value="update-driver">
                <input type="hidden" name="id" value="<?= h($driver['id']) ?>">
                <label>Duty status<select name="on_duty"><option value="1" <?= $driver['on_duty'] ? 'selected' : '' ?>>On duty</option><option value="0" <?= !$driver['on_duty'] ? 'selected' : '' ?>>Off duty</option></select></label>
                <button class="button button-primary bg-blue-700 text-white" type="submit">Save driver</button>
              </form>
            </article>
            <?php endforeach; ?>
          </section>
          <?php elseif ($activeView === 'maintenance'): ?>
          <section class="workspace-grid" aria-label="Editable maintenance records">
            <?php foreach ($allMaintenanceItems as $item): ?>
            <article class="panel workspace-card search-item" data-search="<?= h(strtolower(implode(' ', $item))) ?>">
              <div class="panel-heading"><div><h2><?= h($item['vehicle_code']) ?> · <?= h($item['task']) ?></h2><p><?= h($item['department']) ?> · <?= h($item['depot']) ?></p></div><span class="maintenance-tag tag-<?= h(str_replace(' ', '-', $item['state'])) ?>"><?= h(ucfirst($item['state'])) ?></span></div>
              <form class="record-form" data-record-form>
                <input type="hidden" name="action" value="update-maintenance">
                <input type="hidden" name="id" value="<?= h($item['id']) ?>">
                <label>Vehicle<select name="vehicle_code"><?php foreach ($vehicles as $vehicle): ?><option value="<?= h($vehicle['vehicle_code']) ?>" <?= $item['vehicle_code'] === $vehicle['vehicle_code'] ? 'selected' : '' ?>><?= h($vehicle['vehicle_code']) ?></option><?php endforeach; ?></select></label>
                <label>Task<input name="task" maxlength="120" required value="<?= h($item['task']) ?>"></label>
                <label>Department<input name="department" maxlength="80" required value="<?= h($item['department']) ?>"></label>
                <label>Depot<input name="depot" maxlength="80" required value="<?= h($item['depot']) ?>"></label>
                <label>Due date<input name="due_date" type="date" required value="<?= h($item['due_date']) ?>"></label>
                <label>Status<select name="state"><?php foreach (['overdue', 'due soon', 'requested', 'complete'] as $state): ?><option value="<?= h($state) ?>" <?= $item['state'] === $state ? 'selected' : '' ?>><?= h(ucfirst($state)) ?></option><?php endforeach; ?></select></label>
                <button class="button button-primary bg-blue-700 text-white" type="submit">Save maintenance</button>
              </form>
            </article>
            <?php endforeach; ?>
          </section>
          <?php elseif ($activeView === 'accounting'): ?>
          <?php
            $categoryColors = ['Fuel' => '#2563eb', 'Maintenance' => '#16a34a', 'Parts' => '#f59e0b', 'Tolls' => '#0891b2', 'Insurance' => '#7c3aed', 'Penalties' => '#dc2626', 'Inspection' => '#64748b', 'Other' => '#94a3b8'];
            $trendMax = max(1, ...array_column($expenseTrend, 'total_cents'));
            $trendPoints = [];
            $trendCount = max(1, count($expenseTrend) - 1);
            foreach ($expenseTrend as $trendIndex => $trendItem) {
                $trendPoints[] = round(24 + ($trendIndex * 552 / $trendCount), 1) . ',' . round(130 - ($trendItem['total_cents'] / $trendMax * 100), 1);
            }
            $donutStops = [];
            $categoryTotal = max(1, array_sum(array_column($expenseCategories, 'total_cents')));
            $categoryOffset = 0;
            foreach ($expenseCategories as $categoryItem) {
                $slice = $categoryItem['total_cents'] / $categoryTotal * 100;
                $donutStops[] = $categoryColors[$categoryItem['category']] . ' ' . $categoryOffset . '% ' . ($categoryOffset + $slice) . '%';
                $categoryOffset += $slice;
            }
          ?>
          <form class="accounting-filters" method="get" aria-label="Filter accounting records">
            <input type="hidden" name="view" value="accounting">
            <label>Period<select name="period" id="accounting-period"><option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This month</option><option value="year" <?= $period === 'year' ? 'selected' : '' ?>>Year to date</option><option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom dates</option></select></label>
            <label class="custom-date-field" <?= $period !== 'custom' ? 'hidden' : '' ?>>From<input type="date" name="from" value="<?= h($periodStart) ?>"></label>
            <label class="custom-date-field" <?= $period !== 'custom' ? 'hidden' : '' ?>>To<input type="date" name="to" value="<?= h($periodEnd) ?>"></label>
            <label>Vehicle<select name="vehicle"><option value="">All vehicles</option><?php foreach ($accountingVehicles as $vehicle): ?><option value="<?= h($vehicle['vehicle_code']) ?>" <?= $selectedVehicle === $vehicle['vehicle_code'] ? 'selected' : '' ?>><?= h($vehicle['vehicle_code']) ?> · <?= h($vehicle['make']) ?></option><?php endforeach; ?></select></label>
            <button class="button filter-submit" type="submit">Apply filters</button>
            <a class="button export-button" href="?<?= h(http_build_query(['view' => 'accounting', 'period' => $period, 'from' => $periodStart, 'to' => $periodEnd, 'vehicle' => $selectedVehicle, 'export' => 'csv'])) ?>" aria-label="Export filtered expenses as CSV">↓ Export CSV</a>
          </form>
          <section class="metrics-grid accounting-metrics" aria-label="Financial summary">
            <article class="metric-card"><span class="metric-top"><span class="metric-label">Fleet expenses</span><span class="metric-icon icon-blue">↗</span></span><strong class="metric-value"><?= h($money($expenseSummary['total_cents'])) ?></strong><span class="metric-foot">Selected period total</span></article>
            <article class="metric-card"><span class="metric-top"><span class="metric-label">Fuel costs</span><span class="metric-icon icon-amber">◉</span></span><strong class="metric-value"><?= h($money($expenseSummary['fuel_cents'])) ?></strong><span class="metric-foot">Fuel transactions</span></article>
            <article class="metric-card"><span class="metric-top"><span class="metric-label">Maintenance &amp; repair</span><span class="metric-icon icon-green">⚙</span></span><strong class="metric-value"><?= h($money($expenseSummary['maintenance_cents'])) ?></strong><span class="metric-foot">Maintenance, parts &amp; inspections</span></article>
            <article class="metric-card"><span class="metric-top"><span class="metric-label">Pending bills</span><span class="metric-icon icon-red">!</span></span><strong class="metric-value"><?= h($money($expenseSummary['pending_cents'])) ?></strong><span class="metric-foot">Awaiting payment</span></article>
          </section>
          <section class="accounting-chart-grid" aria-label="Expense reports">
            <article class="panel accounting-chart-panel">
              <div class="panel-heading"><div><h2>Expense trend</h2><p><?= $dailyTrend ? 'Daily' : 'Monthly' ?> spend · <?= h(date('M Y', strtotime($periodStart))) ?> to <?= h(date('M Y', strtotime($periodEnd))) ?></p></div><span class="chart-unit">PHP</span></div>
              <?php if ($expenseTrend !== []): ?><div class="line-chart-wrap"><svg class="expense-line-chart" viewBox="0 0 600 170" role="img" aria-label="<?= $dailyTrend ? 'Daily' : 'Monthly' ?> fleet expense trend in Philippine pesos"><path class="line-grid" d="M24 30H576 M24 80H576 M24 130H576"/><polyline points="<?= h(implode(' ', $trendPoints)) ?>"/><g><?php foreach ($expenseTrend as $trendIndex => $trendItem): ?><circle cx="<?= h(explode(',', $trendPoints[$trendIndex])[0]) ?>" cy="<?= h(explode(',', $trendPoints[$trendIndex])[1]) ?>" r="4"><title><?= h(date($dailyTrend ? 'd M Y' : 'F Y', strtotime($dailyTrend ? $trendItem['month'] : $trendItem['month'] . '-01'))) ?>: <?= h($money($trendItem['total_cents'])) ?></title></circle><?php endforeach; ?></g></svg><div class="trend-labels"><?php foreach ($expenseTrend as $trendItem): ?><span><?= h(date($dailyTrend ? 'd M' : 'M Y', strtotime($dailyTrend ? $trendItem['month'] : $trendItem['month'] . '-01'))) ?></span><?php endforeach; ?></div></div><?php else: ?><p class="chart-empty">No expense data for this period.</p><?php endif; ?>
            </article>
            <article class="panel accounting-chart-panel">
              <div class="panel-heading"><div><h2>Spend by category</h2><p>Share of selected-period expenses</p></div></div>
              <?php if ($expenseCategories !== []): ?><div class="category-breakdown"><div class="expense-donut" style="--donut-stops: <?= h(implode(', ', $donutStops)) ?>"><span><?= h($money($categoryTotal)) ?><small>Total</small></span></div><div class="category-legend"><?php foreach ($expenseCategories as $categoryItem): ?><div><i style="--category-color: <?= h($categoryColors[$categoryItem['category']]) ?>"></i><span><?= h($categoryItem['category']) ?></span><strong><?= h(number_format($categoryItem['total_cents'] / $categoryTotal * 100)) ?>%</strong></div><?php endforeach; ?></div></div><?php else: ?><p class="chart-empty">No expense data for this period.</p><?php endif; ?>
            </article>
          </section>
          <section class="accounting-table-grid">
            <article class="panel accounting-table-panel">
              <div class="panel-heading"><div><h2>Recent expenses</h2><p>Latest recorded fleet transactions</p></div><span class="count-pill"><?= h(count($recentExpenses)) ?> entries</span></div>
              <div class="table-scroll"><table class="accounting-table"><thead><tr><th>DATE</th><th>VEHICLE / DRIVER</th><th>CATEGORY</th><th>DESCRIPTION</th><th>AMOUNT</th><th>STATUS</th></tr></thead><tbody>
                <?php foreach ($recentExpenses as $expense): ?><tr><td><?= h(date('d M Y', strtotime($expense['expense_date']))) ?></td><td><strong><?= h($expense['vehicle_code']) ?></strong><small><?= h($expense['make']) ?> · <?= h($expense['driver_name'] ?: 'Unassigned') ?></small></td><td><?= h($expense['category']) ?></td><td><?= h($expense['description']) ?></td><td class="amount-cell"><?= h($money($expense['amount_cents'])) ?></td><td><span class="expense-status status-<?= strtolower(h($expense['status'])) ?>"><?= h($expense['status']) ?></span></td></tr><?php endforeach; ?>
                <?php if ($recentExpenses === []): ?><tr><td class="table-empty" colspan="6">No expenses recorded for this period.</td></tr><?php endif; ?>
              </tbody></table></div>
            </article>
            <article class="panel accounting-table-panel">
              <div class="panel-heading"><div><h2>Driver advances</h2><p>Cash issued and receipt liquidation status</p></div></div>
              <div class="table-scroll"><table class="accounting-table advance-table"><thead><tr><th>DRIVER / VEHICLE</th><th>ISSUED</th><th>LIQUIDATED</th><th>STATUS</th></tr></thead><tbody>
                <?php foreach ($cashAdvances as $advance): ?><tr><td><strong><?= h($advance['driver_name']) ?></strong><small><?= h($advance['vehicle_code']) ?> · <?= h(date('d M', strtotime($advance['advance_date']))) ?></small></td><td><?= h($money($advance['amount_cents'])) ?></td><td><?= h($money($advance['liquidated_cents'])) ?></td><td><span class="expense-status status-<?= strtolower(h($advance['status'])) ?>"><?= h($advance['status']) ?></span></td></tr><?php endforeach; ?>
                <?php if ($cashAdvances === []): ?><tr><td class="table-empty" colspan="4">No driver advances for this period.</td></tr><?php endif; ?>
              </tbody></table></div>
            </article>
          </section>
          <?php elseif ($activeView === 'reports'): ?>
          <section class="performance-grid" aria-label="Fleet performance reports">
            <article class="panel chart-panel bg-white">
              <div class="panel-heading"><div><h2>Daily vehicle utilization</h2><p>Vehicles in use each day</p></div><span class="chart-unit">%</span></div>
              <div class="bar-chart" role="img" aria-label="Daily utilization percentages">
                <?php foreach ($dailyMetrics as $metric): ?><div class="bar-column"><span class="bar-value"><?= h($metric['utilization']) ?>%</span><i class="chart-bar utilization-bar" style="--bar-height: <?= h($metric['utilization']) ?>%"></i><small><?= h(date('D', strtotime($metric['metric_date']))) ?></small></div><?php endforeach; ?>
              </div>
            </article>
            <article class="panel chart-panel bg-white">
              <div class="panel-heading"><div><h2>Trip completion rate</h2><p>Completed trips per day</p></div><span class="chart-unit">%</span></div>
              <div class="bar-chart" role="img" aria-label="Daily trip completion rates">
                <?php foreach ($dailyMetrics as $metric): ?><div class="bar-column"><span class="bar-value"><?= h($metric['completion_rate']) ?>%</span><i class="chart-bar completion-bar" style="--bar-height: <?= h($metric['completion_rate']) ?>%"></i><small><?= h(date('D', strtotime($metric['metric_date']))) ?></small></div><?php endforeach; ?>
              </div>
            </article>
            <article class="panel chart-panel bg-white">
              <div class="panel-heading"><div><h2>Maintenance by department</h2><p>Open scheduled work</p></div><span class="chart-unit">items</span></div>
              <div class="department-chart">
                <?php $maxDepartmentIssues = max(1, ...array_column($maintenanceByDepartment, 'issue_count')); ?>
                <?php foreach ($maintenanceByDepartment as $department): ?><div class="department-row"><div><span><?= h($department['department']) ?></span><strong><?= h($department['issue_count']) ?></strong></div><i style="--bar-width: <?= h((int) round($department['issue_count'] / $maxDepartmentIssues * 100)) ?>%"></i></div><?php endforeach; ?>
                <?php if ($maintenanceByDepartment === []): ?><p class="chart-empty">No open maintenance issues.</p><?php endif; ?>
              </div>
            </article>
            <article class="panel chart-panel bg-white">
              <div class="panel-heading"><div><h2>Fuel consumption</h2><p>Fleet usage by day</p></div><span class="chart-unit">L</span></div>
              <div class="bar-chart" role="img" aria-label="Daily fuel consumption in liters">
                <?php $maxFuel = max(1, ...array_column($dailyMetrics, 'fuel_liters')); ?>
                <?php foreach ($dailyMetrics as $metric): ?><div class="bar-column"><span class="bar-value"><?= h($metric['fuel_liters']) ?></span><i class="chart-bar fuel-bar" style="--bar-height: <?= h((int) round($metric['fuel_liters'] / $maxFuel * 100)) ?>%"></i><small><?= h(date('D', strtotime($metric['metric_date']))) ?></small></div><?php endforeach; ?>
              </div>
            </article>
          </section>
          <?php elseif ($activeView === 'settings'): ?>
          <section class="panel settings-panel">
            <div class="panel-heading"><div><h2>Manager workspace</h2><p>Current dashboard configuration</p></div></div>
            <div class="settings-content">
              <div><strong>Workspace</strong><span>Fleet Management · Manager</span></div>
              <div><strong>Data source</strong><span>Local SQLite sample data</span></div>
              <div><strong>Account settings</strong><span>Not configured in this mock-up.</span></div>
              <p>Settings are shown separately for clarity. This project has no persisted user-preferences model to edit.</p>
            </div>
          </section>
          <?php endif; ?>
          <footer class="page-footer"><span>Fleet Management · Accounting</span><span><i></i>Local fleet records</span></footer>
        </div>
      </main>
    </div>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <dialog class="detail-dialog expense-dialog" id="expense-dialog">
      <div class="dialog-top"><span class="dialog-icon">＋</span><button class="dialog-close" type="button" aria-label="Close dialog">×</button></div>
      <h2>Record fleet expense</h2>
      <p class="dialog-intro">Log a vehicle cost and its payment status.</p>
      <form id="expense-form" class="expense-form" data-record-form enctype="multipart/form-data">
        <input type="hidden" name="action" value="record-expense">
      <input type="hidden" name="id" value="">
        <label class="expense-field">Date<input id="expense-date" name="expense_date" type="date" value="<?= h(date('Y-m-d')) ?>" required></label>
        <label class="expense-field">Vehicle<select id="expense-vehicle" name="vehicle_code" required><?php foreach ($accountingVehicles as $vehicle): ?><option value="<?= h($vehicle['vehicle_code']) ?>" data-driver="<?= h($vehicle['driver_name'] ?? '') ?>"><?= h($vehicle['vehicle_code']) ?> · <?= h($vehicle['make']) ?></option><?php endforeach; ?></select></label>
        <div class="expense-driver-preview"><span aria-hidden="true">♙</span><div><small>ASSIGNED DRIVER</small><strong id="expense-assigned-driver">Unassigned</strong></div><i>Saved with this expense</i></div>
        <label class="expense-field">Category<select id="expense-category" name="category" required><?php foreach ($categories as $category): ?><option value="<?= h($category) ?>"><?= h($category) ?></option><?php endforeach; ?></select></label>
        <label class="expense-field">Payment status<select id="expense-status" name="status"><option>Paid</option><option>Pending</option></select></label>
        <label class="expense-field">Recorded by<input id="expense-recorded-by" name="recorded_by" maxlength="120" value="Accounting team" required></label>
        <label class="expense-field expense-field-wide">Description<input id="expense-description" name="description" maxlength="160" placeholder="Receipt, service, or route expense" required></label>
        <label class="expense-field expense-field-wide">Amount (PHP)<input id="expense-amount" name="amount" type="number" min="0.01" max="10000000" step="0.01" placeholder="0.00" required></label>
        <label class="expense-field expense-field-wide">Receipt or supporting document<input id="expense-receipt" name="receipt" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"></label>
        <button class="button button-primary expense-save-button" type="submit"><span aria-hidden="true">＋</span> Save expense</button>
      </form>
    </dialog>
    <dialog class="detail-dialog expense-dialog" id="expense-details-dialog">
      <div class="dialog-top"><span class="dialog-icon">↗</span><button class="dialog-close" type="button" aria-label="Close dialog">×</button></div>
      <h2>Expense details</h2>
      <div class="expense-details-content">
        <p><strong>Expense ID</strong><span id="expense-detail-id"></span></p>
        <p><strong>Date</strong><span id="expense-detail-date"></span></p>
        <p><strong>Vehicle</strong><span id="expense-detail-vehicle"></span></p>
        <p><strong>Category</strong><span id="expense-detail-category"></span></p>
        <p><strong>Description</strong><span id="expense-detail-description"></span></p>
        <p><strong>Amount</strong><span id="expense-detail-amount"></span></p>
        <p><strong>Recorded by</strong><span id="expense-detail-recorder"></span></p>
        <p><strong>Status</strong><span id="expense-detail-status"></span></p>
        <a id="expense-detail-receipt" class="button export-button" hidden target="_blank" rel="noopener">View receipt</a>
      </div>
    </dialog>
    <dialog class="detail-dialog expense-dialog" id="bill-dialog">
      <div class="dialog-top"><span class="dialog-icon">＋</span><button class="dialog-close" type="button" aria-label="Close dialog">×</button></div>
      <h2 id="bill-dialog-title">Record supplier bill</h2>
      <p class="dialog-intro">The bill is recorded as one expense. Payments only reduce its balance.</p>
      <form id="bill-form" class="expense-form" data-record-form enctype="multipart/form-data">
        <input type="hidden" name="action" value="record-bill">
        <input type="hidden" name="id" value="">
        <label class="expense-field">Supplier<input name="supplier" maxlength="120" required></label>
        <label class="expense-field">Vehicle<select name="vehicle_code" required><?php foreach ($accountingVehicles as $vehicle): ?><option value="<?= h($vehicle['vehicle_code']) ?>"><?= h($vehicle['vehicle_code']) ?> · <?= h($vehicle['make']) ?></option><?php endforeach; ?></select></label>
        <label class="expense-field">Category<select name="category" required><?php foreach ($categories as $category): ?><option value="<?= h($category) ?>"><?= h($category) ?></option><?php endforeach; ?></select></label>
        <label class="expense-field">Bill date<input name="bill_date" type="date" value="<?= h(date('Y-m-d')) ?>" required></label>
        <label class="expense-field">Due date<input name="due_date" type="date" value="<?= h(date('Y-m-d')) ?>" required></label>
        <label class="expense-field">Amount (PHP)<input name="amount" type="number" min="0.01" max="10000000" step="0.01" required></label>
        <label class="expense-field expense-field-wide">Description<input name="description" maxlength="160" required></label>
        <label class="expense-field expense-field-wide">Invoice (PDF, JPEG, PNG, WebP; max 5 MB)<input name="invoice" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"></label>
        <button class="button button-primary expense-save-button" type="submit">Save bill</button>
      </form>
    </dialog>
    <dialog class="detail-dialog issue-dialog expense-dialog" id="bill-payment-dialog">
      <div class="dialog-top"><span class="dialog-icon">₱</span><button class="dialog-close" type="button" aria-label="Close dialog">×</button></div>
      <h2>Record bill payment</h2>
      <p class="dialog-intro">Apply a payment to <strong id="bill-payment-supplier"></strong>. Remaining balance: <strong id="bill-payment-balance"></strong>.</p>
      <form id="bill-payment-form" data-record-form>
        <input type="hidden" name="action" value="record-bill-payment">
        <input type="hidden" name="id">
        <label for="bill-payment-date">Payment date</label><input id="bill-payment-date" name="payment_date" type="date" value="<?= h(date('Y-m-d')) ?>" required>
        <label for="bill-payment-amount">Payment amount (PHP)</label><input id="bill-payment-amount" name="amount" type="number" min="0.01" step="0.01" required>
        <label for="bill-payment-reference">Reference</label><input id="bill-payment-reference" name="reference" maxlength="100">
        <label for="bill-payment-notes">Notes</label><textarea id="bill-payment-notes" name="notes" rows="2" maxlength="500"></textarea>
        <button class="button button-primary dialog-done bg-blue-700 text-white" type="submit">Save payment</button>
      </form>
    </dialog>
    <dialog class="detail-dialog issue-dialog expense-dialog" id="advance-dialog">
      <div class="dialog-top"><span class="dialog-icon">＋</span><button class="dialog-close" type="button" aria-label="Close dialog">×</button></div>
      <h2>Record driver advance</h2>
      <p class="dialog-intro">Record cash issued for a route or vehicle expense.</p>
      <form id="advance-form" data-record-form>
        <input type="hidden" name="action" value="record-advance">
        <label for="advance-date">Issued date</label><input id="advance-date" name="advance_date" type="date" value="<?= h(date('Y-m-d')) ?>" required>
        <label for="advance-driver">Driver</label><select id="advance-driver" name="driver_name" required><?php foreach ($accountingDrivers as $driver): ?><option value="<?= h($driver['name']) ?>"><?= h($driver['name']) ?></option><?php endforeach; ?></select>
        <label for="advance-vehicle">Vehicle</label><select id="advance-vehicle" name="vehicle_code" required><?php foreach ($accountingVehicles as $vehicle): ?><option value="<?= h($vehicle['vehicle_code']) ?>"><?= h($vehicle['vehicle_code']) ?> · <?= h($vehicle['make']) ?></option><?php endforeach; ?></select>
        <label for="advance-purpose">Purpose</label><input id="advance-purpose" name="purpose" maxlength="120" placeholder="Fuel, tolls, trip allowance..." required>
        <label for="advance-amount">Amount (PHP)</label><input id="advance-amount" name="amount" type="number" min="0.01" max="10000000" step="0.01" required>
        <label for="advance-notes">Notes</label><textarea id="advance-notes" name="notes" rows="2" maxlength="500"></textarea>
        <button class="button button-primary dialog-done bg-blue-700 text-white" type="submit">Save advance</button>
      </form>
    </dialog>
    <dialog class="detail-dialog issue-dialog expense-dialog" id="liquidation-dialog">
      <div class="dialog-top"><span class="dialog-icon">✓</span><button class="dialog-close" type="button" aria-label="Close dialog">×</button></div>
      <h2>Record liquidation</h2>
      <p class="dialog-intro">Add receipts submitted by <strong id="liquidation-driver"></strong>.</p>
      <form id="liquidation-form" data-record-form>
        <input type="hidden" name="action" value="liquidate-advance">
        <input type="hidden" id="liquidation-id" name="id">
        <label for="liquidation-date">Receipt date</label><input id="liquidation-date" name="liquidated_at" type="date" value="<?= h(date('Y-m-d')) ?>" required>
        <label for="liquidated-by">Liquidated by</label><input id="liquidated-by" name="liquidated_by" maxlength="120" placeholder="Name of accounting staff" required>
        <label for="liquidation-amount">Liquidated amount (PHP)</label><input id="liquidation-amount" name="amount" type="number" min="0.01" step="0.01" required>
        <label for="receipt-reference">Receipt reference</label><input id="receipt-reference" name="receipt_reference" maxlength="80" placeholder="Receipt or voucher number">
        <label for="liquidation-notes">Notes</label><textarea id="liquidation-notes" name="liquidation_notes" rows="2" maxlength="500"></textarea>
        <label for="liquidation-receipt">Supporting receipt (PDF, JPEG, PNG, WebP; max 5 MB)</label><input id="liquidation-receipt" name="receipt" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp">
        <button class="button button-primary dialog-done bg-blue-700 text-white" type="submit">Save liquidation</button>
      </form>
    </dialog>
    <script src="app.js"></script>
  </body>
</html>

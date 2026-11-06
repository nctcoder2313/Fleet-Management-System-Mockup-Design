<?php
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$vehicleCounts = ['active' => 0, 'idle' => 0, 'maintenance' => 0, 'offline' => 0];
foreach ($vehicleStats as $stat) {
    $vehicleCounts[$stat['status']] = (int) $stat['count'];
}
$totalVehicles = array_sum($vehicleCounts);
$onDutyDrivers = (int) $onDutyDrivers;
$totalDrivers = (int) $totalDrivers;
$openAlerts = count($alerts);
$urgentAlerts = count(array_filter($alerts, static fn ($alert) => $alert['severity'] === 'urgent'));
$activeTrips = count($trips);
$maintenanceDue = count(array_filter($maintenanceItems, static fn ($item) => $item['state'] === 'due soon'));
$tripStatusLabels = [
    'completed' => ['label' => 'Completed', 'description' => 'Trip completed', 'color' => '#16a34a'],
    'in_progress' => ['label' => 'In progress', 'description' => 'Active without a reported delay', 'color' => '#2563eb'],
    'delayed' => ['label' => 'Delayed', 'description' => 'Active with a reported delay', 'color' => '#f59e0b'],
    'upcoming' => ['label' => 'Upcoming', 'description' => 'Not yet in progress', 'color' => '#94a3b8'],
];
$tripDonutCircumference = 2 * M_PI * 40;
$tripDonutOffset = 0;
$tripDonutSegments = [];
foreach ($tripStatusLabels as $status => $metadata) {
    $count = $weeklyTripCounts[$status];
    if ($weeklyTrips !== [] && $count > 0) {
        $length = $count / count($weeklyTrips) * $tripDonutCircumference;
        $tripDonutSegments[] = [
            'color' => $metadata['color'],
            'dasharray' => number_format($length, 2, '.', '') . ' ' . number_format($tripDonutCircumference - $length, 2, '.', ''),
            'dashoffset' => number_format(-$tripDonutOffset, 2, '.', ''),
        ];
        $tripDonutOffset += $length;
    }
}
$fuelChartPoints = [];
$fuelChartSegments = [];
$fuelChartMaximum = max(1, ...array_column(array_values($monthlyWeeks), 'fuel_liters'));
$monthlyWeekCount = count($monthlyWeeks);
$previousFuelPoint = null;
foreach (array_values($monthlyWeeks) as $index => $week) {
    $x = $monthlyWeekCount > 1 ? 70 + $index * 400 / ($monthlyWeekCount - 1) : 270;
    $value = $week['fuel_liters'];
    $point = [
        'x' => $x,
        'y' => $value === null ? null : 138 - $value / $fuelChartMaximum * 105,
        'value' => $value,
        'label' => $week['label'],
    ];
    if ($point['y'] !== null && $previousFuelPoint !== null) {
        $fuelChartSegments[] = 'M ' . number_format($previousFuelPoint['x'], 1, '.', '')
            . ' ' . number_format($previousFuelPoint['y'], 1, '.', '')
            . ' L ' . number_format($point['x'], 1, '.', '')
            . ' ' . number_format($point['y'], 1, '.', '');
    }
    $fuelChartPoints[] = $point;
    $previousFuelPoint = $point['y'] === null ? null : $point;
}
$overviewFuelChartMaximum = max(1, ...array_column(array_values($overviewWeeks), 'fuel_liters'));
$overviewFuelChartPoints = [];
$overviewFuelChartSegments = [];
$previousOverviewFuelPoint = null;
foreach (array_values($overviewWeeks) as $index => $week) {
    $x = count($overviewWeeks) > 1 ? 70 + $index * 400 / (count($overviewWeeks) - 1) : 270;
    $point = [
        'x' => $x,
        'y' => 138 - $week['fuel_liters'] / $overviewFuelChartMaximum * 105,
        'value' => $week['fuel_liters'],
        'label' => $week['label'],
    ];
    if ($previousOverviewFuelPoint !== null) {
        $overviewFuelChartSegments[] = 'M ' . number_format($previousOverviewFuelPoint['x'], 1, '.', '')
            . ' ' . number_format($previousOverviewFuelPoint['y'], 1, '.', '')
            . ' L ' . number_format($point['x'], 1, '.', '')
            . ' ' . number_format($point['y'], 1, '.', '');
    }
    $overviewFuelChartPoints[] = $point;
    $previousOverviewFuelPoint = $point;
}
$overviewTripDonutCircumference = 2 * M_PI * 40;
$overviewTripDonutOffset = 0;
$overviewTripDonutSegments = [];
foreach ($tripStatusLabels as $status => $metadata) {
    $count = $rangeTripCounts[$status];
    if ($rangeTrips !== [] && $count > 0) {
        $length = $count / count($rangeTrips) * $overviewTripDonutCircumference;
        $overviewTripDonutSegments[] = [
            'color' => $metadata['color'],
            'dasharray' => number_format($length, 2, '.', '') . ' ' . number_format($overviewTripDonutCircumference - $length, 2, '.', ''),
            'dashoffset' => number_format(-$overviewTripDonutOffset, 2, '.', ''),
        ];
        $overviewTripDonutOffset += $length;
    }
}
$views = [
    'overview' => ['title' => 'Dashboard', 'description' => 'Operational fleet, trip, driver, and maintenance performance.'],
    'fleet' => ['title' => 'Fleet status', 'description' => 'Review and update vehicle details, availability, and assignments.'],
    'trips' => ['title' => 'Trips & assignments', 'description' => 'Review routes and update trip status, driver, and timing.'],
    'drivers' => ['title' => 'Drivers', 'description' => 'Review driver availability and update duty status.'],
    'maintenance' => ['title' => 'Maintenance', 'description' => 'Track reported issues, review progress, and update maintenance status.'],
    'reports' => ['title' => 'Reports', 'description' => 'Review weekly utilization, trip status, fuel consumption, and maintenance.'],
    'settings' => ['title' => 'Settings', 'description' => 'Workspace details and available manager settings.'],
];
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
        <a class="brand" href="?view=overview" aria-label="Fleet Management home">
          <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 32 32" fill="none"><path d="M5 22.5 15.8 5l4.3 7-5 8.2H26l-3.7 6H5Z" fill="currentColor"/><path d="m18.7 13.2 4.1 6.7h-7.5" stroke="#13251F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <span>Fleet <span class="brand-light">Management</span></span>
        </a>

        <div class="workspace-label">MANAGER WORKSPACE</div>
        <nav class="nav-list">
          <a class="nav-item <?= $activeView === 'overview' ? 'is-active' : '' ?>" href="?view=overview" title="Overview" <?= $activeView === 'overview' ? 'aria-current="page"' : '' ?>><span class="nav-icon">◫</span>Overview</a>
          <a class="nav-item <?= $activeView === 'fleet' ? 'is-active' : '' ?>" href="?view=fleet" title="Fleet status" <?= $activeView === 'fleet' ? 'aria-current="page"' : '' ?>><span class="nav-icon">▤</span>Fleet Status <span class="nav-count"><?= h($totalVehicles) ?></span></a>
          <a class="nav-item <?= $activeView === 'trips' ? 'is-active' : '' ?>" href="?view=trips" title="Trips &amp; assignments" <?= $activeView === 'trips' ? 'aria-current="page"' : '' ?>><span class="nav-icon">↗</span>Trips &amp; Assignments</a>
          <a class="nav-item <?= $activeView === 'drivers' ? 'is-active' : '' ?>" href="?view=drivers" title="Drivers" <?= $activeView === 'drivers' ? 'aria-current="page"' : '' ?>><span class="nav-icon">♙</span>Drivers</a>
          <a class="nav-item <?= $activeView === 'maintenance' ? 'is-active' : '' ?>" href="?view=maintenance" title="Maintenance" <?= $activeView === 'maintenance' ? 'aria-current="page"' : '' ?>><span class="nav-icon">⚙</span>Maintenance <span class="nav-dot"></span></a>
          <a class="nav-item <?= $activeView === 'reports' ? 'is-active' : '' ?>" href="?view=reports" title="Reports" <?= $activeView === 'reports' ? 'aria-current="page"' : '' ?>><span class="nav-icon">▥</span>Reports</a>
          <a class="nav-item <?= $activeView === 'settings' ? 'is-active' : '' ?>" href="?view=settings" title="Settings" <?= $activeView === 'settings' ? 'aria-current="page"' : '' ?>><span class="nav-icon">⚙</span>Settings</a>
        </nav>

        <div class="sidebar-bottom">
          <div class="help-card">
            <div class="help-icon">?</div>
            <strong>Manager workspace</strong>
            <p>Operational fleet, trip, driver, and maintenance data.</p>
            <a href="mailto:ops@northstarfleet.example">Get support <span>↗</span></a>
          </div>
          <button class="profile-button" type="button" data-toast="Profile settings are coming soon.">
            <span class="avatar avatar-sidebar">FM</span>
            <span class="profile-copy"><strong>Fatma</strong><small>Manager</small></span>
            <span class="profile-more">···</span>
          </button>
        </div>
      </aside>

      <main class="main-content">
        <header class="topbar bg-white">
          <div class="breadcrumb"><strong>Fleet Management</strong><span class="crumb-divider">/</span><span><?= h($views[$activeView]['title']) ?></span></div>
          <div class="topbar-actions">
            <?php if (in_array($activeView, ['fleet', 'trips', 'drivers', 'maintenance'], true)): ?>
            <label class="search-box">
              <span aria-hidden="true">⌕</span>
              <input id="global-search" type="search" placeholder="Search this section..." aria-label="Search this section">
              <kbd>⌘ K</kbd>
            </label>
            <?php endif; ?>
            <button class="icon-button notification-button" type="button" aria-label="Notifications" data-toast="You're all caught up on notifications.">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg>
              <span class="notification-indicator"></span>
            </button>
            <span class="avatar avatar-top" aria-label="Manager profile">FM</span>
          </div>
        </header>

        <div class="page-content">
          <section class="page-heading">
            <div>
              <div class="eyebrow"><span class="live-pulse"></span> MANAGER WORKSPACE</div>
              <h1><?= h($views[$activeView]['title']) ?></h1>
              <p><?= h($views[$activeView]['description']) ?></p>
            </div>
            <div class="heading-actions">
              <?php if ($activeView === 'overview'): ?>
              <div class="date-picker" id="date-picker">
                <button class="date-selector" id="date-selector" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="date-picker-popover" data-start="<?= h($selectedStartDate) ?>" data-end="<?= h($selectedEndDate) ?>">
                  <span aria-hidden="true">▦</span><span id="selected-date-label"><?= h($selectedRangeLabel) ?></span><span class="chevron" aria-hidden="true">⌄</span>
                </button>
                <div class="date-picker-popover" id="date-picker-popover" role="dialog" aria-label="Select dashboard date range" hidden>
                  <div class="date-picker-presets" aria-label="Quick date ranges">
                    <button type="button" data-date-preset="today">Today</button>
                    <button type="button" data-date-preset="last-7-days">Last 7 days</button>
                    <button type="button" data-date-preset="this-month">This month</button>
                  </div>
                  <div class="calendar-header">
                    <button type="button" id="calendar-previous" aria-label="Previous month">‹</button>
                    <strong id="calendar-month-label"></strong>
                    <button type="button" id="calendar-next" aria-label="Next month">›</button>
                  </div>
                  <div class="calendar-weekdays" aria-hidden="true">
                    <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                  </div>
                  <div class="calendar-days" id="calendar-days" role="group" aria-label="Calendar dates"></div>
                  <p class="calendar-selection-hint" id="calendar-selection-hint" aria-live="polite">Select a start date, then an end date.</p>
                  <div class="calendar-actions">
                    <button class="calendar-cancel" id="calendar-cancel" type="button">Cancel</button>
                    <button class="calendar-apply" id="calendar-apply" type="button">Apply</button>
                  </div>
                </div>
              </div>
              <?php elseif ($activeView === 'maintenance'): ?>
              <button class="button button-primary bg-blue-700 text-white" id="report-issue"><span>＋</span> Report an issue</button>
              <?php endif; ?>
            </div>
          </section>

          <?php if ($activeView === 'overview'): ?>
          <section class="current-snapshot" aria-label="Current fleet snapshot">
            <div class="section-heading"><h2>Current fleet snapshot</h2><span>As of <?= h(gmdate('M j, Y g:i A')) ?> UTC</span></div>
            <div class="metrics-grid manager-metrics">
            <div class="metric-card bg-white">
              <span class="metric-top"><span class="metric-label">Active Vehicles</span><span class="metric-icon icon-green">↗</span></span>
              <span class="metric-value"><?= h($vehicleCounts['active']) ?></span>
              <span class="metric-foot"><span class="status-dot dot-green"></span><span>Current live status</span></span>
            </div>
            <div class="metric-card bg-white">
              <span class="metric-top"><span class="metric-label">Available Vehicles</span><span class="metric-icon icon-violet">◷</span></span>
              <span class="metric-value"><?= h($vehicleCounts['idle']) ?></span>
              <span class="metric-foot"><span class="status-dot dot-violet"></span><span>Current live status</span></span>
            </div>
            <div class="metric-card bg-white">
              <span class="metric-top"><span class="metric-label">Active Trips</span><span class="metric-icon icon-blue">⇢</span></span>
              <span class="metric-value"><?= h($activeTrips) ?></span>
              <span class="metric-foot"><span class="status-dot dot-green"></span><span>Current live status</span></span>
            </div>
            <div class="metric-card bg-white">
              <span class="metric-top"><span class="metric-label">Open Maintenance</span><span class="metric-icon icon-amber">⚙</span></span>
              <span class="metric-value"><?= h($pendingMaintenance) ?></span>
              <span class="metric-foot"><span class="trend-warn"><?= h($maintenanceDue) ?> due soon</span><span> · current snapshot</span></span>
            </div>
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
                <div><h2>Recent activity</h2><p>Updates from <?= h($selectedRangeLabel) ?> UTC</p></div>
                <span class="count-pill"><?= h(count($recentActivity)) ?> updates</span>
              </div>
              <div class="activity-list">
                <?php foreach ($recentActivity as $activity): ?>
                <div class="activity-item">
                  <span class="activity-marker activity-<?= h($activity['category']) ?>"></span>
                  <span class="activity-copy"><strong><?= h($activity['message']) ?></strong><small><?= h((new DateTimeImmutable($activity['occurred_at'], $utc))->format('M j, g:i A')) ?> UTC</small></span>
                </div>
                <?php endforeach; ?>
              </div>
            </article>
          </section>

          <section class="performance-section" id="reports" aria-label="Fleet performance for selected date range">
            <div class="section-heading"><h2>Fleet performance</h2><span><?= h($selectedRangeLabel) ?> · weeks start Monday · UTC</span></div>
            <div class="performance-grid">
              <article class="panel chart-panel bg-white">
                <div class="panel-heading"><div><h2>Average vehicle utilization</h2><p><?= $overviewMetricsIncludeSample ? 'Sample data included for dates without reported metrics' : 'Average of reported daily utilization' ?></p></div><span class="chart-unit">%</span></div>
                <div class="monthly-utilization-chart" role="img" aria-label="Average vehicle utilization by week for <?= h($selectedRangeLabel) ?>">
                  <?php foreach (array_values($overviewWeeks) as $week): ?>
                  <div class="monthly-bar-column">
                    <span class="monthly-bar-value"><?= $week['utilization'] === null ? 'Not reported' : h($week['utilization']) . '%' ?></span>
                    <i class="monthly-utilization-bar<?= $week['utilization'] === null ? ' is-unreported' : '' ?>" style="--bar-height: <?= $week['utilization'] === null ? '0px' : h(number_format($week['utilization'] * 1.05, 1, '.', '')) . 'px' ?>"></i>
                    <small><?= h($week['label']) ?></small>
                  </div>
                  <?php endforeach; ?>
                </div>
              </article>
              <article class="panel chart-panel bg-white">
                <div class="panel-heading"><div><h2>Trip status</h2><p><?= h($selectedRangeLabel) ?> UTC · Completion rate: <?= $rangeTripCompletionRate === null ? 'Not available' : h($rangeTripCompletionRate) . '%' ?></p></div><span class="chart-unit">trips</span></div>
                <div class="trip-status-layout">
                  <?php if ($rangeTrips !== []): ?>
                  <svg class="trip-donut" viewBox="0 0 120 120" role="img" aria-label="Trip status distribution for <?= h($selectedRangeLabel) ?>">
                    <circle class="trip-donut-track" cx="60" cy="60" r="40"></circle>
                    <?php foreach ($overviewTripDonutSegments as $segment): ?><circle class="trip-donut-segment" cx="60" cy="60" r="40" stroke="<?= h($segment['color']) ?>" stroke-dasharray="<?= h($segment['dasharray']) ?>" stroke-dashoffset="<?= h($segment['dashoffset']) ?>"></circle><?php endforeach; ?>
                    <text class="trip-donut-total" x="60" y="57"><?= h(count($rangeTrips)) ?></text><text class="trip-donut-caption" x="60" y="72">trips</text>
                  </svg>
                  <ul class="trip-status-legend">
                    <?php foreach ($tripStatusLabels as $status => $metadata): $statusCount = $rangeTripCounts[$status]; ?>
                    <li><span class="trip-legend-name"><i style="--status-color: <?= h($metadata['color']) ?>"></i><span><strong><?= h($metadata['label']) ?></strong><small><?= h($metadata['description']) ?></small></span></span><span class="trip-legend-value"><?= h(number_format($statusCount / count($rangeTrips) * 100, 1)) ?>%<small><?= h($statusCount) ?> trips</small></span></li>
                    <?php endforeach; ?>
                  </ul>
                  <?php if ($undatedTripCount > 0): ?><p class="trip-legend-note"><?= h($undatedTripCount) ?> undated trips excluded.</p><?php endif; ?>
                  <?php else: ?>
                  <p class="chart-empty">No dated trips are available for this range. <?= $undatedTripCount > 0 ? h($undatedTripCount) . ' undated trips are excluded.' : 'No trip dates were reported.' ?></p>
                  <?php endif; ?>
                </div>
              </article>
              <article class="panel chart-panel bg-white">
                <div class="panel-heading"><div><h2>Maintenance by department</h2><p>Uncompleted work due <?= h($selectedRangeLabel) ?> UTC</p></div><span class="chart-unit">items</span></div>
                <div class="department-chart">
                  <?php $maxDepartmentIssues = max(1, ...array_column($rangeMaintenanceByDepartment, 'issue_count')); ?>
                  <?php foreach ($rangeMaintenanceByDepartment as $department): ?>
                  <div class="department-row"><div><span><?= h($department['department']) ?></span><strong><?= h($department['issue_count']) ?></strong></div><i style="--bar-width: <?= h((int) round($department['issue_count'] / $maxDepartmentIssues * 100)) ?>%"></i></div>
                  <?php endforeach; ?>
                  <?php if ($rangeMaintenanceByDepartment === []): ?><p class="chart-empty">No uncompleted maintenance due in this range.</p><?php endif; ?>
                </div>
              </article>
              <article class="panel chart-panel bg-white">
                <div class="panel-heading"><div><h2>Fuel consumption by week</h2><p><?= $overviewMetricsIncludeSample ? 'Sample data included for dates without reported metrics' : 'Total fuel consumed each week' ?></p></div><span class="chart-unit">L</span></div>
                <div class="monthly-fuel-chart">
                  <svg viewBox="0 0 500 170" role="img" aria-label="Fuel consumption in liters by week for <?= h($selectedRangeLabel) ?>">
                    <line class="fuel-gridline" x1="42" y1="33" x2="485" y2="33"></line>
                    <line class="fuel-gridline" x1="42" y1="85" x2="485" y2="85"></line>
                    <line class="fuel-gridline" x1="42" y1="138" x2="485" y2="138"></line>
                    <text class="fuel-axis-label" x="36" y="29"><?= h(number_format($overviewFuelChartMaximum)) ?></text>
                    <text class="fuel-axis-label" x="36" y="134">0</text>
                    <?php foreach ($overviewFuelChartSegments as $segment): ?><path class="fuel-line-segment" d="<?= h($segment) ?>"></path><?php endforeach; ?>
                    <?php foreach ($overviewFuelChartPoints as $point): ?>
                    <?php if ($point['value'] !== null): ?>
                    <circle class="fuel-line-point" cx="<?= h(number_format($point['x'], 1, '.', '')) ?>" cy="<?= h(number_format($point['y'], 1, '.', '')) ?>" r="4"><title><?= h($point['label']) ?>: <?= h(number_format($point['value'])) ?> L</title></circle>
                    <text class="fuel-point-label" x="<?= h(number_format($point['x'], 1, '.', '')) ?>" y="<?= h(number_format($point['y'] - 9, 1, '.', '')) ?>"><?= h(number_format($point['value'])) ?></text>
                    <?php endif; ?>
                    <?php endforeach; ?>
                  </svg>
                  <div class="fuel-week-labels" style="grid-template-columns: repeat(<?= h(count($overviewFuelChartPoints)) ?>, minmax(0, 1fr))">
                    <?php foreach ($overviewFuelChartPoints as $point): ?><span><strong><?= h($point['label']) ?></strong><small><?= $point['value'] === null ? 'Not reported' : h(number_format($point['value'])) . ' L' ?></small></span><?php endforeach; ?>
                  </div>
                </div>
              </article>
            </div>
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
                    $fuelLevelLabel = $vehicle['fuel_level'] === null
                        ? 'Not reported'
                        : h($vehicle['fuel_level']) . '%';
                    $fuelUpdatedLabel = $vehicle['fuel_level_updated_at'] !== null
                        ? gmdate('M j, Y g:i A', strtotime($vehicle['fuel_level_updated_at'])) . ' UTC'
                        : 'Update time not recorded';
                    $detail = implode('|', [
                        $vehicle['vehicle_code'] . ' · ' . $vehicle['make'] . ' ' . $vehicle['vehicle_type'],
                        $statusLabel . ($vehicle['driver_name'] ? ' with ' . $vehicle['driver_name'] : ''),
                        'Current location: ' . $vehicle['location'],
                        'Assignment: ' . ($vehicle['assignment'] ?: 'Unassigned'),
                        'Last service: ' . date('M j, Y', strtotime($vehicle['last_service'])),
                        'Fuel level: ' . ($vehicle['fuel_level'] === null ? 'Not reported' : $vehicle['fuel_level'] . '%'),
                        'Fuel level last updated: ' . $fuelUpdatedLabel,
                    ]);
                    $search = strtolower(implode(' ', array_filter($vehicle)));
                    ?>
                    <tr data-status="<?= h($vehicle['status']) ?>" data-search="<?= h($search) ?>">
                      <td><span class="vehicle-name"><?= h($vehicle['vehicle_code']) ?></span><span class="vehicle-type"><?= h($vehicle['make']) ?> · <?= h($vehicle['vehicle_type']) ?></span></td>
                      <td><span class="status-badge <?= h($statusClass) ?>"><i></i><?= h($statusLabel) ?></span></td>
                      <td><?= $driverMarkup ?></td>
                      <td><span class="location-cell"><i></i><?= h($vehicle['assignment'] !== '' ? $vehicle['assignment'] : $vehicle['location']) ?></span><span class="vehicle-type"><?= h($vehicle['location']) ?></span></td>
                      <td><?= h(date('M j, Y', strtotime($vehicle['last_service']))) ?></td>
                      <td><span class="fuel-level <?= $vehicle['fuel_level'] !== null && $vehicle['fuel_level'] < 30 ? 'fuel-low' : '' ?>"><?= $fuelLevelLabel ?></span><span class="vehicle-type"><?= h($fuelUpdatedLabel) ?></span></td>
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
                      <td><?= h(date('M j, Y', strtotime($item['due_date']))) ?></td>
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
                <label>Fuel level (%)<input name="fuel_level" type="number" min="0" max="100" placeholder="Not reported" value="<?= h($vehicle['fuel_level'] ?? '') ?>"><small class="field-help">Last updated: <?= $vehicle['fuel_level_updated_at'] !== null ? h(gmdate('M j, Y g:i A', strtotime($vehicle['fuel_level_updated_at'])) . ' UTC') : 'Update time not recorded' ?></small></label>
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
                <label>Trip date<input name="trip_date" type="date" value="<?= h($trip['trip_date'] ?? '') ?>"><small class="field-help">Leave blank if the date is unknown; undated trips are excluded from weekly reports.</small></label>
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
          <section class="maintenance-board" aria-label="Maintenance issue tracking">
            <article class="panel issue-section issue-section-new">
              <div class="panel-heading">
                <div><h2>Newly Reported Issues</h2><p>Awaiting review or action</p></div>
                <span class="issue-count"><?= h(count($newIssues)) ?></span>
              </div>
              <div class="issue-list">
                <?php if ($newIssues === []): ?><p class="issue-empty">No issues are awaiting review.</p><?php endif; ?>
                <?php foreach ($newIssues as $issue): ?>
                <?php $issueDate = gmdate('M j, Y', strtotime($issue['created_at'] . ' UTC')); ?>
                <button class="issue-card search-item" type="button" data-search="<?= h(strtolower($issue['vehicle_or_trip'] . ' ' . $issue['description'] . ' ' . ($issue['issue_type'] ?? ''))) ?>"
                  data-issue-id="<?= h($issue['id']) ?>" data-issue-vehicle="<?= h($issue['vehicle_or_trip']) ?>"
                  data-issue-description="<?= h($issue['description']) ?>" data-issue-type="<?= h($issue['issue_type'] ?? '') ?>"
                  data-issue-priority="<?= h($issue['priority'] ?? '') ?>" data-issue-created="<?= h($issueDate) ?>"
                  data-issue-due="<?= h($issue['due_date'] ?? '') ?>" data-issue-notes="<?= h($issue['maintenance_notes']) ?>"
                  data-issue-status="<?= h($issue['status']) ?>">
                  <span class="issue-card-copy"><span class="issue-card-top"><strong>Issue #<?= h($issue['id']) ?> · <?= h($issue['vehicle_or_trip']) ?></strong><span class="issue-status status-awaiting">Awaiting Review</span></span>
                    <span class="issue-description"><?= h($issue['description']) ?></span>
                    <span class="issue-card-meta">Reported <?= h($issueDate) ?><?= $issue['issue_type'] ? ' · ' . h($issue['issue_type']) : '' ?></span></span>
                  <?php if ($issue['is_sample']): ?><span class="issue-sample-tag">Mock-up data</span><?php endif; ?>
                </button>
                <?php endforeach; ?>
              </div>
            </article>
            <article class="panel issue-section">
              <div class="panel-heading">
                <div><h2>In Progress / Delayed</h2><p>Work currently underway</p></div>
                <span class="issue-count"><?= h(count($activeIssues)) ?></span>
              </div>
              <div class="issue-list">
                <?php if ($activeIssues === []): ?><p class="issue-empty">No active maintenance issues.</p><?php endif; ?>
                <?php foreach ($activeIssues as $issue): ?>
                <?php $issueDate = gmdate('M j, Y', strtotime($issue['created_at'] . ' UTC')); $isDelayed = $issue['status'] === 'delayed'; ?>
                <button class="issue-card search-item" type="button" data-search="<?= h(strtolower($issue['vehicle_or_trip'] . ' ' . $issue['description'] . ' ' . ($issue['issue_type'] ?? ''))) ?>"
                  data-issue-id="<?= h($issue['id']) ?>" data-issue-vehicle="<?= h($issue['vehicle_or_trip']) ?>"
                  data-issue-description="<?= h($issue['description']) ?>" data-issue-type="<?= h($issue['issue_type'] ?? '') ?>"
                  data-issue-priority="<?= h($issue['priority'] ?? '') ?>" data-issue-created="<?= h($issueDate) ?>"
                  data-issue-due="<?= h($issue['due_date'] ?? '') ?>" data-issue-notes="<?= h($issue['maintenance_notes']) ?>"
                  data-issue-status="<?= h($issue['status']) ?>">
                  <span class="issue-card-copy"><span class="issue-card-top"><strong>Issue #<?= h($issue['id']) ?> · <?= h($issue['vehicle_or_trip']) ?></strong><span class="issue-status <?= $isDelayed ? 'status-delayed' : 'status-progress' ?>"><?= $isDelayed ? 'Delayed' : 'In Progress' ?></span></span>
                    <span class="issue-description"><?= h($issue['description']) ?></span>
                    <span class="issue-card-meta">Reported <?= h($issueDate) ?><?= $issue['due_date'] ? ' · Due ' . h(gmdate('M j, Y', strtotime($issue['due_date'] . ' UTC'))) : '' ?></span></span>
                  <?php if ($issue['is_sample']): ?><span class="issue-sample-tag">Mock-up data</span><?php endif; ?>
                </button>
                <?php endforeach; ?>
              </div>
            </article>
            <article class="panel issue-section">
              <div class="panel-heading">
                <div><h2>Completed Maintenance</h2><p>Resolved issue reports</p></div>
                <span class="issue-count"><?= h(count($completedIssues)) ?></span>
              </div>
              <div class="issue-list">
                <?php if ($completedIssues === []): ?><p class="issue-empty">No completed issues yet.</p><?php endif; ?>
                <?php foreach ($completedIssues as $issue): ?>
                <?php $issueDate = gmdate('M j, Y', strtotime($issue['created_at'] . ' UTC')); ?>
                <button class="issue-card search-item" type="button" data-search="<?= h(strtolower($issue['vehicle_or_trip'] . ' ' . $issue['description'] . ' ' . ($issue['issue_type'] ?? ''))) ?>"
                  data-issue-id="<?= h($issue['id']) ?>" data-issue-vehicle="<?= h($issue['vehicle_or_trip']) ?>"
                  data-issue-description="<?= h($issue['description']) ?>" data-issue-type="<?= h($issue['issue_type'] ?? '') ?>"
                  data-issue-priority="<?= h($issue['priority'] ?? '') ?>" data-issue-created="<?= h($issueDate) ?>"
                  data-issue-due="<?= h($issue['due_date'] ?? '') ?>" data-issue-notes="<?= h($issue['maintenance_notes']) ?>"
                  data-issue-status="<?= h($issue['status']) ?>">
                  <span class="issue-card-copy"><span class="issue-card-top"><strong>Issue #<?= h($issue['id']) ?> · <?= h($issue['vehicle_or_trip']) ?></strong><span class="issue-status status-completed">Completed</span></span>
                    <span class="issue-description"><?= h($issue['description']) ?></span>
                    <span class="issue-card-meta">Reported <?= h($issueDate) ?><?= $issue['issue_type'] ? ' · ' . h($issue['issue_type']) : '' ?></span></span>
                  <?php if ($issue['is_sample']): ?><span class="issue-sample-tag">Mock-up data</span><?php endif; ?>
                </button>
                <?php endforeach; ?>
              </div>
            </article>
          </section>
          <?php elseif ($activeView === 'reports'): ?>
          <section class="performance-section" aria-label="Fleet performance reports">
            <div class="section-heading"><h2>Monthly performance</h2><span><?= h($monthLabel) ?> · weeks start Monday · UTC</span></div>
            <div class="performance-grid">
              <article class="panel chart-panel bg-white">
                <div class="panel-heading"><div><h2>Average vehicle utilization</h2><p><?= $monthlyMetricsIncludeSample ? 'Sample data · missing weeks are illustrative' : 'Sample data · average of reported daily utilization' ?></p></div><span class="chart-unit">%</span></div>
                <div class="monthly-utilization-chart" role="img" aria-label="Average vehicle utilization by week for <?= h($monthLabel) ?>, sample data">
                  <?php foreach (array_values($monthlyWeeks) as $week): ?>
                  <div class="monthly-bar-column">
                    <span class="monthly-bar-value"><?= $week['utilization'] === null ? 'Not reported' : h($week['utilization']) . '%' ?></span>
                    <i class="monthly-utilization-bar<?= $week['utilization'] === null ? ' is-unreported' : '' ?>" style="--bar-height: <?= $week['utilization'] === null ? '0px' : h(number_format($week['utilization'] * 1.05, 1, '.', '')) . 'px' ?>"></i>
                    <small><?= h($week['label']) ?></small>
                  </div>
                  <?php endforeach; ?>
                </div>
              </article>
              <article class="panel chart-panel bg-white">
                <div class="panel-heading"><div><h2>Weekly trip status</h2><p><?= h($weekRangeLabel) ?> UTC · Completion rate: <?= $weeklyTripCompletionRate === null ? 'Not available' : h($weeklyTripCompletionRate) . '%' ?></p></div><span class="chart-unit">trips</span></div>
                <div class="trip-status-layout">
                  <?php if ($weeklyTrips !== []): ?>
                  <svg class="trip-donut" viewBox="0 0 120 120" role="img" aria-label="Weekly distribution of <?= h(count($weeklyTrips)) ?> dated trips by status">
                    <circle class="trip-donut-track" cx="60" cy="60" r="40"></circle>
                    <?php foreach ($tripDonutSegments as $segment): ?><circle class="trip-donut-segment" cx="60" cy="60" r="40" stroke="<?= h($segment['color']) ?>" stroke-dasharray="<?= h($segment['dasharray']) ?>" stroke-dashoffset="<?= h($segment['dashoffset']) ?>"></circle><?php endforeach; ?>
                    <text class="trip-donut-total" x="60" y="57"><?= h(count($weeklyTrips)) ?></text><text class="trip-donut-caption" x="60" y="72">trips</text>
                  </svg>
                  <ul class="trip-status-legend">
                    <?php foreach ($tripStatusLabels as $status => $metadata): $statusCount = $weeklyTripCounts[$status]; ?>
                    <li><span class="trip-legend-name"><i style="--status-color: <?= h($metadata['color']) ?>"></i><span><strong><?= h($metadata['label']) ?></strong><small><?= h($metadata['description']) ?></small></span></span><span class="trip-legend-value"><?= h(number_format($statusCount / count($weeklyTrips) * 100, 1)) ?>%<small><?= h($statusCount) ?> trips</small></span></li>
                    <?php endforeach; ?>
                  </ul>
                  <?php if ($undatedTripCount > 0): ?><p class="trip-legend-note"><?= h($undatedTripCount) ?> undated trips excluded.</p><?php endif; ?>
                  <?php else: ?>
                  <p class="chart-empty">No dated trips are available for this week. <?= $undatedTripCount > 0 ? h($undatedTripCount) . ' undated trips are excluded.' : 'No trip dates were reported.' ?></p>
                  <?php endif; ?>
                </div>
              </article>
              <article class="panel chart-panel bg-white">
                <div class="panel-heading"><div><h2>Maintenance by department</h2><p>Uncompleted work due <?= h($weekRangeLabel) ?> UTC</p></div><span class="chart-unit">items</span></div>
                <div class="department-chart">
                  <?php $maxDepartmentIssues = max(1, ...array_column($maintenanceByDepartment, 'issue_count')); ?>
                  <?php foreach ($maintenanceByDepartment as $department): ?><div class="department-row"><div><span><?= h($department['department']) ?></span><strong><?= h($department['issue_count']) ?></strong></div><i style="--bar-width: <?= h((int) round($department['issue_count'] / $maxDepartmentIssues * 100)) ?>%"></i></div><?php endforeach; ?>
                  <?php if ($maintenanceByDepartment === []): ?><p class="chart-empty">No uncompleted maintenance due this week.</p><?php endif; ?>
                </div>
              </article>
              <article class="panel chart-panel bg-white">
                <div class="panel-heading"><div><h2>Fuel consumption by week</h2><p><?= $monthlyMetricsIncludeSample ? 'Sample data · missing weeks are illustrative' : 'Sample data · reported weekly totals' ?></p></div><span class="chart-unit">L</span></div>
                <div class="monthly-fuel-chart">
                  <svg viewBox="0 0 500 170" role="img" aria-label="Fuel consumption in liters by week for <?= h($monthLabel) ?>, sample data">
                    <line class="fuel-gridline" x1="42" y1="33" x2="485" y2="33"></line>
                    <line class="fuel-gridline" x1="42" y1="85" x2="485" y2="85"></line>
                    <line class="fuel-gridline" x1="42" y1="138" x2="485" y2="138"></line>
                    <text class="fuel-axis-label" x="36" y="29"><?= h(number_format($fuelChartMaximum)) ?></text>
                    <text class="fuel-axis-label" x="36" y="134">0</text>
                    <?php foreach ($fuelChartSegments as $segment): ?><path class="fuel-line-segment" d="<?= h($segment) ?>"></path><?php endforeach; ?>
                    <?php foreach ($fuelChartPoints as $point): ?>
                    <?php if ($point['value'] !== null): ?>
                    <circle class="fuel-line-point" cx="<?= h(number_format($point['x'], 1, '.', '')) ?>" cy="<?= h(number_format($point['y'], 1, '.', '')) ?>" r="4"><title><?= h($point['label']) ?>: <?= h(number_format($point['value'])) ?> L</title></circle>
                    <text class="fuel-point-label" x="<?= h(number_format($point['x'], 1, '.', '')) ?>" y="<?= h(number_format($point['y'] - 9, 1, '.', '')) ?>"><?= h(number_format($point['value'])) ?></text>
                    <?php endif; ?>
                    <?php endforeach; ?>
                  </svg>
                  <div class="fuel-week-labels" style="grid-template-columns: repeat(<?= h(count($fuelChartPoints)) ?>, minmax(0, 1fr))">
                    <?php foreach ($fuelChartPoints as $point): ?><span><strong><?= h($point['label']) ?></strong><small><?= $point['value'] === null ? 'Not reported' : h(number_format($point['value'])) . ' L' ?></small></span><?php endforeach; ?>
                  </div>
                </div>
              </article>
            </div>
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
          <footer class="page-footer" id="manager-settings"><span>Fleet Management · Manager workspace</span><span><i></i>Sample data for dashboard mock-up</span></footer>
        </div>
      </main>
    </div>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <dialog class="detail-dialog" id="detail-dialog">
      <div class="dialog-top"><span class="dialog-icon">↗</span><button class="dialog-close" type="button" aria-label="Close dialog">×</button></div>
      <h2 id="dialog-title">Details</h2>
      <div class="dialog-body" id="dialog-body"></div>
      <button class="button button-primary dialog-done bg-blue-700 text-white" type="button">Done</button>
    </dialog>
    <dialog class="detail-dialog issue-dialog" id="issue-dialog">
      <div class="dialog-top"><span class="dialog-icon">＋</span><button class="dialog-close" type="button" aria-label="Close dialog">×</button></div>
      <h2>Report an issue</h2>
      <p class="dialog-intro">Tell the operations team what needs attention.</p>
      <form id="issue-form">
        <label for="issue-vehicle">Vehicle or trip</label>
        <input id="issue-vehicle" name="vehicle" placeholder="e.g. VH-204 or TRIP-2841" required>
        <label for="issue-description">What happened?</label>
        <textarea id="issue-description" name="description" rows="3" placeholder="Add a short description..." required></textarea>
        <label for="issue-type">Issue type <span>(optional)</span></label>
        <select id="issue-type" name="issue_type">
          <option value="">Select a type</option><option>Maintenance</option><option>Mechanical</option><option>Tires</option><option>Electrical</option><option>Safety</option><option>Other</option>
        </select>
        <label for="issue-priority">Priority <span>(optional)</span></label>
        <select id="issue-priority" name="priority">
          <option value="">Select a priority</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option>
        </select>
        <button class="button button-primary dialog-done bg-blue-700 text-white" type="submit">Submit report</button>
      </form>
    </dialog>
    <dialog class="detail-dialog issue-detail-dialog" id="issue-detail-dialog">
      <div class="dialog-top"><span class="dialog-icon">⚙</span><button class="dialog-close" type="button" aria-label="Close dialog">×</button></div>
      <h2 id="issue-detail-title">Issue details</h2>
      <div class="issue-detail-meta">
        <div><span>Vehicle or trip</span><strong id="issue-detail-vehicle"></strong></div>
        <div><span>Issue type</span><strong id="issue-detail-type"></strong></div>
        <div><span>Priority</span><strong id="issue-detail-priority"></strong></div>
        <div><span>Report date</span><strong id="issue-detail-created"></strong></div>
        <div><span>Description</span><strong id="issue-detail-description"></strong></div>
      </div>
      <form id="issue-detail-form">
        <input type="hidden" name="id">
        <label for="issue-detail-status">Status</label>
        <select id="issue-detail-status" name="status"></select>
        <label for="issue-detail-due">Due date <span>(optional)</span></label>
        <input id="issue-detail-due" name="due_date" type="date">
        <label for="issue-detail-notes">Maintenance notes</label>
        <textarea id="issue-detail-notes" name="maintenance_notes" rows="3" maxlength="3000" placeholder="Add work notes..."></textarea>
        <button class="button button-primary bg-blue-700 text-white" type="submit">Save issue</button>
      </form>
    </dialog>
    <script src="app.js"></script>
  </body>
</html>

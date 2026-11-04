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
$dateLabel = date('D, M j, Y');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#dbeafe">
    <title>Manager dashboard | Fleet Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="styles.css">
  </head>
  <body class="text-slate-900">
    <div class="app-shell min-h-screen">
      <aside class="sidebar bg-white" aria-label="Main navigation">
        <a class="brand" href="#overview" aria-label="Fleet Management home">
          <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 32 32" fill="none"><path d="M5 22.5 15.8 5l4.3 7-5 8.2H26l-3.7 6H5Z" fill="currentColor"/><path d="m18.7 13.2 4.1 6.7h-7.5" stroke="#13251F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <span>Fleet <span class="brand-light">Management</span></span>
        </a>

        <div class="workspace-label">MANAGER WORKSPACE</div>
        <nav class="nav-list">
          <a class="nav-item is-active" href="#overview" aria-current="page"><span class="nav-icon">◫</span>Overview</a>
          <a class="nav-item" href="#fleet"><span class="nav-icon">▤</span>Fleet Status <span class="nav-count"><?= h($totalVehicles) ?></span></a>
          <a class="nav-item" href="#trips"><span class="nav-icon">↗</span>Trips &amp; Assignments</a>
          <a class="nav-item" href="#driver-schedule"><span class="nav-icon">♙</span>Drivers</a>
          <a class="nav-item" href="#maintenance"><span class="nav-icon">⚙</span>Maintenance <span class="nav-dot"></span></a>
          <a class="nav-item" href="#reports"><span class="nav-icon">▥</span>Reports</a>
          <a class="nav-item" href="#manager-settings" data-toast="Manager profile preferences are not available in this mock-up."><span class="nav-icon">⚙</span>Settings</a>
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

      <main class="main-content" id="overview">
        <header class="topbar bg-white">
          <div class="breadcrumb"><strong>Fleet Management</strong><span class="crumb-divider">/</span><span>Manager Dashboard</span></div>
          <div class="topbar-actions">
            <label class="search-box">
              <span aria-hidden="true">⌕</span>
              <input id="global-search" type="search" placeholder="Search vehicles, trips..." aria-label="Search vehicles and trips">
              <kbd>⌘ K</kbd>
            </label>
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
              <h1>Manager dashboard <span class="wave">✳</span></h1>
              <p>Operational fleet, trip, driver, and maintenance performance.</p>
            </div>
            <div class="heading-actions">
              <div class="date-selector"><span>▦</span><span id="today-label"><?= h($dateLabel) ?></span><span class="chevron">⌄</span></div>
              <button class="button button-primary bg-blue-700 text-white" id="report-issue"><span>＋</span> Report an issue</button>
            </div>
          </section>

          <section class="metrics-grid manager-metrics" aria-label="Fleet summary">
            <button class="metric-card bg-white" type="button" data-filter="active">
              <span class="metric-top"><span class="metric-label">Active Vehicles</span><span class="metric-icon icon-green">↗</span></span>
              <span class="metric-value"><?= h($vehicleCounts['active']) ?></span>
              <span class="metric-foot"><span class="status-dot dot-green"></span><span>Currently on the road</span></span>
            </button>
            <button class="metric-card bg-white" type="button" data-filter="idle">
              <span class="metric-top"><span class="metric-label">Available Vehicles</span><span class="metric-icon icon-violet">◷</span></span>
              <span class="metric-value"><?= h($vehicleCounts['idle']) ?></span>
              <span class="metric-foot"><span class="status-dot dot-violet"></span><span>Ready for assignment</span></span>
            </button>
            <button class="metric-card bg-white" type="button" data-filter="active">
              <span class="metric-top"><span class="metric-label">In-Transit Vehicles</span><span class="metric-icon icon-blue">⇢</span></span>
              <span class="metric-value"><?= h($activeTrips) ?></span>
              <span class="metric-foot"><span class="status-dot dot-green"></span><span>Vehicles on active trips</span></span>
            </button>
            <button class="metric-card bg-white" type="button" data-filter="maintenance">
              <span class="metric-top"><span class="metric-label">Pending Maintenance</span><span class="metric-icon icon-amber">⚙</span></span>
              <span class="metric-value"><?= h($pendingMaintenance) ?></span>
              <span class="metric-foot"><span class="trend-warn"><?= h($maintenanceDue) ?> due soon</span><span> · needs attention</span></span>
            </button>
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
                  <span class="activity-copy"><strong><?= h($activity['message']) ?></strong><small><?= h(date('M j, g:i A', strtotime($activity['occurred_at']))) ?></small></span>
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

          <section class="content-grid">
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
                        'Last service: ' . date('M j, Y', strtotime($vehicle['last_service'])),
                        'Fuel level: ' . $vehicle['fuel_level'] . '%',
                    ]);
                    $search = strtolower(implode(' ', array_filter($vehicle)));
                    ?>
                    <tr data-status="<?= h($vehicle['status']) ?>" data-search="<?= h($search) ?>">
                      <td><span class="vehicle-name"><?= h($vehicle['vehicle_code']) ?></span><span class="vehicle-type"><?= h($vehicle['make']) ?> · <?= h($vehicle['vehicle_type']) ?></span></td>
                      <td><span class="status-badge <?= h($statusClass) ?>"><i></i><?= h($statusLabel) ?></span></td>
                      <td><?= $driverMarkup ?></td>
                      <td><span class="location-cell"><i></i><?= h($vehicle['assignment'] !== '' ? $vehicle['assignment'] : $vehicle['location']) ?></span><span class="vehicle-type"><?= h($vehicle['location']) ?></span></td>
                      <td><?= h(date('M j, Y', strtotime($vehicle['last_service']))) ?></td>
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
                      <td><?= h(date('M j, Y', strtotime($item['due_date']))) ?></td>
                      <td><span class="maintenance-tag <?= $isOverdue ? 'tag-overdue' : 'tag-soon' ?>"><?= h(ucfirst($item['state'])) ?></span></td>
                      <td><button class="maintenance-action" type="button" data-maintenance-request="<?= h($item['id']) ?>" <?= $item['state'] === 'requested' ? 'disabled' : '' ?>><?= $item['state'] === 'requested' ? 'Requested' : 'Request' ?></button></td>
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
        <button class="button button-primary dialog-done bg-blue-700 text-white" type="submit">Submit report</button>
      </form>
    </dialog>
    <script src="app.js"></script>
  </body>
</html>

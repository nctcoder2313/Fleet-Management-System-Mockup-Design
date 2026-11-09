<?php
declare(strict_types=1);

$db = require __DIR__ . '/database.php';

$requestedView = $_GET['view'] ?? 'overview';
$validViews = ['overview', 'fleet', 'trips', 'drivers', 'maintenance', 'reports', 'settings'];
$activeView = is_string($requestedView) && in_array($requestedView, $validViews, true)
    ? $requestedView
    : 'overview';

$utc = new DateTimeZone('UTC');
$monthStart = (new DateTimeImmutable('first day of this month', $utc))->setTime(0, 0);
$selectedStart = $monthStart;
$selectedEnd = new DateTimeImmutable('today', $utc);
// Overview and Reports share the selected UTC dates; invalid query values fall back to this month-to-date.
$requestedStart = $_GET['start'] ?? null;
$requestedEnd = $_GET['end'] ?? null;
$isValidDate = static function ($value): bool {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
        return false;
    }

    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year);
};
if ($isValidDate($requestedStart) && $isValidDate($requestedEnd) && $requestedStart <= $requestedEnd) {
    $selectedStart = new DateTimeImmutable($requestedStart, $utc);
    $selectedEnd = new DateTimeImmutable($requestedEnd, $utc);
}
$selectedStartDate = $selectedStart->format('Y-m-d');
$selectedEndDate = $selectedEnd->format('Y-m-d');
$selectedRangeLabel = $selectedStartDate === $selectedEndDate
    ? $selectedStart->format('M j, Y')
    : ($selectedStart->format('Y') === $selectedEnd->format('Y')
        ? ($selectedStart->format('m') === $selectedEnd->format('m')
            ? $selectedStart->format('M j') . '–' . $selectedEnd->format('j, Y')
            : $selectedStart->format('M j') . '–' . $selectedEnd->format('M j, Y'))
        : $selectedStart->format('M j, Y') . '–' . $selectedEnd->format('M j, Y'));
$overviewWeeks = [];
$weekCursor = $selectedStart->modify('monday this week');
while ($weekCursor <= $selectedEnd) {
    $weekEnd = $weekCursor->modify('+6 days');
    $periodStart = $weekCursor < $selectedStart ? $selectedStart : $weekCursor;
    $periodEnd = $weekEnd > $selectedEnd ? $selectedEnd : $weekEnd;
    $overviewWeeks[$weekCursor->format('Y-m-d')] = [
        'label' => $periodStart->format('M j') === $periodEnd->format('M j')
            ? $periodStart->format('M j')
            : $periodStart->format('M j') . '–' . $periodEnd->format('j'),
        'utilization_sum' => 0,
        'fuel_liters' => 0,
        'days_reported' => 0,
        'sample_days' => 0,
        'utilization' => null,
    ];
    $weekCursor = $weekCursor->modify('+7 days');
}
$vehicleStats = $db->query(
    'SELECT status, COUNT(*) AS count FROM vehicles GROUP BY status'
)->fetchAll(PDO::FETCH_ASSOC);
$unassignedVehicleCount = 0;
if ($activeView === 'fleet') {
    // Unassigned is a driver relationship, independent from the vehicle's availability status.
    $unassignedVehicleCount = (int) $db->query(
        "SELECT COUNT(*) FROM vehicles WHERE driver_name IS NULL OR trim(driver_name) = ''"
    )->fetchColumn();
}

// This manager view uses operational data only; financial and admin records are never queried here.
$vehicles = $db->query(
    'SELECT vehicles.vehicle_code, vehicles.make, vehicles.vehicle_type, vehicles.status,
            vehicles.driver_name, vehicles.driver_initials, vehicles.driver_color,
            vehicles.location, vehicles.assignment, vehicles.last_service, vehicles.fuel_level,
            vehicles.fuel_level_updated_at,
            trips.eta, trips.delay_minutes
     FROM vehicles
     LEFT JOIN trips ON trips.vehicle_code = vehicles.vehicle_code AND trips.status = \'active\'
     ORDER BY CASE vehicles.status
         WHEN \'active\' THEN 1
         WHEN \'idle\' THEN 2
         WHEN \'maintenance\' THEN 3
         ELSE 4
     END, vehicles.vehicle_code'
)->fetchAll(PDO::FETCH_ASSOC);

$trips = $db->query(
    'SELECT trips.id, trips.origin, trips.destination, trips.delay_minutes, trips.eta,
            vehicles.vehicle_code, trips.driver_name
     FROM trips
     JOIN vehicles ON vehicles.vehicle_code = trips.vehicle_code
     WHERE trips.status = \'active\'
     ORDER BY trips.eta'
)->fetchAll(PDO::FETCH_ASSOC);

$allTrips = $db->query(
    'SELECT id, vehicle_code, driver_name, origin, destination, status, delay_minutes, eta, trip_date
     FROM trips
     ORDER BY CASE status WHEN \'active\' THEN 0 WHEN \'upcoming\' THEN 1 ELSE 2 END, eta'
)->fetchAll(PDO::FETCH_ASSOC);

$rangeTripsStatement = $db->prepare(
    'SELECT id, status, delay_minutes
     FROM trips
     WHERE trip_date BETWEEN :range_start AND :range_end'
);
$rangeTripsStatement->execute(['range_start' => $selectedStartDate, 'range_end' => $selectedEndDate]);
$rangeTrips = $rangeTripsStatement->fetchAll(PDO::FETCH_ASSOC);
$rangeTripCounts = ['completed' => 0, 'in_progress' => 0, 'delayed' => 0, 'upcoming' => 0];
foreach ($rangeTrips as $rangeTrip) {
    if ($rangeTrip['status'] === 'completed') {
        $rangeTripCounts['completed']++;
    } elseif ($rangeTrip['status'] === 'upcoming') {
        $rangeTripCounts['upcoming']++;
    } elseif ((int) $rangeTrip['delay_minutes'] > 0) {
        $rangeTripCounts['delayed']++;
    } else {
        $rangeTripCounts['in_progress']++;
    }
}
$rangeActualTripCount = $rangeTripCounts['completed']
    + $rangeTripCounts['in_progress']
    + $rangeTripCounts['delayed'];
$rangeTripCompletionRate = $rangeActualTripCount > 0
    ? (int) round($rangeTripCounts['completed'] / $rangeActualTripCount * 100)
    : null;

$alerts = $db->query(
    'SELECT title, detail, severity, time_label
     FROM alerts
     WHERE is_open = 1
     ORDER BY CASE severity WHEN \'urgent\' THEN 0 ELSE 1 END, id'
)->fetchAll(PDO::FETCH_ASSOC);

$maintenanceItems = $db->query(
    'SELECT id, vehicle_code, task, depot, department, due_date, state
     FROM maintenance
     WHERE state IN (\'overdue\', \'due soon\', \'requested\')
     ORDER BY CASE state WHEN \'overdue\' THEN 0 WHEN \'due soon\' THEN 1 ELSE 2 END, due_date'
)->fetchAll(PDO::FETCH_ASSOC);

$allMaintenanceItems = $db->query(
    'SELECT id, vehicle_code, task, depot, department, due_date, state
     FROM maintenance
     ORDER BY CASE state WHEN \'overdue\' THEN 0 WHEN \'due soon\' THEN 1 WHEN \'requested\' THEN 2 ELSE 3 END, due_date'
)->fetchAll(PDO::FETCH_ASSOC);

// Persist overdue issue reports as Delayed so their section and saved status stay aligned.
if ($activeView === 'maintenance') {
    $db->exec(
        "UPDATE issue_reports SET status = 'delayed'
         WHERE status = 'in_progress' AND due_date IS NOT NULL AND due_date < date('now')"
    );
    $issueReports = $db->query(
        'SELECT id, vehicle_or_trip, description, created_at, status, issue_type, priority,
                due_date, maintenance_notes, is_sample
         FROM issue_reports
         ORDER BY created_at DESC, id DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $issueReports = [];
}
$newIssues = array_values(array_filter($issueReports, static fn (array $issue): bool => $issue['status'] === 'awaiting_review'));
$activeIssues = array_values(array_filter($issueReports, static fn (array $issue): bool => in_array($issue['status'], ['in_progress', 'delayed'], true)));
$completedIssues = array_values(array_filter($issueReports, static fn (array $issue): bool => $issue['status'] === 'completed'));

$drivers = $db->query(
    'SELECT drivers.id, drivers.name, drivers.on_duty, vehicles.vehicle_code, vehicles.assignment
     FROM drivers
     LEFT JOIN vehicles ON vehicles.driver_name = drivers.name
     ORDER BY drivers.on_duty DESC, drivers.name'
)->fetchAll(PDO::FETCH_ASSOC);

$overviewDailyMetricsStatement = $db->prepare(
    'SELECT metric_date, utilization, fuel_liters
     FROM daily_metrics
     WHERE metric_date BETWEEN :range_start AND :range_end
     ORDER BY metric_date'
);
$overviewDailyMetricsStatement->execute(['range_start' => $selectedStartDate, 'range_end' => $selectedEndDate]);
$overviewDailyMetrics = $overviewDailyMetricsStatement->fetchAll(PDO::FETCH_ASSOC);
$overviewMetricsByDate = [];
foreach ($overviewDailyMetrics as $metric) {
    $overviewMetricsByDate[$metric['metric_date']] = $metric;
}
$overviewMetricsIncludeSample = false;
$sampleDailyMetrics = [
    ['utilization' => 62, 'fuel_liters' => 412],
    ['utilization' => 68, 'fuel_liters' => 438],
    ['utilization' => 65, 'fuel_liters' => 401],
    ['utilization' => 74, 'fuel_liters' => 465],
    ['utilization' => 71, 'fuel_liters' => 449],
    ['utilization' => 79, 'fuel_liters' => 482],
    ['utilization' => 76, 'fuel_liters' => 458],
];
foreach ($overviewWeeks as $weekKey => &$week) {
    $periodStart = new DateTimeImmutable($weekKey, $utc);
    $periodEnd = $periodStart->modify('+6 days');
    if ($periodStart < $selectedStart) {
        $periodStart = $selectedStart;
    }
    if ($periodEnd > $selectedEnd) {
        $periodEnd = $selectedEnd;
    }
    for ($date = $periodStart; $date <= $periodEnd; $date = $date->modify('+1 day')) {
        $dateKey = $date->format('Y-m-d');
        if (isset($overviewMetricsByDate[$dateKey])) {
            $week['utilization_sum'] += (int) $overviewMetricsByDate[$dateKey]['utilization'];
            $week['fuel_liters'] += (int) $overviewMetricsByDate[$dateKey]['fuel_liters'];
            $week['days_reported']++;
        } else {
            // Fill unreported days only in the display aggregates; sample values never alter SQLite records.
            $sample = $sampleDailyMetrics[((int) $date->format('z')) % count($sampleDailyMetrics)];
            $week['utilization_sum'] += $sample['utilization'];
            $week['fuel_liters'] += $sample['fuel_liters'];
            $week['sample_days']++;
            $overviewMetricsIncludeSample = true;
        }
    }
    $week['utilization'] = (int) round(
        $week['utilization_sum'] / ($week['days_reported'] + $week['sample_days'])
    );
}
unset($week);

$rangeMaintenanceStatement = $db->prepare(
    'SELECT department, COUNT(*) AS issue_count
     FROM maintenance
     WHERE state != \'complete\' AND due_date BETWEEN :range_start AND :range_end
     GROUP BY department
     ORDER BY issue_count DESC, department'
);
$rangeMaintenanceStatement->execute(['range_start' => $selectedStartDate, 'range_end' => $selectedEndDate]);
$rangeMaintenanceByDepartment = $rangeMaintenanceStatement->fetchAll(PDO::FETCH_ASSOC);
$undatedTripCount = (int) $db->query('SELECT COUNT(*) FROM trips WHERE trip_date IS NULL')->fetchColumn();

$recentActivity = $db->query(
    'SELECT category, message, occurred_at
     FROM activity_log
     ORDER BY occurred_at DESC
     LIMIT 5'
)->fetchAll(PDO::FETCH_ASSOC);
if ($activeView === 'overview') {
    $activityStatement = $db->prepare(
        'SELECT category, message, occurred_at
         FROM activity_log
         WHERE date(occurred_at) BETWEEN :range_start AND :range_end
         ORDER BY occurred_at DESC
         LIMIT 5'
    );
    $activityStatement->execute(['range_start' => $selectedStartDate, 'range_end' => $selectedEndDate]);
    $recentActivity = $activityStatement->fetchAll(PDO::FETCH_ASSOC);
    if ($recentActivity === []) {
        $recentActivity[] = [
            'category' => 'fleet',
            'message' => 'Sample activity · No recorded updates during this date range',
            'occurred_at' => $selectedEndDate . ' 12:00:00',
        ];
    }
}

$driverCounts = $db->query(
    'SELECT COUNT(*) AS total, SUM(on_duty) AS on_duty FROM drivers'
)->fetch(PDO::FETCH_ASSOC);
$onDutyDrivers = (int) $driverCounts['on_duty'];
$totalDrivers = (int) $driverCounts['total'];
$offDutyDrivers = $totalDrivers - $onDutyDrivers;
$unassignedDrivers = count(array_filter($drivers, static fn (array $driver): bool => empty($driver['vehicle_code'])));
$pendingMaintenance = count($maintenanceItems);

require __DIR__ . '/dashboard.php';

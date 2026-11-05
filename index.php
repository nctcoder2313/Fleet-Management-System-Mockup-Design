<?php
declare(strict_types=1);

$db = require __DIR__ . '/database.php';

$requestedView = $_GET['view'] ?? 'overview';
$validViews = ['overview', 'fleet', 'trips', 'drivers', 'maintenance', 'reports', 'settings'];
$activeView = is_string($requestedView) && in_array($requestedView, $validViews, true)
    ? $requestedView
    : 'overview';

$utc = new DateTimeZone('UTC');
$weekStart = new DateTimeImmutable('now', $utc);
$weekStart = $weekStart->modify('monday this week')->setTime(0, 0);
$weekEnd = $weekStart->modify('+6 days');
$weekStartDate = $weekStart->format('Y-m-d');
$weekEndDate = $weekEnd->format('Y-m-d');
$weekRangeLabel = $weekStart->format('Y') === $weekEnd->format('Y')
    ? ($weekStart->format('m') === $weekEnd->format('m')
        ? $weekStart->format('M j') . '–' . $weekEnd->format('j, Y')
        : $weekStart->format('M j') . '–' . $weekEnd->format('M j, Y'))
    : $weekStart->format('M j, Y') . '–' . $weekEnd->format('M j, Y');
$monthStart = (new DateTimeImmutable('first day of this month', $utc))->setTime(0, 0);
$monthEnd = $monthStart->modify('last day of this month');
$monthStartDate = $monthStart->format('Y-m-d');
$monthEndDate = $monthEnd->format('Y-m-d');
$monthLabel = $monthStart->format('F Y');
$selectedStart = $monthStart;
$selectedEnd = new DateTimeImmutable('today', $utc);
// Only Overview consumes the selected UTC dates; invalid query values fall back to this month-to-date.
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
$monthlyWeeks = [];
$weekCursor = $monthStart->modify('monday this week');
while ($weekCursor <= $monthEnd) {
    $weekEnd = $weekCursor->modify('+6 days');
    $periodStart = $weekCursor < $monthStart ? $monthStart : $weekCursor;
    $periodEnd = $weekEnd > $monthEnd ? $monthEnd : $weekEnd;
    $weekKey = $weekCursor->format('Y-m-d');
    $monthlyWeeks[$weekKey] = [
        'label' => $periodStart->format('M j') === $periodEnd->format('M j')
            ? $periodStart->format('M j')
            : $periodStart->format('M j') . '–' . $periodEnd->format('j'),
        'utilization_sum' => 0,
        'fuel_liters' => 0,
        'days_reported' => 0,
        'utilization' => null,
    ];
    $weekCursor = $weekCursor->modify('+7 days');
}

$vehicleStats = $db->query(
    'SELECT status, COUNT(*) AS count FROM vehicles GROUP BY status'
)->fetchAll(PDO::FETCH_ASSOC);

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

$weeklyTripsStatement = $db->prepare(
    'SELECT id, status, delay_minutes
     FROM trips
     WHERE trip_date BETWEEN :week_start AND :week_end'
);
$weeklyTripsStatement->execute(['week_start' => $weekStartDate, 'week_end' => $weekEndDate]);
$weeklyTrips = $weeklyTripsStatement->fetchAll(PDO::FETCH_ASSOC);

$weeklyTripCounts = [
    'completed' => 0,
    'in_progress' => 0,
    'delayed' => 0,
    'upcoming' => 0,
];
foreach ($weeklyTrips as $weeklyTrip) {
    if ($weeklyTrip['status'] === 'completed') {
        $weeklyTripCounts['completed']++;
    } elseif ($weeklyTrip['status'] === 'upcoming') {
        $weeklyTripCounts['upcoming']++;
    } elseif ((int) $weeklyTrip['delay_minutes'] > 0) {
        $weeklyTripCounts['delayed']++;
    } else {
        $weeklyTripCounts['in_progress']++;
    }
}
$weeklyActualTripCount = $weeklyTripCounts['completed']
    + $weeklyTripCounts['in_progress']
    + $weeklyTripCounts['delayed'];
$weeklyTripCompletionRate = $weeklyActualTripCount > 0
    ? (int) round($weeklyTripCounts['completed'] / $weeklyActualTripCount * 100)
    : null;

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

$drivers = $db->query(
    'SELECT drivers.id, drivers.name, drivers.on_duty, vehicles.vehicle_code, vehicles.assignment
     FROM drivers
     LEFT JOIN vehicles ON vehicles.driver_name = drivers.name
     ORDER BY drivers.on_duty DESC, drivers.name'
)->fetchAll(PDO::FETCH_ASSOC);

$dailyMetricsStatement = $db->prepare(
    'SELECT metric_date, utilization, completion_rate, fuel_liters
     FROM daily_metrics
     WHERE metric_date BETWEEN :month_start AND :month_end
     ORDER BY metric_date'
);
$dailyMetricsStatement->execute(['month_start' => $monthStartDate, 'month_end' => $monthEndDate]);
$dailyMetrics = $dailyMetricsStatement->fetchAll(PDO::FETCH_ASSOC);
foreach ($dailyMetrics as $metric) {
    $metricDate = new DateTimeImmutable($metric['metric_date'], $utc);
    $metricWeekKey = $metricDate->modify('monday this week')->format('Y-m-d');
    if (isset($monthlyWeeks[$metricWeekKey])) {
        $monthlyWeeks[$metricWeekKey]['utilization_sum'] += (int) $metric['utilization'];
        $monthlyWeeks[$metricWeekKey]['fuel_liters'] += (int) $metric['fuel_liters'];
        $monthlyWeeks[$metricWeekKey]['days_reported']++;
    }
}
$monthlyMetricsAreSample = $dailyMetrics === [];
$monthlyMetricsIncludeSample = $monthlyMetricsAreSample;
$sampleWeeks = [
    ['utilization' => 62, 'fuel_liters' => 2100],
    ['utilization' => 68, 'fuel_liters' => 2350],
    ['utilization' => 74, 'fuel_liters' => 2210],
    ['utilization' => 79, 'fuel_liters' => 2680],
    ['utilization' => 76, 'fuel_liters' => 2460],
    ['utilization' => 72, 'fuel_liters' => 2290],
];
if ($monthlyMetricsAreSample) {
    $weekIndex = 0;
    foreach ($monthlyWeeks as &$week) {
        $sample = $sampleWeeks[$weekIndex % count($sampleWeeks)];
        $week['utilization'] = $sample['utilization'];
        $week['fuel_liters'] = $sample['fuel_liters'];
        $weekIndex++;
    }
    unset($week);
} else {
    $weekIndex = 0;
    foreach ($monthlyWeeks as &$week) {
        if ($week['days_reported'] > 0) {
            $week['utilization'] = (int) round($week['utilization_sum'] / $week['days_reported']);
        } else {
            $sample = $sampleWeeks[$weekIndex % count($sampleWeeks)];
            $week['utilization'] = $sample['utilization'];
            $week['fuel_liters'] = $sample['fuel_liters'];
            $monthlyMetricsIncludeSample = true;
        }
        $weekIndex++;
    }
    unset($week);
}

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

$maintenanceStatement = $db->prepare(
    'SELECT department, COUNT(*) AS issue_count
     FROM maintenance
     WHERE state != \'complete\' AND due_date BETWEEN :week_start AND :week_end
     GROUP BY department
     ORDER BY issue_count DESC, department'
);
$maintenanceStatement->execute(['week_start' => $weekStartDate, 'week_end' => $weekEndDate]);
$maintenanceByDepartment = $maintenanceStatement->fetchAll(PDO::FETCH_ASSOC);
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
$pendingMaintenance = count($maintenanceItems);

require __DIR__ . '/dashboard.php';

<?php
declare(strict_types=1);

$db = require __DIR__ . '/database.php';

$vehicleStats = $db->query(
    'SELECT status, COUNT(*) AS count FROM vehicles GROUP BY status'
)->fetchAll(PDO::FETCH_ASSOC);

// This manager view uses operational data only; financial and admin records are never queried here.
$vehicles = $db->query(
    'SELECT vehicles.vehicle_code, vehicles.make, vehicles.vehicle_type, vehicles.status,
            vehicles.driver_name, vehicles.driver_initials, vehicles.driver_color,
            vehicles.location, vehicles.assignment, vehicles.last_service, vehicles.fuel_level,
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

$drivers = $db->query(
    'SELECT drivers.name, drivers.on_duty, vehicles.vehicle_code, vehicles.assignment
     FROM drivers
     LEFT JOIN vehicles ON vehicles.driver_name = drivers.name
     ORDER BY drivers.on_duty DESC, drivers.name'
)->fetchAll(PDO::FETCH_ASSOC);

$dailyMetrics = $db->query(
    'SELECT metric_date, utilization, completion_rate, fuel_liters
     FROM daily_metrics
     ORDER BY metric_date'
)->fetchAll(PDO::FETCH_ASSOC);

$maintenanceByDepartment = $db->query(
    'SELECT department, COUNT(*) AS issue_count
     FROM maintenance
     WHERE state != \'complete\'
     GROUP BY department
     ORDER BY issue_count DESC, department'
)->fetchAll(PDO::FETCH_ASSOC);

$recentActivity = $db->query(
    'SELECT category, message, occurred_at
     FROM activity_log
     ORDER BY occurred_at DESC
     LIMIT 5'
)->fetchAll(PDO::FETCH_ASSOC);

$driverCounts = $db->query(
    'SELECT COUNT(*) AS total, SUM(on_duty) AS on_duty FROM drivers'
)->fetch(PDO::FETCH_ASSOC);
$onDutyDrivers = (int) $driverCounts['on_duty'];
$totalDrivers = (int) $driverCounts['total'];
$pendingMaintenance = count($maintenanceItems);

require __DIR__ . '/dashboard.php';

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS vehicles (
    vehicle_code TEXT PRIMARY KEY,
    make TEXT NOT NULL,
    vehicle_type TEXT NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('active', 'idle', 'maintenance', 'offline')),
    driver_name TEXT,
    driver_initials TEXT,
    driver_color TEXT NOT NULL DEFAULT 'teal' CHECK (driver_color IN ('orange', 'purple', 'teal')),
    location TEXT NOT NULL,
    assignment TEXT NOT NULL DEFAULT '',
    last_service TEXT NOT NULL DEFAULT '2026-10-01',
    fuel_level INTEGER DEFAULT NULL CHECK (fuel_level IS NULL OR fuel_level BETWEEN 0 AND 100),
    fuel_level_updated_at TEXT
);

CREATE TABLE IF NOT EXISTS drivers (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL UNIQUE,
    on_duty INTEGER NOT NULL DEFAULT 0 CHECK (on_duty IN (0, 1))
);

CREATE TABLE IF NOT EXISTS trips (
    id TEXT PRIMARY KEY,
    vehicle_code TEXT NOT NULL REFERENCES vehicles(vehicle_code),
    driver_name TEXT NOT NULL REFERENCES drivers(name),
    origin TEXT NOT NULL,
    destination TEXT NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('active', 'completed', 'upcoming')),
    delay_minutes INTEGER NOT NULL DEFAULT 0 CHECK (delay_minutes >= 0),
    eta TEXT NOT NULL,
    trip_date TEXT
);

CREATE TABLE IF NOT EXISTS alerts (
    id INTEGER PRIMARY KEY,
    title TEXT NOT NULL,
    detail TEXT NOT NULL,
    severity TEXT NOT NULL CHECK (severity IN ('urgent', 'warning')),
    time_label TEXT NOT NULL,
    is_open INTEGER NOT NULL DEFAULT 1 CHECK (is_open IN (0, 1))
);

CREATE TABLE IF NOT EXISTS maintenance (
    id INTEGER PRIMARY KEY,
    vehicle_code TEXT NOT NULL REFERENCES vehicles(vehicle_code),
    task TEXT NOT NULL,
    depot TEXT NOT NULL,
    department TEXT NOT NULL DEFAULT 'Operations',
    due_date TEXT NOT NULL,
    state TEXT NOT NULL CHECK (state IN ('overdue', 'due soon', 'requested', 'complete'))
);

CREATE TABLE IF NOT EXISTS daily_metrics (
    metric_date TEXT PRIMARY KEY,
    utilization INTEGER NOT NULL CHECK (utilization BETWEEN 0 AND 100),
    completion_rate INTEGER NOT NULL CHECK (completion_rate BETWEEN 0 AND 100),
    fuel_liters INTEGER NOT NULL CHECK (fuel_liters >= 0)
);

CREATE TABLE IF NOT EXISTS activity_log (
    id INTEGER PRIMARY KEY,
    category TEXT NOT NULL,
    message TEXT NOT NULL,
    occurred_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS issue_reports (
    id INTEGER PRIMARY KEY,
    vehicle_or_trip TEXT NOT NULL,
    description TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO vehicles
    (vehicle_code, make, vehicle_type, status, driver_name, driver_initials, driver_color, location, assignment, last_service, fuel_level, fuel_level_updated_at)
VALUES
    ('VH-204', 'Ford Transit', 'Van', 'active', 'Marcus Reed', 'MR', 'orange', 'Portland, OR', 'Salem · Run #2841', date('now', '-18 days'), 82, datetime('now')),
    ('VH-118', 'Freightliner M2', 'Truck', 'active', 'Priya Patel', 'PP', 'purple', 'Vancouver, WA', 'Cold-chain delivery', date('now', '-9 days'), 64, datetime('now')),
    ('VH-167', 'Mercedes Sprinter', 'Van', 'active', 'Alex Chen', 'AC', 'teal', 'Eugene, OR', 'Portland · Run #2836', date('now', '-25 days'), 47, datetime('now')),
    ('VH-092', 'Toyota HiAce', 'Van', 'idle', 'Elena Garcia', 'EG', 'teal', 'Depot 02', '', date('now', '-12 days'), 91, datetime('now')),
    ('VH-056', 'Mercedes Sprinter', 'Van', 'idle', NULL, NULL, 'teal', 'North hub', '', date('now', '-31 days'), 73, datetime('now')),
    ('VH-331', 'Isuzu NPR', 'Truck', 'maintenance', NULL, NULL, 'teal', 'Central depot', 'Brake inspection', date('now', '-72 days'), 28, datetime('now')),
    ('VH-219', 'Ford Transit', 'Van', 'maintenance', NULL, NULL, 'teal', 'Central depot', 'Oil change', date('now', '-58 days'), 36, datetime('now')),
    ('VH-084', 'Toyota HiAce', 'Van', 'idle', NULL, NULL, 'teal', 'North hub', '', date('now', '-14 days'), 68, datetime('now')),
    ('VH-307', 'Freightliner M2', 'Truck', 'idle', NULL, NULL, 'teal', 'East depot', '', date('now', '-21 days'), 55, datetime('now')),
    ('VH-402', 'Isuzu NPR', 'Truck', 'offline', NULL, NULL, 'teal', 'Last seen: Portland, OR', '', date('now', '-45 days'), 19, datetime('now'));

INSERT OR IGNORE INTO drivers (id, name, on_duty) VALUES
    (1, 'Marcus Reed', 1),
    (2, 'Priya Patel', 1),
    (3, 'Alex Chen', 1),
    (4, 'Elena Garcia', 1),
    (5, 'Sam Wilson', 0),
    (6, 'Taylor Brooks', 0);

INSERT OR IGNORE INTO trips (id, vehicle_code, driver_name, origin, destination, status, delay_minutes, eta, trip_date) VALUES
    ('TRIP-2841', 'VH-204', 'Marcus Reed', 'Portland, OR', 'Salem, OR', 'active', 0, '10:45 AM', date('now')),
    ('TRIP-2836', 'VH-167', 'Alex Chen', 'Eugene, OR', 'Portland, OR', 'active', 18, '11:20 AM', date('now')),
    ('TRIP-2832', 'VH-118', 'Priya Patel', 'Hillsboro, OR', 'Vancouver, WA', 'active', 0, '11:05 AM', date('now'));

INSERT OR IGNORE INTO alerts (id, title, detail, severity, time_label, is_open) VALUES
    (1, 'Vehicle offline', 'VH-402 · Last seen 24 min ago', 'urgent', '9:32 AM', 1),
    (2, 'Trip running late', 'TRIP-2836 · Eugene → Portland', 'warning', '9:18 AM', 1),
    (3, 'Maintenance overdue', 'VH-219 · Oil change due 2 days ago', 'warning', '8:50 AM', 1);

INSERT OR IGNORE INTO maintenance (id, vehicle_code, task, depot, department, due_date, state) VALUES
    (1, 'VH-219', 'Oil change', 'Central depot', 'Regional Delivery', date('now', '-2 days'), 'overdue'),
    (2, 'VH-084', 'Tire rotation', 'North hub', 'Local Delivery', date('now', '+2 days'), 'due soon'),
    (3, 'VH-307', 'Annual inspection', 'East depot', 'Long-haul', date('now', '+4 days'), 'due soon');

-- Illustrative operational history used by the manager performance charts.
INSERT OR IGNORE INTO daily_metrics (metric_date, utilization, completion_rate, fuel_liters) VALUES
    (date('now', '-6 days'), 62, 88, 412),
    (date('now', '-5 days'), 68, 91, 438),
    (date('now', '-4 days'), 65, 86, 401),
    (date('now', '-3 days'), 74, 94, 465),
    (date('now', '-2 days'), 71, 92, 449),
    (date('now', '-1 day'), 79, 96, 482),
    (date('now'), 76, 93, 458);

INSERT OR IGNORE INTO activity_log (id, category, message, occurred_at) VALUES
    (1, 'trip', 'TRIP-2841 departed Portland for Salem', datetime('now', '-12 minutes')),
    (2, 'driver', 'Priya Patel started the cold-chain route', datetime('now', '-28 minutes')),
    (3, 'maintenance', 'VH-219 oil change flagged as overdue', datetime('now', '-46 minutes')),
    (4, 'fleet', 'VH-092 checked in at Depot 02', datetime('now', '-1 hour')),
    (5, 'trip', 'TRIP-2836 reported an 18-minute delay', datetime('now', '-2 hours'));

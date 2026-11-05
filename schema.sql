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
    fuel_level INTEGER NOT NULL DEFAULT 75 CHECK (fuel_level BETWEEN 0 AND 100)
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
    eta TEXT NOT NULL
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

CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY,
    expense_date TEXT NOT NULL,
    vehicle_code TEXT NOT NULL REFERENCES vehicles(vehicle_code),
    driver_name TEXT,
    category TEXT NOT NULL CHECK (category IN ('Fuel', 'Maintenance', 'Repairs', 'Parts', 'Registration', 'Inspection', 'Insurance', 'Tolls', 'Parking', 'Cleaning', 'Permits', 'Fines', 'Other')),
    description TEXT NOT NULL,
    amount_cents INTEGER NOT NULL CHECK (amount_cents > 0),
    status TEXT NOT NULL DEFAULT 'Paid' CHECK (status IN ('Paid', 'Pending'))
);

CREATE TABLE IF NOT EXISTS cash_advances (
    id INTEGER PRIMARY KEY,
    advance_date TEXT NOT NULL,
    driver_name TEXT NOT NULL REFERENCES drivers(name),
    vehicle_code TEXT NOT NULL REFERENCES vehicles(vehicle_code),
    purpose TEXT NOT NULL DEFAULT 'Trip cash advance',
    notes TEXT NOT NULL DEFAULT '',
    amount_cents INTEGER NOT NULL CHECK (amount_cents > 0),
    liquidated_cents INTEGER NOT NULL DEFAULT 0 CHECK (liquidated_cents >= 0 AND liquidated_cents <= amount_cents),
    liquidated_at TEXT,
    receipt_reference TEXT NOT NULL DEFAULT '',
    liquidation_notes TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS advance_liquidations (
    id INTEGER PRIMARY KEY,
    advance_id INTEGER NOT NULL REFERENCES cash_advances(id) ON DELETE CASCADE,
    liquidated_at TEXT NOT NULL,
    liquidated_by TEXT NOT NULL DEFAULT 'Not recorded',
    amount_cents INTEGER NOT NULL CHECK (amount_cents > 0),
    receipt_reference TEXT NOT NULL DEFAULT '',
    notes TEXT NOT NULL DEFAULT ''
);

INSERT OR IGNORE INTO vehicles
    (vehicle_code, make, vehicle_type, status, driver_name, driver_initials, driver_color, location, assignment, last_service, fuel_level)
VALUES
    ('VH-204', 'Ford Transit', 'Van', 'active', 'Marcus Reed', 'MR', 'orange', 'Portland, OR', 'Salem · Run #2841', date('now', '-18 days'), 82),
    ('VH-118', 'Freightliner M2', 'Truck', 'active', 'Priya Patel', 'PP', 'purple', 'Vancouver, WA', 'Cold-chain delivery', date('now', '-9 days'), 64),
    ('VH-167', 'Mercedes Sprinter', 'Van', 'active', 'Alex Chen', 'AC', 'teal', 'Eugene, OR', 'Portland · Run #2836', date('now', '-25 days'), 47),
    ('VH-092', 'Toyota HiAce', 'Van', 'idle', 'Elena Garcia', 'EG', 'teal', 'Depot 02', '', date('now', '-12 days'), 91),
    ('VH-056', 'Mercedes Sprinter', 'Van', 'idle', NULL, NULL, 'teal', 'North hub', '', date('now', '-31 days'), 73),
    ('VH-331', 'Isuzu NPR', 'Truck', 'maintenance', NULL, NULL, 'teal', 'Central depot', 'Brake inspection', date('now', '-72 days'), 28),
    ('VH-219', 'Ford Transit', 'Van', 'maintenance', NULL, NULL, 'teal', 'Central depot', 'Oil change', date('now', '-58 days'), 36),
    ('VH-084', 'Toyota HiAce', 'Van', 'idle', NULL, NULL, 'teal', 'North hub', '', date('now', '-14 days'), 68),
    ('VH-307', 'Freightliner M2', 'Truck', 'idle', NULL, NULL, 'teal', 'East depot', '', date('now', '-21 days'), 55),
    ('VH-402', 'Isuzu NPR', 'Truck', 'offline', NULL, NULL, 'teal', 'Last seen: Portland, OR', '', date('now', '-45 days'), 19);

INSERT OR IGNORE INTO drivers (id, name, on_duty) VALUES
    (1, 'Marcus Reed', 1),
    (2, 'Priya Patel', 1),
    (3, 'Alex Chen', 1),
    (4, 'Elena Garcia', 1),
    (5, 'Sam Wilson', 0),
    (6, 'Taylor Brooks', 0);

INSERT OR IGNORE INTO trips (id, vehicle_code, driver_name, origin, destination, status, delay_minutes, eta) VALUES
    ('TRIP-2841', 'VH-204', 'Marcus Reed', 'Portland, OR', 'Salem, OR', 'active', 0, '10:45 AM'),
    ('TRIP-2836', 'VH-167', 'Alex Chen', 'Eugene, OR', 'Portland, OR', 'active', 18, '11:20 AM'),
    ('TRIP-2832', 'VH-118', 'Priya Patel', 'Hillsboro, OR', 'Vancouver, WA', 'active', 0, '11:05 AM');

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

INSERT OR IGNORE INTO expenses (id, expense_date, vehicle_code, category, description, amount_cents, status) VALUES
    (1, date('now', 'start of month', '-2 months', '+5 days'), 'VH-204', 'Fuel', 'Fuel card refill', 18450, 'Paid'),
    (2, date('now', 'start of month', '-2 months', '+12 days'), 'VH-331', 'Maintenance', 'Brake service', 42800, 'Paid'),
    (3, date('now', 'start of month', '-1 month', '+4 days'), 'VH-118', 'Fuel', 'Diesel refill', 22175, 'Paid'),
    (4, date('now', 'start of month', '-1 month', '+10 days'), 'VH-167', 'Tolls', 'Route tolls', 3650, 'Paid'),
    (5, date('now', 'start of month', '+1 day'), 'VH-204', 'Fuel', 'Fuel card refill', 19620, 'Paid'),
    (6, date('now', '-3 days'), 'VH-219', 'Parts', 'Replacement filters', 12750, 'Pending'),
    (7, date('now', '-2 days'), 'VH-118', 'Insurance', 'Monthly vehicle premium', 89500, 'Paid'),
    (8, date('now', '-1 day'), 'VH-056', 'Fuel', 'Fuel card refill', 17240, 'Pending'),
    (9, date('now', '-5 days'), 'VH-204', 'Repairs', 'Side mirror replacement', 13800, 'Paid'),
    (10, date('now', '-6 days'), 'VH-219', 'Maintenance', 'Preventive service', 24600, 'Pending'),
    (11, date('now', '-7 days'), 'VH-331', 'Parts', 'Brake pads and fittings', 18950, 'Paid'),
    (12, date('now', '-8 days'), 'VH-307', 'Registration', 'Vehicle registration renewal', 12400, 'Paid'),
    (13, date('now', '-9 days'), 'VH-084', 'Inspection', 'Annual roadworthiness inspection', 7600, 'Paid'),
    (14, date('now', '-10 days'), 'VH-056', 'Insurance', 'Fleet insurance installment', 89500, 'Paid'),
    (15, date('now', '-11 days'), 'VH-118', 'Tolls', 'Interstate toll charges', 4850, 'Paid'),
    (16, date('now', '-12 days'), 'VH-167', 'Parking', 'Delivery-zone parking', 1200, 'Paid'),
    (17, date('now', '-13 days'), 'VH-092', 'Cleaning', 'Cabin deep clean', 4200, 'Paid'),
    (18, date('now', '-14 days'), 'VH-204', 'Permits', 'City access permit', 3500, 'Pending'),
    (19, date('now', '-15 days'), 'VH-118', 'Fines', 'Parking citation', 8500, 'Pending'),
    (20, date('now', '-16 days'), 'VH-219', 'Fuel', 'Diesel refill', 20580, 'Paid'),
    (21, date('now', '-17 days'), 'VH-331', 'Repairs', 'Hydraulic line repair', 36200, 'Pending'),
    (22, date('now', '-18 days'), 'VH-402', 'Registration', 'Registration and plate fees', 11800, 'Paid'),
    (23, date('now', '-19 days'), 'VH-307', 'Other', 'Cargo straps', 2975, 'Paid'),
    (24, date('now', '-20 days'), 'VH-084', 'Fuel', 'Fuel card refill', 16840, 'Paid'),
    (25, date('now', '-21 days'), 'VH-056', 'Maintenance', 'Tire rotation', 9800, 'Paid'),
    (26, date('now', '-22 days'), 'VH-167', 'Inspection', 'Emissions test', 5400, 'Paid'),
    (27, date('now', '-23 days'), 'VH-092', 'Parking', 'Depot parking fees', 2200, 'Paid'),
    (28, date('now', '-24 days'), 'VH-118', 'Permits', 'Oversize load permit', 6900, 'Paid'),
    (29, date('now', '-25 days'), 'VH-204', 'Parts', 'Wiper blades and bulbs', 3650, 'Paid'),
    (30, date('now', '-26 days'), 'VH-307', 'Cleaning', 'Exterior wash', 2800, 'Paid');

INSERT OR IGNORE INTO cash_advances (id, advance_date, driver_name, vehicle_code, purpose, notes, amount_cents, liquidated_cents, liquidated_at, receipt_reference) VALUES
    (1, date('now', '-4 days'), 'Marcus Reed', 'VH-204', 'Fuel and route tolls', 'Salem delivery run', 15000, 15000, date('now', '-3 days'), 'RCPT-204-01'),
    (2, date('now', '-2 days'), 'Priya Patel', 'VH-118', 'Cold-chain trip expenses', 'Fuel and meal receipts pending', 20000, 8500, date('now', '-1 day'), 'RCPT-118-08'),
    (3, date('now', '-1 day'), 'Alex Chen', 'VH-167', 'Route expenses', 'Awaiting receipts', 12000, 0, NULL, ''),
    (4, date('now', '-3 days'), 'Elena Garcia', 'VH-092', 'Local delivery expenses', 'Partial receipts submitted', 8000, 3200, date('now', '-1 day'), 'RCPT-092-14'),
    (5, date('now', '-5 days'), 'Sam Wilson', 'VH-056', 'Fuel and parking', 'North hub transfer', 10000, 10000, date('now', '-4 days'), 'RCPT-056-05'),
    (6, date('now', '-6 days'), 'Taylor Brooks', 'VH-084', 'Trip petty cash', 'Receipts not yet submitted', 7500, 0, NULL, ''),
    (7, date('now', '-7 days'), 'Marcus Reed', 'VH-204', 'Emergency repair', 'Tire patch and roadside service', 18000, 11200, date('now', '-5 days'), 'RCPT-204-09'),
    (8, date('now', '-8 days'), 'Priya Patel', 'VH-118', 'Trip allowance', 'Two-day route allowance', 14000, 14000, date('now', '-6 days'), 'RCPT-118-11'),
    (9, date('now', '-9 days'), 'Alex Chen', 'VH-167', 'Fuel advance', 'Fuel receipt outstanding', 9000, 4500, date('now', '-7 days'), 'RCPT-167-03'),
    (10, date('now', '-10 days'), 'Elena Garcia', 'VH-092', 'Route tolls', 'Receipt photo requested', 6000, 0, NULL, ''),
    (11, date('now', '-12 days'), 'Sam Wilson', 'VH-056', 'Vehicle supplies', 'Cleaning and safety items', 5200, 5200, date('now', '-10 days'), 'RCPT-056-12'),
    (12, date('now', '-14 days'), 'Taylor Brooks', 'VH-084', 'Trip cash advance', 'Long-haul fuel and meals', 22000, 15000, date('now', '-11 days'), 'RCPT-084-07'),
    (13, date('now', '-16 days'), 'Marcus Reed', 'VH-204', 'Parking and tolls', 'Downtown delivery route', 4500, 4500, date('now', '-15 days'), 'RCPT-204-16'),
    (14, date('now', '-18 days'), 'Priya Patel', 'VH-118', 'Emergency supplies', 'Cold-chain packaging purchase', 11500, 0, NULL, ''),
    (15, date('now', '-20 days'), 'Alex Chen', 'VH-167', 'Route expenses', 'Receipts under review', 13500, 9100, date('now', '-18 days'), 'RCPT-167-20'),
    (16, date('now', '-22 days'), 'Elena Garcia', 'VH-092', 'Fuel and parking', 'Local route', 6800, 6800, date('now', '-21 days'), 'RCPT-092-22'),
    (17, date('now', '-24 days'), 'Sam Wilson', 'VH-056', 'Roadside assistance', 'Pending liquidation', 16000, 0, NULL, ''),
    (18, date('now', '-26 days'), 'Taylor Brooks', 'VH-084', 'Trip allowance', 'Receipt submitted', 12500, 12500, date('now', '-24 days'), 'RCPT-084-26');

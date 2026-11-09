<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

function validDate(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
        return false;
    }

    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Only POST requests are supported.']);
}

$submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($submittedToken) || !hash_equals(csrfToken(), $submittedToken)) {
    respond(403, ['error' => 'Your session expired. Refresh the page and try again.']);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$payload = stripos($contentType, 'multipart/form-data') !== false
    ? $_POST
    : json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    respond(400, ['error' => 'A valid JSON request body is required.']);
}

if (!in_array($payload['action'] ?? '', [
    'record-expense', 'update-expense', 'record-bill', 'record-bill-payment', 'update-bill',
    'record-advance', 'liquidate-advance', 'review-liquidation', 'report-issue',
    'request-maintenance', 'update-vehicle',
], true)) {
    respond(404, ['error' => 'That action is not available in the accounting workspace.']);
}

try {
    $db = require __DIR__ . '/database.php';
    $readUpload = static function (string $field): ?array {
        $upload = $_FILES[$field] ?? null;
        if ($upload === null || $upload['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024 || !is_uploaded_file($upload['tmp_name'])) {
            respond(422, ['error' => 'Attachments must be uploaded successfully and be no larger than 5 MB.']);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedTypes, true)) {
            respond(422, ['error' => 'Upload a PDF, JPEG, PNG, or WebP attachment.']);
        }
        $name = trim(str_replace(["\r", "\n", "\0"], '', basename((string) $upload['name'])));
        if ($name === '' || strlen($name) > 160) {
            respond(422, ['error' => 'Attachment filename must be 1 to 160 characters.']);
        }
        $data = file_get_contents($upload['tmp_name']);
        if ($data === false) {
            throw new RuntimeException('Unable to read the uploaded attachment.');
        }
        return ['name' => $name, 'mime' => $mime, 'data' => $data];
    };

    if (in_array(($payload['action'] ?? ''), ['record-expense', 'update-expense'], true)) {
        $expenseId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
        $expenseDate = (string) ($payload['expense_date'] ?? '');
        $vehicleCode = trim((string) ($payload['vehicle_code'] ?? ''));
        $category = (string) ($payload['category'] ?? '');
        $description = trim((string) ($payload['description'] ?? ''));
        $amount = filter_var($payload['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $status = (string) ($payload['status'] ?? 'Paid');
        $categories = ['Fuel', 'Maintenance', 'Repairs', 'Parts', 'Registration', 'Inspection', 'Insurance', 'Tolls', 'Parking', 'Cleaning', 'Permits', 'Fines', 'Other'];
        $recordedBy = trim((string) ($payload['recorded_by'] ?? 'Accounting team'));
        if (($payload['action'] === 'update-expense' && !$expenseId)
            || !validDate($expenseDate) || $vehicleCode === '' || !in_array($category, $categories, true)
            || $description === '' || strlen($description) > 160 || $amount === false || $amount <= 0
            || $amount > 10000000 || strlen($recordedBy) > 120 || !in_array($status, ['Paid', 'Pending'], true)) {
            respond(422, ['error' => 'Check the expense fields and provide valid values.']);
        }
        $amountCents = (int) round($amount * 100);
        if ($amountCents < 1) {
            respond(422, ['error' => 'Expense amount must be at least one cent.']);
        }
        $vehicleExists = $db->prepare('SELECT driver_name FROM vehicles WHERE vehicle_code = :vehicle_code');
        $vehicleExists->execute(['vehicle_code' => $vehicleCode]);
        $vehicle = $vehicleExists->fetch(PDO::FETCH_ASSOC);
        if (!$vehicle) {
            respond(422, ['error' => 'Choose an existing vehicle.']);
        }
        $attachment = $readUpload('receipt');
        if ($payload['action'] === 'update-expense') {
            $billLink = $db->prepare('SELECT source_bill_id FROM expenses WHERE id = :id');
            $billLink->execute(['id' => $expenseId]);
            $sourceBillId = $billLink->fetchColumn();
            if ($sourceBillId !== false && $sourceBillId !== null) {
                respond(422, ['error' => 'Edit this expense from Bills & Payments so its bill remains in sync.']);
            }
            $statement = $db->prepare(
                'UPDATE expenses SET expense_date = :expense_date, vehicle_code = :vehicle_code,
                    driver_name = :driver_name, category = :category, description = :description,
                    amount_cents = :amount_cents, status = :status, recorded_by = :recorded_by
                 WHERE id = :id'
            );
            $statement->execute([
                'expense_date' => $expenseDate, 'vehicle_code' => $vehicleCode, 'driver_name' => $vehicle['driver_name'],
                'category' => $category, 'description' => $description, 'amount_cents' => $amountCents,
                'status' => $status, 'recorded_by' => $recordedBy, 'id' => $expenseId,
            ]);
            $savedExpenseId = $expenseId;
        } else {
            $statement = $db->prepare(
                'INSERT INTO expenses (expense_date, vehicle_code, driver_name, category, description, amount_cents, status, recorded_by)
                 VALUES (:expense_date, :vehicle_code, :driver_name, :category, :description, :amount_cents, :status, :recorded_by)'
            );
            $statement->execute([
                'expense_date' => $expenseDate, 'vehicle_code' => $vehicleCode, 'driver_name' => $vehicle['driver_name'],
                'category' => $category, 'description' => $description, 'amount_cents' => $amountCents,
                'status' => $status, 'recorded_by' => $recordedBy,
            ]);
            $savedExpenseId = (int) $db->lastInsertId();
        }
        if ($attachment !== null) {
            $receiptStatement = $db->prepare(
                'INSERT INTO expense_receipts (expense_id, file_name, mime_type, file_data)
                 VALUES (:expense_id, :file_name, :mime_type, :file_data)
                 ON CONFLICT(expense_id) DO UPDATE SET file_name = excluded.file_name,
                    mime_type = excluded.mime_type, file_data = excluded.file_data'
            );
            $receiptStatement->bindValue(':expense_id', $savedExpenseId, PDO::PARAM_INT);
            $receiptStatement->bindValue(':file_name', $attachment['name']);
            $receiptStatement->bindValue(':mime_type', $attachment['mime']);
            $receiptStatement->bindValue(':file_data', $attachment['data'], PDO::PARAM_LOB);
            $receiptStatement->execute();
        }
        respond($payload['action'] === 'update-expense' ? 200 : 201, [
            'message' => $payload['action'] === 'update-expense' ? 'Expense updated.' : 'Expense recorded.',
        ]);
    }

    if (($payload['action'] ?? '') === 'record-bill') {
        $supplier = trim((string) ($payload['supplier'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $category = (string) ($payload['category'] ?? '');
        $vehicleCode = trim((string) ($payload['vehicle_code'] ?? ''));
        $billDate = (string) ($payload['bill_date'] ?? '');
        $dueDate = (string) ($payload['due_date'] ?? '');
        $amount = filter_var($payload['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $categories = ['Fuel', 'Maintenance', 'Repairs', 'Parts', 'Registration', 'Inspection', 'Insurance', 'Tolls', 'Parking', 'Cleaning', 'Permits', 'Fines', 'Other'];
        if ($supplier === '' || strlen($supplier) > 120 || $description === '' || strlen($description) > 160
            || !in_array($category, $categories, true) || !validDate($billDate) || !validDate($dueDate)
            || $vehicleCode === '' || $amount === false || $amount <= 0 || $amount > 10000000) {
            respond(422, ['error' => 'Check the bill fields and provide valid values.']);
        }
        $amountCents = (int) round($amount * 100);
        $vehicleExists = $db->prepare('SELECT driver_name FROM vehicles WHERE vehicle_code = :vehicle_code');
        $vehicleExists->execute(['vehicle_code' => $vehicleCode]);
        $vehicle = $vehicleExists->fetch(PDO::FETCH_ASSOC);
        if (!$vehicle) {
            respond(422, ['error' => 'Choose an existing vehicle.']);
        }
        $invoice = $readUpload('invoice');
        $db->beginTransaction();
        $expenseStatement = $db->prepare(
            'INSERT INTO expenses (expense_date, vehicle_code, driver_name, category, description, amount_cents, status, recorded_by)
             VALUES (:bill_date, :vehicle_code, :driver_name, :category, :description, :amount_cents, \'Pending\', :recorded_by)'
        );
        $expenseStatement->execute([
            'bill_date' => $billDate, 'vehicle_code' => $vehicleCode, 'driver_name' => $vehicle['driver_name'],
            'category' => $category, 'description' => $description, 'amount_cents' => $amountCents,
            'recorded_by' => trim((string) ($payload['recorded_by'] ?? 'Accounting team')) ?: 'Accounting team',
        ]);
        $expenseId = (int) $db->lastInsertId();
        $billStatement = $db->prepare(
            'INSERT INTO bills (supplier, description, category, vehicle_code, bill_date, due_date, amount_cents,
                expense_id, invoice_name, invoice_mime, invoice_data)
             VALUES (:supplier, :description, :category, :vehicle_code, :bill_date, :due_date, :amount_cents,
                :expense_id, :invoice_name, :invoice_mime, :invoice_data)'
        );
        $billStatement->bindValue(':supplier', $supplier);
        $billStatement->bindValue(':description', $description);
        $billStatement->bindValue(':category', $category);
        $billStatement->bindValue(':vehicle_code', $vehicleCode);
        $billStatement->bindValue(':bill_date', $billDate);
        $billStatement->bindValue(':due_date', $dueDate);
        $billStatement->bindValue(':amount_cents', $amountCents, PDO::PARAM_INT);
        $billStatement->bindValue(':expense_id', $expenseId, PDO::PARAM_INT);
        $billStatement->bindValue(':invoice_name', $invoice['name'] ?? null);
        $billStatement->bindValue(':invoice_mime', $invoice['mime'] ?? null);
        $billStatement->bindValue(':invoice_data', $invoice['data'] ?? null, $invoice === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $billStatement->execute();
        $billId = (int) $db->lastInsertId();
        $linkStatement = $db->prepare('UPDATE expenses SET source_bill_id = :bill_id WHERE id = :expense_id');
        $linkStatement->execute(['bill_id' => $billId, 'expense_id' => $expenseId]);
        $db->commit();
        respond(201, ['message' => 'Bill recorded. It has been added to expenses once; payments will only reduce its balance.']);
    }

    if (($payload['action'] ?? '') === 'update-bill') {
        $billId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
        $supplier = trim((string) ($payload['supplier'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $category = (string) ($payload['category'] ?? '');
        $vehicleCode = trim((string) ($payload['vehicle_code'] ?? ''));
        $billDate = (string) ($payload['bill_date'] ?? '');
        $dueDate = (string) ($payload['due_date'] ?? '');
        $amount = filter_var($payload['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $categories = ['Fuel', 'Maintenance', 'Repairs', 'Parts', 'Registration', 'Inspection', 'Insurance', 'Tolls', 'Parking', 'Cleaning', 'Permits', 'Fines', 'Other'];
        if (!$billId || $supplier === '' || strlen($supplier) > 120 || $description === '' || strlen($description) > 160
            || !in_array($category, $categories, true) || !validDate($billDate) || !validDate($dueDate)
            || $vehicleCode === '' || $amount === false || $amount <= 0 || $amount > 10000000) {
            respond(422, ['error' => 'Check the bill fields and provide valid values.']);
        }
        $amountCents = (int) round($amount * 100);
        $vehicleExists = $db->prepare('SELECT driver_name FROM vehicles WHERE vehicle_code = :vehicle_code');
        $vehicleExists->execute(['vehicle_code' => $vehicleCode]);
        $vehicle = $vehicleExists->fetch(PDO::FETCH_ASSOC);
        if (!$vehicle) {
            respond(422, ['error' => 'Choose an existing vehicle.']);
        }
        $invoice = $readUpload('invoice');
        $db->beginTransaction();
        $existingQuery = $db->prepare(
            'SELECT bills.expense_id, COALESCE(SUM(bill_payments.amount_cents), 0) AS paid_cents
             FROM bills LEFT JOIN bill_payments ON bill_payments.bill_id = bills.id
             WHERE bills.id = :id GROUP BY bills.id'
        );
        $existingQuery->execute(['id' => $billId]);
        $existingBill = $existingQuery->fetch(PDO::FETCH_ASSOC);
        if (!$existingBill) {
            $db->rollBack();
            respond(404, ['error' => 'That bill was not found.']);
        }
        if ($amountCents < (int) $existingBill['paid_cents']) {
            $db->rollBack();
            respond(422, ['error' => 'Bill amount cannot be less than payments already recorded.']);
        }
        $billUpdate = $db->prepare(
            'UPDATE bills SET supplier = :supplier, description = :description, category = :category,
                vehicle_code = :vehicle_code, bill_date = :bill_date, due_date = :due_date,
                amount_cents = :amount_cents,
                invoice_name = COALESCE(:invoice_name, invoice_name),
                invoice_mime = COALESCE(:invoice_mime, invoice_mime),
                invoice_data = COALESCE(:invoice_data, invoice_data)
             WHERE id = :id'
        );
        $billUpdate->bindValue(':supplier', $supplier);
        $billUpdate->bindValue(':description', $description);
        $billUpdate->bindValue(':category', $category);
        $billUpdate->bindValue(':vehicle_code', $vehicleCode);
        $billUpdate->bindValue(':bill_date', $billDate);
        $billUpdate->bindValue(':due_date', $dueDate);
        $billUpdate->bindValue(':amount_cents', $amountCents, PDO::PARAM_INT);
        $billUpdate->bindValue(':invoice_name', $invoice['name'] ?? null);
        $billUpdate->bindValue(':invoice_mime', $invoice['mime'] ?? null);
        $billUpdate->bindValue(':invoice_data', $invoice['data'] ?? null, $invoice === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $billUpdate->bindValue(':id', $billId, PDO::PARAM_INT);
        $billUpdate->execute();
        $expenseUpdate = $db->prepare(
            'UPDATE expenses SET expense_date = :bill_date, vehicle_code = :vehicle_code,
                driver_name = :driver_name, category = :category, description = :description,
                amount_cents = :amount_cents,
                status = CASE WHEN :amount_cents <= :paid_cents THEN \'Paid\' ELSE \'Pending\' END
             WHERE id = :expense_id'
        );
        $expenseUpdate->execute([
            'bill_date' => $billDate, 'vehicle_code' => $vehicleCode, 'driver_name' => $vehicle['driver_name'],
            'category' => $category, 'description' => $description, 'amount_cents' => $amountCents,
            'paid_cents' => $existingBill['paid_cents'], 'expense_id' => $existingBill['expense_id'],
        ]);
        $db->commit();
        respond(200, ['message' => 'Bill and its linked expense were updated together.']);
    }

    if (($payload['action'] ?? '') === 'record-bill-payment') {
        $billId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
        $paymentDate = (string) ($payload['payment_date'] ?? '');
        $amount = filter_var($payload['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $reference = trim((string) ($payload['reference'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        if (!$billId || !validDate($paymentDate) || $amount === false || $amount <= 0 || $amount > 10000000
            || strlen($reference) > 100 || strlen($notes) > 500) {
            respond(422, ['error' => 'Check the payment fields and provide valid values.']);
        }
        $amountCents = (int) round($amount * 100);
        $db->beginTransaction();
        $billStatement = $db->prepare(
            'SELECT bills.amount_cents, bills.expense_id,
                COALESCE(SUM(bill_payments.amount_cents), 0) AS paid_cents
             FROM bills LEFT JOIN bill_payments ON bill_payments.bill_id = bills.id
             WHERE bills.id = :id GROUP BY bills.id'
        );
        $billStatement->execute(['id' => $billId]);
        $bill = $billStatement->fetch(PDO::FETCH_ASSOC);
        if (!$bill) {
            $db->rollBack();
            respond(404, ['error' => 'That bill was not found.']);
        }
        $balanceCents = (int) $bill['amount_cents'] - (int) $bill['paid_cents'];
        if ($amountCents < 1 || $amountCents > $balanceCents) {
            $db->rollBack();
            respond(422, ['error' => 'Payment cannot exceed the remaining bill balance.']);
        }
        $paymentStatement = $db->prepare(
            'INSERT INTO bill_payments (bill_id, payment_date, amount_cents, reference, notes)
             VALUES (:bill_id, :payment_date, :amount_cents, :reference, :notes)'
        );
        $paymentStatement->execute([
            'bill_id' => $billId, 'payment_date' => $paymentDate, 'amount_cents' => $amountCents,
            'reference' => $reference, 'notes' => $notes,
        ]);
        if ($amountCents === $balanceCents) {
            $paidStatement = $db->prepare("UPDATE expenses SET status = 'Paid' WHERE id = :expense_id");
            $paidStatement->execute(['expense_id' => $bill['expense_id']]);
        }
        $db->commit();
        respond(201, ['message' => 'Payment recorded and applied to the bill balance. No duplicate expense was created.']);
    }

    if (($payload['action'] ?? '') === 'record-advance') {
        $advanceDate = (string) ($payload['advance_date'] ?? '');
        $driverName = trim((string) ($payload['driver_name'] ?? ''));
        $vehicleCode = trim((string) ($payload['vehicle_code'] ?? ''));
        $purpose = trim((string) ($payload['purpose'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        $amount = filter_var($payload['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        if (!validDate($advanceDate) || $driverName === '' || $vehicleCode === '' || $purpose === ''
            || strlen($purpose) > 120 || strlen($notes) > 500 || $amount === false || $amount <= 0 || $amount > 10000000) {
            respond(422, ['error' => 'Check the advance fields and provide valid values.']);
        }
        $amountCents = (int) round($amount * 100);
        if ($amountCents < 1) {
            respond(422, ['error' => 'Advance amount must be at least one cent.']);
        }
        $driverExists = $db->prepare('SELECT 1 FROM drivers WHERE name = :driver_name');
        $driverExists->execute(['driver_name' => $driverName]);
        $vehicleExists = $db->prepare('SELECT 1 FROM vehicles WHERE vehicle_code = :vehicle_code');
        $vehicleExists->execute(['vehicle_code' => $vehicleCode]);
        if (!$driverExists->fetchColumn() || !$vehicleExists->fetchColumn()) {
            respond(422, ['error' => 'Choose an existing driver and vehicle.']);
        }
        $statement = $db->prepare(
            'INSERT INTO cash_advances (advance_date, driver_name, vehicle_code, purpose, notes, amount_cents)
             VALUES (:advance_date, :driver_name, :vehicle_code, :purpose, :notes, :amount_cents)'
        );
        $statement->execute([
            'advance_date' => $advanceDate,
            'driver_name' => $driverName,
            'vehicle_code' => $vehicleCode,
            'purpose' => $purpose,
            'notes' => $notes,
            'amount_cents' => $amountCents,
        ]);
        respond(201, ['message' => 'Driver advance recorded.']);
    }

    if (($payload['action'] ?? '') === 'liquidate-advance') {
        $advanceId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
        $liquidatedAt = (string) ($payload['liquidated_at'] ?? '');
        $liquidatedBy = trim((string) ($payload['liquidated_by'] ?? ''));
        $amount = filter_var($payload['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $receiptReference = trim((string) ($payload['receipt_reference'] ?? ''));
        $liquidationNotes = trim((string) ($payload['liquidation_notes'] ?? ''));
        if (!$advanceId || !validDate($liquidatedAt) || $liquidatedBy === '' || strlen($liquidatedBy) > 120
            || $amount === false || $amount <= 0
            || strlen($receiptReference) > 80 || strlen($liquidationNotes) > 500) {
            respond(422, ['error' => 'Check the liquidation fields and provide valid values.']);
        }
        $amountCents = (int) round($amount * 100);
        $receipt = $readUpload('receipt');
        $db->beginTransaction();
        $advanceStatement = $db->prepare(
            'SELECT cash_advances.amount_cents, cash_advances.liquidated_cents,
                COALESCE((SELECT SUM(amount_cents) FROM advance_liquidations
                    WHERE advance_id = cash_advances.id AND status = \'Submitted\'), 0) AS submitted_cents
             FROM cash_advances WHERE cash_advances.id = :id'
        );
        $advanceStatement->execute(['id' => $advanceId]);
        $advance = $advanceStatement->fetch(PDO::FETCH_ASSOC);
        if (!$advance) {
            $db->rollBack();
            respond(404, ['error' => 'That advance was not found.']);
        }
        $remainingCents = (int) $advance['amount_cents'] - (int) $advance['liquidated_cents'];
        if ($amountCents < 1 || $amountCents > $remainingCents - (int) $advance['submitted_cents']) {
            $db->rollBack();
            respond(422, ['error' => 'Submitted receipts cannot exceed the unapproved advance balance.']);
        }
        $liquidationStatement = $db->prepare(
            'INSERT INTO advance_liquidations
                (advance_id, liquidated_at, liquidated_by, amount_cents, receipt_reference, notes, status, receipt_name, receipt_mime, receipt_data)
             VALUES (:advance_id, :liquidated_at, :liquidated_by, :amount_cents, :receipt_reference, :notes, \'Submitted\',
                :receipt_name, :receipt_mime, :receipt_data)'
        );
        $liquidationStatement->bindValue(':advance_id', $advanceId, PDO::PARAM_INT);
        $liquidationStatement->bindValue(':liquidated_at', $liquidatedAt);
        $liquidationStatement->bindValue(':liquidated_by', $liquidatedBy);
        $liquidationStatement->bindValue(':amount_cents', $amountCents, PDO::PARAM_INT);
        $liquidationStatement->bindValue(':receipt_reference', $receiptReference);
        $liquidationStatement->bindValue(':notes', $liquidationNotes);
        $liquidationStatement->bindValue(':receipt_name', $receipt['name'] ?? null);
        $liquidationStatement->bindValue(':receipt_mime', $receipt['mime'] ?? null);
        $liquidationStatement->bindValue(':receipt_data', $receipt['data'] ?? null, $receipt === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $liquidationStatement->execute();
        $db->commit();
        respond(201, ['message' => 'Liquidation submitted for review. The outstanding balance will update after approval.']);
    }

    if (($payload['action'] ?? '') === 'review-liquidation') {
        $liquidationId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($payload['decision'] ?? '');
        if (!$liquidationId || !in_array($decision, ['approve', 'reject'], true)) {
            respond(422, ['error' => 'Choose a valid liquidation and review decision.']);
        }
        $db->beginTransaction();
        $reviewQuery = $db->prepare(
            'SELECT advance_liquidations.advance_id, advance_liquidations.amount_cents,
                    advance_liquidations.status, advance_liquidations.liquidated_at,
                    advance_liquidations.receipt_reference, advance_liquidations.notes,
                    cash_advances.amount_cents AS advance_amount_cents,
                    cash_advances.liquidated_cents AS approved_cents
             FROM advance_liquidations
             JOIN cash_advances ON cash_advances.id = advance_liquidations.advance_id
             WHERE advance_liquidations.id = :id'
        );
        $reviewQuery->execute(['id' => $liquidationId]);
        $submission = $reviewQuery->fetch(PDO::FETCH_ASSOC);
        if (!$submission || $submission['status'] !== 'Submitted') {
            $db->rollBack();
            respond(409, ['error' => 'That liquidation is no longer waiting for review.']);
        }
        if ($decision === 'approve') {
            $balanceCents = (int) $submission['advance_amount_cents'] - (int) $submission['approved_cents'];
            if ((int) $submission['amount_cents'] > $balanceCents) {
                $db->rollBack();
                respond(409, ['error' => 'This submission exceeds the advance’s current balance.']);
            }
            $approveQuery = $db->prepare(
                'UPDATE cash_advances SET liquidated_cents = liquidated_cents + :amount_cents,
                    liquidated_at = :liquidated_at, receipt_reference = :receipt_reference,
                    liquidation_notes = :notes WHERE id = :id'
            );
            $approveQuery->execute([
                'amount_cents' => $submission['amount_cents'],
                'liquidated_at' => $submission['liquidated_at'],
                'receipt_reference' => $submission['receipt_reference'],
                'notes' => $submission['notes'],
                'id' => $submission['advance_id'],
            ]);
        }
        $reviewUpdate = $db->prepare(
            'UPDATE advance_liquidations SET status = :status
             WHERE id = :id AND status = \'Submitted\''
        );
        $reviewUpdate->execute([
            'status' => $decision === 'approve' ? 'Approved' : 'Rejected',
            'id' => $liquidationId,
        ]);
        $db->commit();
        respond(200, ['message' => $decision === 'approve' ? 'Liquidation approved.' : 'Liquidation rejected.']);
    }

    if (($payload['action'] ?? '') === 'report-issue') {
        $vehicleOrTrip = trim((string) ($payload['vehicle'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        if ($vehicleOrTrip === '' || $description === '' || strlen($vehicleOrTrip) > 80 || strlen($description) > 1000) {
            respond(422, ['error' => 'Provide a vehicle or trip and a description (up to 1,000 characters).']);
        }

        $statement = $db->prepare('INSERT INTO issue_reports (vehicle_or_trip, description) VALUES (:vehicle, :description)');
        $statement->execute(['vehicle' => $vehicleOrTrip, 'description' => $description]);
        respond(201, ['message' => 'Issue report submitted.']);
    }

    if (($payload['action'] ?? '') === 'request-maintenance') {
        $maintenanceId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$maintenanceId) {
            respond(422, ['error' => 'A valid maintenance item is required.']);
        }

        $statement = $db->prepare(
            "UPDATE maintenance SET state = 'requested' WHERE id = :id AND state IN ('overdue', 'due soon')"
        );
        $statement->execute(['id' => $maintenanceId]);
        if ($statement->rowCount() !== 1) {
            respond(404, ['error' => 'That maintenance item was not found or was already requested.']);
        }
        respond(200, ['message' => 'Maintenance request submitted.']);
    }

    if (($payload['action'] ?? '') === 'update-vehicle') {
        $vehicleCode = trim((string) ($payload['vehicle_code'] ?? ''));
        $make = trim((string) ($payload['make'] ?? ''));
        $vehicleType = trim((string) ($payload['vehicle_type'] ?? ''));
        $status = (string) ($payload['status'] ?? '');
        $driverName = trim((string) ($payload['driver_name'] ?? ''));
        $location = trim((string) ($payload['location'] ?? ''));
        $assignment = trim((string) ($payload['assignment'] ?? ''));
        $lastService = (string) ($payload['last_service'] ?? '');
        $fuelLevel = filter_var($payload['fuel_level'] ?? null, FILTER_VALIDATE_INT);

        if ($vehicleCode === '' || $make === '' || strlen($make) > 80 || $vehicleType === '' || strlen($vehicleType) > 60
            || !in_array($status, ['active', 'idle', 'maintenance', 'offline'], true)
            || strlen($driverName) > 100 || $location === '' || strlen($location) > 120 || strlen($assignment) > 120
            || !validDate($lastService) || $fuelLevel === false || $fuelLevel < 0 || $fuelLevel > 100) {
            respond(422, ['error' => 'Check the vehicle fields and provide valid values.']);
        }

        $statement = $db->prepare(
            'UPDATE vehicles SET make = :make, vehicle_type = :vehicle_type, status = :status,
                driver_name = :driver_name, location = :location, assignment = :assignment,
                last_service = :last_service, fuel_level = :fuel_level
             WHERE vehicle_code = :vehicle_code'
        );
        $statement->execute([
            'make' => $make,
            'vehicle_type' => $vehicleType,
            'status' => $status,
            'driver_name' => $driverName !== '' ? $driverName : null,
            'location' => $location,
            'assignment' => $assignment,
            'last_service' => $lastService,
            'fuel_level' => $fuelLevel,
            'vehicle_code' => $vehicleCode,
        ]);
        if ($statement->rowCount() === 0) {
            $exists = $db->prepare('SELECT 1 FROM vehicles WHERE vehicle_code = :vehicle_code');
            $exists->execute(['vehicle_code' => $vehicleCode]);
            if (!$exists->fetchColumn()) {
                respond(404, ['error' => 'That vehicle was not found.']);
            }
        }
        respond(200, ['message' => 'Vehicle updated.']);
    }

    if (($payload['action'] ?? '') === 'update-trip') {
        $tripId = trim((string) ($payload['id'] ?? ''));
        $vehicleCode = trim((string) ($payload['vehicle_code'] ?? ''));
        $driverName = trim((string) ($payload['driver_name'] ?? ''));
        $origin = trim((string) ($payload['origin'] ?? ''));
        $destination = trim((string) ($payload['destination'] ?? ''));
        $status = (string) ($payload['status'] ?? '');
        $delayMinutes = filter_var($payload['delay_minutes'] ?? null, FILTER_VALIDATE_INT);
        $eta = trim((string) ($payload['eta'] ?? ''));

        if ($tripId === '' || $vehicleCode === '' || $driverName === '' || strlen($driverName) > 100
            || $origin === '' || strlen($origin) > 120 || $destination === '' || strlen($destination) > 120
            || !in_array($status, ['active', 'upcoming', 'completed'], true)
            || $delayMinutes === false || $delayMinutes < 0 || $eta === '' || strlen($eta) > 30) {
            respond(422, ['error' => 'Check the trip fields and provide valid values.']);
        }
        $vehicleExists = $db->prepare('SELECT 1 FROM vehicles WHERE vehicle_code = :vehicle_code');
        $vehicleExists->execute(['vehicle_code' => $vehicleCode]);
        $driverExists = $db->prepare('SELECT 1 FROM drivers WHERE name = :driver_name');
        $driverExists->execute(['driver_name' => $driverName]);
        if (!$vehicleExists->fetchColumn() || !$driverExists->fetchColumn()) {
            respond(422, ['error' => 'Choose an existing vehicle and driver for this trip.']);
        }

        $statement = $db->prepare(
            'UPDATE trips SET vehicle_code = :vehicle_code, driver_name = :driver_name,
                origin = :origin, destination = :destination, status = :status,
                delay_minutes = :delay_minutes, eta = :eta WHERE id = :id'
        );
        $statement->execute([
            'vehicle_code' => $vehicleCode,
            'driver_name' => $driverName,
            'origin' => $origin,
            'destination' => $destination,
            'status' => $status,
            'delay_minutes' => $delayMinutes,
            'eta' => $eta,
            'id' => $tripId,
        ]);
        if ($statement->rowCount() === 0) {
            $exists = $db->prepare('SELECT 1 FROM trips WHERE id = :id');
            $exists->execute(['id' => $tripId]);
            if (!$exists->fetchColumn()) {
                respond(404, ['error' => 'That trip was not found.']);
            }
        }
        respond(200, ['message' => 'Trip updated.']);
    }

    if (($payload['action'] ?? '') === 'update-driver') {
        $driverId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
        $onDuty = (string) ($payload['on_duty'] ?? '');
        if (!$driverId || !in_array($onDuty, ['0', '1'], true)) {
            respond(422, ['error' => 'Choose a valid driver duty status.']);
        }
        $statement = $db->prepare('UPDATE drivers SET on_duty = :on_duty WHERE id = :id');
        $statement->execute(['on_duty' => (int) $onDuty, 'id' => $driverId]);
        if ($statement->rowCount() === 0) {
            $exists = $db->prepare('SELECT 1 FROM drivers WHERE id = :id');
            $exists->execute(['id' => $driverId]);
            if (!$exists->fetchColumn()) {
                respond(404, ['error' => 'That driver was not found.']);
            }
        }
        respond(200, ['message' => 'Driver updated.']);
    }

    if (($payload['action'] ?? '') === 'update-maintenance') {
        $maintenanceId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
        $vehicleCode = trim((string) ($payload['vehicle_code'] ?? ''));
        $task = trim((string) ($payload['task'] ?? ''));
        $department = trim((string) ($payload['department'] ?? ''));
        $depot = trim((string) ($payload['depot'] ?? ''));
        $dueDate = (string) ($payload['due_date'] ?? '');
        $state = (string) ($payload['state'] ?? '');
        if (!$maintenanceId || $vehicleCode === '' || $task === '' || strlen($task) > 120
            || $department === '' || strlen($department) > 80 || $depot === '' || strlen($depot) > 80
            || !validDate($dueDate) || !in_array($state, ['overdue', 'due soon', 'requested', 'complete'], true)) {
            respond(422, ['error' => 'Check the maintenance fields and provide valid values.']);
        }
        $vehicleExists = $db->prepare('SELECT 1 FROM vehicles WHERE vehicle_code = :vehicle_code');
        $vehicleExists->execute(['vehicle_code' => $vehicleCode]);
        if (!$vehicleExists->fetchColumn()) {
            respond(422, ['error' => 'Choose an existing vehicle for this maintenance item.']);
        }
        $statement = $db->prepare(
            'UPDATE maintenance SET vehicle_code = :vehicle_code, task = :task,
                department = :department, depot = :depot, due_date = :due_date, state = :state
             WHERE id = :id'
        );
        $statement->execute([
            'vehicle_code' => $vehicleCode,
            'task' => $task,
            'department' => $department,
            'depot' => $depot,
            'due_date' => $dueDate,
            'state' => $state,
            'id' => $maintenanceId,
        ]);
        if ($statement->rowCount() === 0) {
            $exists = $db->prepare('SELECT 1 FROM maintenance WHERE id = :id');
            $exists->execute(['id' => $maintenanceId]);
            if (!$exists->fetchColumn()) {
                respond(404, ['error' => 'That maintenance item was not found.']);
            }
        }
        respond(200, ['message' => 'Maintenance record updated.']);
    }

    respond(400, ['error' => 'Unknown action.']);
} catch (Throwable $exception) {
    error_log('Fleet dashboard API error: ' . $exception->getMessage());
    respond(500, ['error' => 'The request could not be saved. Check the PHP error log for details.']);
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/correction-reference.php';

function correctionNumericValue(string $value, string $label, bool $allowZero = true): float
{
    $value = trim($value);
    if ($value === '' || !is_numeric($value)) throw new RuntimeException('Enter a valid ' . $label . '.');
    $number = round((float) $value, 2);
    if ($number < 0 || (!$allowZero && $number <= 0)) throw new RuntimeException('Enter a valid ' . $label . '.');
    return $number;
}

function correctionDateValue(string $value): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
    if (!$date || $date->format('Y-m-d') !== trim($value) || $date > new DateTimeImmutable('today')) {
        throw new RuntimeException('Enter a valid received date that is not in the future.');
    }
    return $date->format('Y-m-d');
}

function correctionTextValue(string $value, string $label): string
{
    $value = trim($value);
    if ($value === '' || mb_strlen($value) > 100) throw new RuntimeException('Enter a valid ' . $label . '.');
    return $value;
}

function correctionValuesAreEqual(string $errorType, string $expected, string $actual): bool
{
    if (in_array($errorType, ['wrong-unit-cost', 'wrong-quantity', 'wrong-payment-amount'], true)) {
        return is_numeric($expected) && is_numeric($actual) && abs((float) $expected - (float) $actual) < 0.005;
    }
    return trim($expected) === trim($actual);
}

function updateReceiptPaymentSummary(PDO $database, int $receiptId, float $paidAmount): void
{
    $statement = $database->prepare('SELECT total_cost FROM stock_receipts WHERE id = ? FOR UPDATE');
    $statement->execute([$receiptId]);
    $totalCost = $statement->fetchColumn();
    if ($totalCost === false) throw new RuntimeException('The related batch no longer exists.');

    $totalCost = round((float) $totalCost, 2);
    $paidAmount = round($paidAmount, 2);
    if ($paidAmount > $totalCost) throw new RuntimeException('The corrected payment would exceed the batch total.');

    $balance = round($totalCost - $paidAmount, 2);
    $status = $paidAmount <= 0 ? 'unpaid' : ($balance <= 0 ? 'fully-paid' : 'partially-paid');
    $statement = $database->prepare(
        'UPDATE stock_receipts SET paid_amount = ?, balance_amount = ?, payment_status = ? WHERE id = ?'
    );
    $statement->execute([
        number_format($paidAmount, 2, '.', ''),
        number_format($balance, 2, '.', ''),
        $status,
        $receiptId,
    ]);
}

/** Apply one approved correction to its authoritative batch/payment record. */
function applyApprovedCorrection(PDO $database, array $request): void
{
    $reference = trim((string) $request['record_reference']);
    $referenceType = str_starts_with(strtoupper($reference), 'PAY-') ? 'payment' : 'batch';
    $record = resolveCorrectionReference($database, $referenceType, $reference, false, true);
    $liveValue = correctionReferenceCurrentValue($record, (string) $request['error_type']);
    if (!correctionValuesAreEqual((string) $request['error_type'], (string) $request['current_value'], $liveValue)) {
        throw new RuntimeException('This record changed after the request was submitted. Reject it and ask for a new correction request.');
    }

    $proposed = trim((string) $request['proposed_correction']);
    if (correctionValuesAreEqual((string) $request['error_type'], $liveValue, $proposed)) {
        throw new RuntimeException('The proposed value is the same as the current value.');
    }

    $recordId = (int) $record['id'];
    if ($referenceType === 'payment') {
        if ($request['error_type'] === 'wrong-check-number') {
            $checkNumber = correctionTextValue($proposed, 'check number');
            $statement = $database->prepare('UPDATE supplier_payments SET check_number = ? WHERE id = ?');
            $statement->execute([$checkNumber, $recordId]);
            return;
        }
        if ($request['error_type'] !== 'wrong-payment-amount') throw new RuntimeException('That field does not belong to a payment record.');

        $amount = correctionNumericValue($proposed, 'payment amount', false);
        $statement = $database->prepare('SELECT receipt_id, amount FROM supplier_payments WHERE id = ? FOR UPDATE');
        $statement->execute([$recordId]);
        $payment = $statement->fetch();
        if (!$payment) throw new RuntimeException('The payment no longer exists.');

        $sumStatement = $database->prepare('SELECT COALESCE(SUM(amount), 0) FROM supplier_payments WHERE receipt_id = ?');
        $sumStatement->execute([(int) $payment['receipt_id']]);
        $oldStoredTotal = (float) $sumStatement->fetchColumn();

        $receiptStatement = $database->prepare('SELECT paid_amount FROM stock_receipts WHERE id = ? FOR UPDATE');
        $receiptStatement->execute([(int) $payment['receipt_id']]);
        $currentPaid = $receiptStatement->fetchColumn();
        if ($currentPaid === false) throw new RuntimeException('The related batch no longer exists.');
        $initialPayment = max(0, (float) $currentPaid - $oldStoredTotal);

        $statement = $database->prepare('UPDATE supplier_payments SET amount = ? WHERE id = ?');
        $statement->execute([number_format($amount, 2, '.', ''), $recordId]);
        updateReceiptPaymentSummary($database, (int) $payment['receipt_id'], $initialPayment + $oldStoredTotal - (float) $payment['amount'] + $amount);
        return;
    }

    $receiptStatement = $database->prepare('SELECT * FROM stock_receipts WHERE id = ? FOR UPDATE');
    $receiptStatement->execute([$recordId]);
    $receipt = $receiptStatement->fetch();
    if (!$receipt) throw new RuntimeException('The batch no longer exists.');

    switch ($request['error_type']) {
        case 'wrong-unit-cost':
            $unitCost = correctionNumericValue($proposed, 'unit cost');
            $totalCost = round((int) $receipt['quantity'] * $unitCost, 2);
            if ((float) $receipt['paid_amount'] > $totalCost) throw new RuntimeException('The corrected batch total would be less than the amount already paid.');
            $statement = $database->prepare('UPDATE stock_receipts SET unit_cost = ?, total_cost = ? WHERE id = ?');
            $statement->execute([number_format($unitCost, 2, '.', ''), number_format($totalCost, 2, '.', ''), $recordId]);
            updateReceiptPaymentSummary($database, $recordId, (float) $receipt['paid_amount']);
            break;

        case 'wrong-quantity':
            if (!ctype_digit($proposed) || (int) $proposed < 1) throw new RuntimeException('Enter a valid quantity of at least 1.');
            $quantity = (int) $proposed;
            $breakdown = json_decode((string) ($receipt['power_breakdown'] ?? ''), true);
            if (is_array($breakdown) && count($breakdown) > 1) {
                throw new RuntimeException('A batch with multiple power quantities cannot be changed automatically. Submit separate power details for administrator review.');
            }
            if (is_array($breakdown) && count($breakdown) === 1) {
                $breakdown[0]['count'] = $quantity;
            }
            $delta = $quantity - (int) $receipt['quantity'];
            $totalCost = round($quantity * (float) $receipt['unit_cost'], 2);
            if ((float) $receipt['paid_amount'] > $totalCost) throw new RuntimeException('The corrected batch total would be less than the amount already paid.');
            $stock = $database->prepare('UPDATE inventory_items SET quantity = quantity + ? WHERE id = ? AND quantity + ? >= 0');
            $stock->execute([$delta, (int) $receipt['item_id'], $delta]);
            if ($delta !== 0 && $stock->rowCount() !== 1) throw new RuntimeException('The quantity correction would make current stock negative.');
            $statement = $database->prepare('UPDATE stock_receipts SET quantity = ?, power_breakdown = ?, total_cost = ? WHERE id = ?');
            $statement->execute([$quantity, is_array($breakdown) ? json_encode($breakdown, JSON_THROW_ON_ERROR) : $receipt['power_breakdown'], number_format($totalCost, 2, '.', ''), $recordId]);
            updateReceiptPaymentSummary($database, $recordId, (float) $receipt['paid_amount']);
            break;

        case 'wrong-bill-number':
            $billNumber = correctionTextValue($proposed, 'bill / invoice number');
            $statement = $database->prepare('UPDATE stock_receipts SET bill_number = ? WHERE id = ?');
            $statement->execute([$billNumber, $recordId]);
            break;

        case 'wrong-date':
            $statement = $database->prepare('UPDATE stock_receipts SET received_date = ? WHERE id = ?');
            $statement->execute([correctionDateValue($proposed), $recordId]);
            break;

        case 'wrong-check-number':
            $statement = $database->prepare('UPDATE stock_receipts SET check_number = ? WHERE id = ?');
            $statement->execute([correctionTextValue($proposed, 'check number'), $recordId]);
            break;

        case 'wrong-payment-amount':
            $initialAmount = correctionNumericValue($proposed, 'payment amount');
            $sumStatement = $database->prepare('SELECT COALESCE(SUM(amount), 0) FROM supplier_payments WHERE receipt_id = ?');
            $sumStatement->execute([$recordId]);
            updateReceiptPaymentSummary($database, $recordId, $initialAmount + (float) $sumStatement->fetchColumn());
            break;

        default:
            throw new RuntimeException('That batch field cannot be corrected automatically.');
    }
}

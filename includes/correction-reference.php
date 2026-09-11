<?php
declare(strict_types=1);

/**
 * Resolve an eligible batch/payment reference and return its live values.
 * The seven-day correction window is enforced here for both the form lookup
 * endpoint and the final POST submission.
 */
function resolveCorrectionReference(PDO $database, string $referenceType, string $reference, bool $enforceWindow = true, bool $lockRecord = false): array
{
    if (!in_array($referenceType, ['batch', 'payment'], true)) {
        throw new RuntimeException('Select Batch Number or Payment Number.');
    }

    $prefix = $referenceType === 'batch' ? 'BAT' : 'PAY';
    if (!preg_match('/^(?:' . $prefix . '-)?(\d+)$/i', trim($reference), $matches) || (int) $matches[1] < 1) {
        throw new RuntimeException(sprintf('Enter a valid %s number, such as %s-0001.', $referenceType, $prefix));
    }

    $recordId = (int) $matches[1];
    if ($referenceType === 'batch') {
        $sql =
            'SELECT r.id, r.quantity, r.received_date, r.unit_cost, r.total_cost, r.bill_number, r.paid_amount,
                    r.check_number, s.company_name, i.item_name,
                    GREATEST(r.paid_amount - COALESCE((SELECT SUM(p.amount) FROM supplier_payments p WHERE p.receipt_id = r.id), 0), 0) AS initial_payment_amount,
                    r.received_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE() AS correction_allowed
             FROM stock_receipts r
             JOIN suppliers s ON s.id = r.supplier_id
             JOIN inventory_items i ON i.id = r.item_id
             WHERE r.id = ? LIMIT 1';
    } else {
        $sql =
            'SELECT p.id, p.amount, p.check_number, p.payment_date,
                    p.payment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE() AS correction_allowed
             FROM supplier_payments p
             WHERE p.id = ? LIMIT 1';
    }

    if ($lockRecord) $sql .= ' FOR UPDATE';
    $statement = $database->prepare($sql);
    $statement->execute([$recordId]);
    $record = $statement->fetch();
    if (!$record) {
        $suffix = $referenceType === 'payment' ? ' Initial payments must use the batch number.' : '';
        throw new RuntimeException('The selected ' . $referenceType . ' number does not exist.' . $suffix);
    }
    if ($enforceWindow && !(bool) $record['correction_allowed']) {
        $dateLabel = $referenceType === 'batch' ? 'received date' : 'payment date';
        throw new RuntimeException(ucfirst($referenceType) . ' corrections can be requested only within 7 days of the ' . $dateLabel . '.');
    }

    $record['reference_type'] = $referenceType;
    $record['record_reference'] = $prefix . '-' . str_pad((string) $recordId, 4, '0', STR_PAD_LEFT);
    return $record;
}

/** Return the authoritative current value for the selected correction field. */
function correctionReferenceCurrentValue(array $record, string $errorType): string
{
    if ($record['reference_type'] === 'payment') {
        return match ($errorType) {
            'wrong-payment-amount' => number_format((float) $record['amount'], 2, '.', ''),
            'wrong-check-number' => trim((string) ($record['check_number'] ?? '')) ?: 'Not recorded',
            default => throw new RuntimeException('Select a payment-related field for a payment correction.'),
        };
    }

    return match ($errorType) {
        'wrong-quantity' => (string) $record['quantity'],
        'wrong-unit-cost' => number_format((float) $record['unit_cost'], 2, '.', ''),
        'wrong-bill-number' => (string) $record['bill_number'],
        'wrong-date' => (string) $record['received_date'],
        'wrong-payment-amount' => number_format((float) $record['initial_payment_amount'], 2, '.', ''),
        'wrong-check-number' => trim((string) ($record['check_number'] ?? '')) ?: 'Not recorded',
        default => throw new RuntimeException('Select a valid correction field.'),
    };
}

<?php
declare(strict_types=1);

requireRole('store-keeper');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/correction-reference.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

try {
    $record = resolveCorrectionReference(
        database(),
        trim((string) ($_GET['reference_type'] ?? '')),
        trim((string) ($_GET['record_reference'] ?? ''))
    );
    $currentValue = correctionReferenceCurrentValue($record, trim((string) ($_GET['error_type'] ?? '')));
    echo json_encode([
        'ok' => true,
        'record_reference' => $record['record_reference'],
        'current_value' => $currentValue,
    ], JSON_THROW_ON_ERROR);
} catch (RuntimeException $exception) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to load the current record value.'], JSON_THROW_ON_ERROR);
}

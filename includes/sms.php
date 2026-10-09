<?php
declare(strict_types=1);

function widmsSmsPhone(string $phone): ?string
{
    $phone = preg_replace('/[\s().-]+/', '', trim($phone)) ?? '';
    if (str_starts_with($phone, '+')) {
        $phone = substr($phone, 1);
    } elseif (str_starts_with($phone, '00')) {
        $phone = substr($phone, 2);
    }
    if (preg_match('/\A07[0-9]{8}\z/', $phone)) {
        $phone = '94' . substr($phone, 1);
    }
    return preg_match('/\A947[0-9]{8}\z/', $phone) ? $phone : null;
}

function widmsAccountApprovedSmsText(?string $username = null): string
{
    return $username !== null
        ? 'Your SWPCS account has been successfully created. Username: ' . $username . '. Log in with the password you entered during registration.'
        : 'Your account has been successfully created. You can now log in to SWPCS using your registered email address and password.';
}

function widmsAdminCreatedAccountSmsText(string $username): string
{
    return 'Your SWPCS account has been successfully created. Username: ' . $username . '. You can now log in to SWPCS. Contact your administrator for your login password.';
}

function widmsAccountRejectedSmsText(string $reason): string
{
    return 'Your SWPCS account registration request was rejected. Reason: ' . trim($reason);
}

/** Textit documents a colon-delimited status on the first response line. */
function widmsParseSmsResponse(string $body): array
{
    $body = trim($body, " \t\r\n");
    // Some PHP endpoints include a UTF-8 byte-order mark before their output.
    if (str_starts_with($body, "\xEF\xBB\xBF")) {
        $body = trim(substr($body, 3), " \t\r\n");
    }
    if ($body === '') {
        return ['status' => 'unknown', 'error' => 'empty-response'];
    }
    if (str_starts_with($body, '<')) {
        return ['status' => 'unknown', 'error' => 'html-response'];
    }
    $lines = preg_split('/\r\n|\r|\n/', $body);
    [$status, $reference] = array_pad(explode(':', $lines[0], 2), 2, '');
    $status = strtoupper(trim($status));
    if (in_array($status, ['ERROR', 'ERR', 'FAIL'], true)) {
        return ['status' => 'failed', 'error' => 'gateway-rejected'];
    }
    if ($status !== 'OK') {
        return ['status' => 'unknown', 'error' => 'unexpected-response'];
    }
    // A single-recipient send must not report multiple/conflicting outcomes.
    foreach (array_slice($lines, 1) as $line) {
        if (preg_match('/^\s*(?:OK|ERROR|ERR|FAIL)\s*:/i', $line)) {
            return ['status' => 'unknown', 'error' => 'multiple-response'];
        }
    }
    $reference = trim($reference, " \t");
    // Textit documents two successful response shapes. The older one embeds a
    // MessageID and recipient number; retain only the ID. The newer one reports
    // an uploaded count/type without a unique ID, so store no reference.
    if (preg_match('/\ARoute=[A-Za-z0-9_-]+,[ \t]*MessageID=([A-Za-z0-9._:-]{1,100}),[ \t]*Recipient=[0-9]{9,15}\z/iD', $reference, $parts)) {
        return ['status' => 'sent', 'reference' => $parts[1], 'error' => null];
    }
    if (preg_match('/\A[1-9][0-9]*-MSG_[A-Z0-9_]+-[0-9]+[ \t]+Uploaded_Successfully\z/iD', $reference)) {
        return ['status' => 'sent', 'reference' => null, 'error' => null];
    }
    // IDs are opaque: punctuation is valid. Never accept markup, control bytes,
    // an absent ID, or a value that cannot fit the database reference column.
    if ($reference === '' || strlen($reference) > 100 || preg_match('/[\x00-\x20\x7F-\xFF<>]/', $reference)) {
        return ['status' => 'unknown', 'error' => 'invalid-reference'];
    }
    return ['status' => 'sent', 'reference' => $reference, 'error' => null];
}

/** Sends only to Textit.biz. The optional transport allows tests without sending an SMS. */
function widmsSendSms(string $phone, string $message, ?array $settings = null, ?callable $transport = null): array
{
    $phone = widmsSmsPhone($phone);
    if ($phone === null) {
        return ['status' => 'failed', 'error' => 'invalid-phone'];
    }
    $settings ??= require __DIR__ . '/../config/sms.php';
    if (empty($settings['enabled']) || empty($settings['user_id']) || empty($settings['password'])) {
        return ['status' => 'failed', 'error' => 'not-configured'];
    }
    if ($message === '' || strlen($message) > 1000) {
        return ['status' => 'failed', 'error' => 'invalid-message'];
    }

    $payload = http_build_query([
        'id' => (string) $settings['user_id'],
        'pw' => (string) $settings['password'],
        'to' => $phone,
        'text' => $message,
    ], '', '&', PHP_QUERY_RFC3986);
    try {
        if ($transport !== null) {
            $response = $transport('https://textit.biz/sendmsg/', $payload);
        } else {
            if (!function_exists('curl_init')) {
                return ['status' => 'failed', 'error' => 'curl-unavailable'];
            }
            $curl = curl_init('https://textit.biz/sendmsg/');
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            ]);
            $body = curl_exec($curl);
            $response = [
                'http_status' => (int) curl_getinfo($curl, CURLINFO_HTTP_CODE),
                'body' => $body === false ? '' : $body,
                'network_error' => curl_errno($curl) !== 0,
            ];
            curl_close($curl);
        }
    } catch (Throwable $exception) {
        // Never log the gateway URL, payload, credentials, recipient, or raw response.
        return ['status' => 'unknown', 'error' => 'transport-error'];
    }
    if (!empty($response['network_error']) || ($response['http_status'] ?? 0) !== 200) {
        return ['status' => 'unknown', 'error' => 'gateway-unconfirmed'];
    }
    $body = (string) ($response['body'] ?? '');
    $result = widmsParseSmsResponse($body);
    if ($result['status'] === 'unknown') {
        // Structure only: never write credentials, phone numbers or raw replies.
        error_log('SWPCS SMS response: ' . $result['error'] . '; HTTP 200; bytes=' . strlen($body) . '.');
    }
    return $result;
}

/** Call only after approval commits. One atomic claim prevents repeat sends. */
function widmsSendRegistrationApprovalSms(PDO $db, int $requestId, ?callable $sender = null): array
{
    return widmsSendRegistrationDecisionSms($db, $requestId, 'approved', $sender);
}

function widmsSendRegistrationRejectionSms(PDO $db, int $requestId, ?callable $sender = null): array
{
    return widmsSendRegistrationDecisionSms($db, $requestId, 'rejected', $sender);
}

/** Send once for a committed decision; rejected applicants do not have a user account. */
function widmsSendRegistrationDecisionSms(PDO $db, int $requestId, string $decision, ?callable $sender = null): array
{
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        throw new InvalidArgumentException('Invalid registration decision.');
    }
    if ($db->inTransaction()) {
        throw new LogicException('Account decision must commit before SMS notification.');
    }
    $eligibility = $decision === 'approved'
        ? "EXISTS (SELECT 1 FROM users
                       WHERE users.status = 'active' AND
                         ((registration_requests.salary_number IS NOT NULL AND users.salary_number = registration_requests.salary_number)
                          OR (registration_requests.salary_number IS NULL AND users.username = registration_requests.email)))"
        : "rejection_reason IS NOT NULL AND TRIM(rejection_reason) <> ''";
    $claim = $db->prepare(
        "UPDATE registration_requests SET sms_status = 'sending'
         WHERE id = :id AND status = :decision AND sms_status = 'not-sent'
           AND $eligibility"
    );
    $claim->execute(['id' => $requestId, 'decision' => $decision]);
    if ($claim->rowCount() !== 1) {
        return ['status' => 'skipped', 'error' => null];
    }

    try {
        $select = $db->prepare($decision === 'approved' ? "SELECT r.phone, u.username FROM registration_requests r JOIN users u ON
            ((r.salary_number IS NOT NULL AND u.salary_number = r.salary_number)
             OR (r.salary_number IS NULL AND u.username = r.email))
            WHERE r.id = :id AND u.status = 'active'"
            : "SELECT phone,rejection_reason FROM registration_requests WHERE id = :id AND status = 'rejected'");
        $select->execute(['id' => $requestId]);
        $recipient = $select->fetch(PDO::FETCH_ASSOC);
        if (!$recipient) {
            throw new RuntimeException('Registration notification recipient is unavailable.');
        }
        $phone = (string) $recipient['phone'];
        $message = $decision === 'approved'
            ? widmsAccountApprovedSmsText($recipient['username'])
            : widmsAccountRejectedSmsText($recipient['rejection_reason']);
        $result = $sender !== null
            ? $sender($phone, $message)
            : widmsSendSms($phone, $message);
    } catch (Throwable $exception) {
        $result = ['status' => 'unknown', 'error' => 'notification-error'];
    }
    if (!in_array($result['status'] ?? '', ['sent', 'failed', 'unknown'], true)) {
        $result = ['status' => 'unknown', 'error' => 'invalid-result'];
    }
    $update = $db->prepare(
        "UPDATE registration_requests SET sms_status = :status, sms_error_code = :error,
                sms_gateway_reference = :reference,
                sms_sent_at = CASE WHEN :accepted = 'sent' THEN CURRENT_TIMESTAMP ELSE NULL END
         WHERE id = :id AND sms_status = 'sending'"
    );
    $update->execute([
        'status' => $result['status'], 'error' => $result['error'] ?? null,
        'reference' => $result['reference'] ?? null, 'accepted' => $result['status'],
        'id' => $requestId,
    ]);
    if ($result['status'] !== 'sent') {
        error_log('SWPCS registration SMS REG-' . $requestId . ': ' . $result['status'] . '.');
    }
    return $result;
}

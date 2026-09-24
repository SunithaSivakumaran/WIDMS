<?php
declare(strict_types=1);

/**
 * Build the shared More Information payload for every aid-request table.
 * Includes configured beneficiary fields plus direct-request reason/PDF.
 */
function aidRequestDetails(array $request): array
{
    $details = json_decode((string) ($request['beneficiary_details_json'] ?? ''), true);
    if (!is_array($details)) {
        $details = [];
    }

    if ($details === [] && !empty($request['beneficiary_detail_label']) && $request['beneficiary_detail_value'] !== null) {
        $details[] = [
            'label' => (string) $request['beneficiary_detail_label'],
            'type' => 'text',
            'value' => (string) $request['beneficiary_detail_value'],
            'display_value' => (string) $request['beneficiary_detail_value'],
        ];
    }

    if (!empty($request['eligibility_override']) && !empty($request['notes'])) {
        $details[] = [
            'label' => function_exists('t') ? t('Reason for Direct Request') : 'Reason for Direct Request',
            'type' => 'text',
            'value' => (string) $request['notes'],
            'display_value' => (string) $request['notes'],
        ];
    }

    $document = (string) ($request['direct_request_document'] ?? '');
    if (preg_match('#^uploads/aid-documents/direct-[a-f0-9]{24}\.pdf$#', $document)) {
        $details[] = [
            'label' => function_exists('t') ? t('Supporting Document') : 'Supporting Document',
            'type' => 'pdf',
            'value' => $document,
            'display_value' => function_exists('t') ? t('Open supporting PDF') : 'Open supporting PDF',
        ];
    }

    return $details;
}

<?php
declare(strict_types=1);

final class BeneficiaryDivisionException extends RuntimeException {}

function beneficiaryDivisionConflict(array $location): BeneficiaryDivisionException
{
    $district = (string) ($location['district_name'] ?: 'District #' . $location['district_id']);
    $division = (string) ($location['division_name'] ?: 'DS Division #' . $location['ds_division_id']);
    $message = 'This beneficiary is registered in %s District, %s DS Division and cannot be assigned to another DS Division.';
    return new BeneficiaryDivisionException(sprintf(function_exists('t') ? t($message) : $message, $district, $division));
}

/** Keep an identified person in their recorded DS Division across aid and camp workflows. */
function assertBeneficiaryIdentityDivision(PDO $db, string $nic, string $elderCard, int $divisionId): void
{
    $nic = strtoupper(trim($nic));
    $elderCard = strtoupper(trim($elderCard));
    if ($nic === '' && $elderCard === '') return;

    foreach (['beneficiaries' => 'elders_card_number', 'spectacle_camp_participants' => 'elder_card_number'] as $table => $elderColumn) {
        $statement = $db->prepare("SELECT b.district_id, b.ds_division_id,
                district.name AS district_name, division.name AS division_name
            FROM $table b
            LEFT JOIN ds_divisions division ON division.id = b.ds_division_id
            LEFT JOIN districts district ON district.id = COALESCE(division.district_id, b.district_id)
            WHERE b.ds_division_id <> ?
              AND ((? <> '' AND UPPER(TRIM(COALESCE(b.nic, ''))) = ?)
                OR (? <> '' AND UPPER(TRIM(COALESCE(b.$elderColumn, ''))) = ?))
            ORDER BY b.id LIMIT 1 FOR UPDATE");
        $statement->execute([$divisionId, $nic, $nic, $elderCard, $elderCard]);
        $match = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$match) continue;

        throw beneficiaryDivisionConflict($match);
    }
}

/** Recheck recorded aid beneficiaries immediately before stock is handed over or distributed. */
function assertBeneficiaryRecordDivision(PDO $db, int $beneficiaryId, ?int $targetDivisionId = null): void
{
    $statement = $db->prepare('SELECT b.nic, b.elders_card_number, b.district_id, b.ds_division_id,
            district.name AS district_name, division.name AS division_name
        FROM beneficiaries b
        LEFT JOIN ds_divisions division ON division.id = b.ds_division_id
        LEFT JOIN districts district ON district.id = COALESCE(division.district_id, b.district_id)
        WHERE b.id = ? FOR UPDATE');
    $statement->execute([$beneficiaryId]);
    $beneficiary = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$beneficiary) throw new RuntimeException('Beneficiary not found.');
    if ($targetDivisionId !== null && (int)$beneficiary['ds_division_id'] !== $targetDivisionId) {
        throw beneficiaryDivisionConflict($beneficiary);
    }
    assertBeneficiaryIdentityDivision(
        $db,
        (string)($beneficiary['nic'] ?? ''),
        (string)($beneficiary['elders_card_number'] ?? ''),
        (int)$beneficiary['ds_division_id']
    );
}

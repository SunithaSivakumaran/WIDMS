<?php
declare(strict_types=1);

require_once __DIR__ . '/spectacle-camps.php';
require_once __DIR__ . '/spectacle-categories.php';

/** Completed camps can be exported by the roles that share participant history. */
function scParticipantExportData(PDO $db, int $campId, array $actor): array
{
    if ($campId < 1 || !in_array($actor['role'], ['subject-officer', 'admin'], true)) {
        throw new DomainException('Access denied.');
    }
    $camp = scCamp($db, $campId, $actor);
    if ($camp['status'] !== 'completed') {
        throw new DomainException('Complete the Vision Camp before printing or exporting participants.');
    }
    $location = scQuery($db, 'SELECT d.name district_name,ds.name division_name FROM spectacle_camps c JOIN districts d ON d.id=c.district_id JOIN ds_divisions ds ON ds.id=c.ds_division_id WHERE c.id=?', [$campId])->fetch(PDO::FETCH_ASSOC);
    $participants = scQuery($db, 'SELECT p.id,p.full_name,p.gender,p.nic,p.elder_card_number,p.address,p.phone,p.status,p.rejection_reason,p.spectacle_category_id,g.name gn_name,category.name spectacle_type FROM spectacle_camp_participants p JOIN gn_divisions g ON g.id=p.gn_division_id LEFT JOIN spectacle_categories category ON category.id=p.spectacle_category_id WHERE p.camp_id=? ORDER BY p.id', [$campId])->fetchAll(PDO::FETCH_ASSOC);
    $totals = [];
    foreach (widmsSpectacleCategories($db, false) as $category) {
        $totals[$category['id']] = ['type' => $category['name'], 'quantity' => 0];
    }
    foreach ($participants as $participant) {
        if ($participant['status'] !== 'approved') continue;
        $categoryId = (int) ($participant['spectacle_category_id'] ?? 0);
        if (!isset($totals[$categoryId])) $totals[$categoryId] = ['type' => $participant['spectacle_type'] ?: 'Unspecified', 'quantity' => 0];
        $totals[$categoryId]['quantity']++;
    }
    return ['camp' => $camp + ($location ?: []), 'participants' => $participants, 'totals' => array_values($totals)];
}

function scExportCellValue(mixed $value): string
{
    $safe = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $value);
    return htmlspecialchars($safe ?? '', ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function scExportColumn(int $index): string
{
    $column = '';
    for ($index++; $index > 0; $index = intdiv($index - 1, 26)) $column = chr(65 + ($index - 1) % 26) . $column;
    return $column;
}

/** Inline strings preserve NICs, phone numbers and leading zeroes in Excel. */
function scExportWorksheet(array $rows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ($rows as $rowIndex => $row) {
        $number = $rowIndex + 1;
        $xml .= '<row r="' . $number . '">';
        foreach (array_values($row) as $columnIndex => $value) {
            $reference = scExportColumn($columnIndex) . $number;
            $xml .= is_int($value)
                ? '<c r="' . $reference . '"><v>' . $value . '</v></c>'
                : '<c r="' . $reference . '" t="inlineStr"><is><t>' . scExportCellValue($value) . '</t></is></c>';
        }
        $xml .= '</row>';
    }
    return $xml . '</sheetData></worksheet>';
}

/** Create a two-sheet OOXML workbook without an additional package dependency. Caller removes the returned temporary file. */
function scParticipantWorkbook(array $data): string
{
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('The PHP ZIP extension is required for Excel export.');
    $camp = $data['camp'];
    $summary = [
        ['Vision Camp VC-' . $camp['id'] . ' - Spectacles Required'],
        ['District', $camp['district_name']],
        ['DS Division', $camp['division_name']],
        ['Camp Date', $camp['camp_date']],
        ['Completed At', $camp['completed_at']],
        [],
        ['Spectacle Type', 'Units Required'],
    ];
    $total = 0;
    foreach ($data['totals'] as $row) {
        $summary[] = [$row['type'], (int) $row['quantity']];
        $total += (int) $row['quantity'];
    }
    $summary[] = ['TOTAL', $total];
    $people = [['Participant ID', 'Full Name', 'Gender', 'NIC / Elder Card', 'Address', 'GN Division', 'Phone Number', 'Spectacle Type', 'Status', 'Rejection Reason', 'Signature']];
    foreach ($data['participants'] as $person) {
        $people[] = ['SCP-' . $person['id'], $person['full_name'], ucfirst((string) $person['gender']), $person['nic'] ?: $person['elder_card_number'], $person['address'], $person['gn_name'], $person['phone'] ?: '', $person['spectacle_type'] ?: '', $person['status'] === 'approved' ? 'Selected for spectacles' : ucfirst((string) $person['status']), $person['rejection_reason'] ?: '', ''];
    }
    $path = tempnam(sys_get_temp_dir(), 'widms-camp-');
    if ($path === false) throw new RuntimeException('Unable to create the Excel export.');
    $zip = new ZipArchive();
    $opened = false;
    try {
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to create the Excel workbook.');
        $opened = true;
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Spectacle Totals" sheetId="1" r:id="rId1"/><sheet name="Participants" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', scExportWorksheet($summary));
        $zip->addFromString('xl/worksheets/sheet2.xml', scExportWorksheet($people));
        $closed = $zip->close();
        $opened = false;
        if (!$closed) throw new RuntimeException('Unable to finish the Excel workbook.');
        return $path;
    } catch (Throwable $error) {
        if ($opened) $zip->close();
        unlink($path);
        throw $error;
    }
}

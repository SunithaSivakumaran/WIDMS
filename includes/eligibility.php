<?php
declare(strict_types=1);

function beneficiaryEligibility(PDO $db, int $beneficiaryId, int $itemId): array
{
    $item = $db->prepare("SELECT i.id,i.item_name,i.variety,i.category_id,c.name category_name,c.distribution_type,
        COALESCE(r.restriction_months,0) restriction_months,COALESCE(r.allow_multiple,0) allow_multiple
        FROM inventory_items i
        JOIN item_categories c ON c.id=i.category_id AND c.status='active'
        LEFT JOIN eligibility_rules r ON r.category_id=c.id AND r.status='active'
        WHERE i.id=:item");
    $item->execute(['item' => $itemId]);
    $data = $item->fetch();
    if (!$data) {
        return ['eligible' => false, 'reason' => 'The selected item has no active distribution category.', 'item' => null];
    }

    $beneficiary = $db->prepare("SELECT id FROM beneficiaries WHERE id=:id AND status='active'");
    $beneficiary->execute(['id' => $beneficiaryId]);
    if (!$beneficiary->fetchColumn()) {
        return ['eligible' => false, 'reason' => 'The beneficiary is not active.', 'item' => $data];
    }

    if ((int)$data['allow_multiple'] === 1 || (int)$data['restriction_months'] === 0) {
        return ['eligible' => true, 'reason' => 'Eligible under the active category rule.', 'item' => $data];
    }

    $last = $db->prepare('SELECT MAX(d.distributed_at) FROM distributions d JOIN inventory_items i ON i.id=d.item_id WHERE d.beneficiary_id=:beneficiary AND i.category_id=:category');
    $last->execute(['beneficiary' => $beneficiaryId, 'category' => $data['category_id']]);
    $lastDate = $last->fetchColumn();
    if (!$lastDate) {
        return ['eligible' => true, 'reason' => 'No previous distribution exists in this category.', 'item' => $data];
    }

    $eligibleOn = (new DateTimeImmutable((string)$lastDate))->modify('+'.(int)$data['restriction_months'].' months');
    if ($eligibleOn > new DateTimeImmutable()) {
        return ['eligible' => false, 'reason' => 'Restriction period active until '.$eligibleOn->format('d M Y').'.', 'item' => $data];
    }
    return ['eligible' => true, 'reason' => 'The category restriction period has ended.', 'item' => $data];
}

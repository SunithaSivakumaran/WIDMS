<?php
declare(strict_types=1);

/** Spectacle types are managed by Subject Officers, not numeric lens powers. */
function widmsIsSpectacleItem(string $itemName): bool
{
    return (bool) preg_match('/^(?:spectacles?|specs|glasses)$/i', trim($itemName));
}

/** @return array<int, array{id:int,name:string,status:string}> */
function widmsSpectacleCategories(PDO $database, bool $activeOnly = true): array
{
    $sql = 'SELECT id, name, status FROM spectacle_categories'
        . ($activeOnly ? " WHERE status = 'active'" : '')
        . ' ORDER BY display_order, name, id';
    return array_map(
        static fn(array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'status' => (string) $row['status'],
        ],
        $database->query($sql)->fetchAll(PDO::FETCH_ASSOC)
    );
}

function widmsSpectacleCategoryName(PDO $database, ?int $categoryId): ?string
{
    if (!$categoryId) return null;
    $query = $database->prepare('SELECT name FROM spectacle_categories WHERE id = ?');
    $query->execute([$categoryId]);
    $name = $query->fetchColumn();
    return $name === false ? null : (string) $name;
}

/** A used type stays in history; only an entirely unused option may be removed. */
function widmsSpectacleCategoryInUse(PDO $database, int $categoryId): bool
{
    if ($categoryId < 1) return false;
    foreach ([
        'aid_requests',
        'stock_receipt_spectacle_lines',
        'spectacle_camp_participants',
        'spectacle_camp_receipts',
        'spectacle_camp_stock_requests',
        'spectacle_camp_transfers',
        'spectacle_camp_distributions',
    ] as $table) {
        $query = $database->prepare("SELECT 1 FROM {$table} WHERE spectacle_category_id = ? LIMIT 1");
        $query->execute([$categoryId]);
        if ($query->fetchColumn() !== false) return true;
    }
    return false;
}

/** @return array<int,int> Remaining Central Stock, keyed by spectacle category. */
function widmsSpectacleCategoryBalances(PDO $database, int $itemId): array
{
    $balances = [];
    if ($itemId < 1) return $balances;

    $received = $database->prepare(
        "SELECT line.spectacle_category_id, SUM(line.quantity) AS amount
         FROM stock_receipt_spectacle_lines line
         JOIN stock_receipts receipt ON receipt.id = line.stock_receipt_id
         WHERE receipt.item_id = ? AND receipt.stock_destination = 'central'
         GROUP BY line.spectacle_category_id"
    );
    $received->execute([$itemId]);
    foreach ($received->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $balances[(int) $row['spectacle_category_id']] = (int) $row['amount'];
    }

    $used = $database->prepare(
        "SELECT category_id, SUM(amount) AS amount FROM (
           SELECT ar.spectacle_category_id AS category_id, ar.quantity AS amount
           FROM goods_request_aid_requests link
           JOIN goods_requests goods ON goods.id = link.goods_request_id
           JOIN aid_requests ar ON ar.id = link.aid_request_id
           WHERE ar.item_id = ? AND ar.spectacle_category_id IS NOT NULL
             AND goods.status IN ('pending-admin-approval', 'approved-awaiting-dispatch', 'dispatched')
           UNION ALL
           SELECT ar.spectacle_category_id, ar.quantity
           FROM admin_direct_releases release_record
           JOIN aid_requests ar ON ar.id = release_record.aid_request_id
           WHERE ar.item_id = ? AND ar.spectacle_category_id IS NOT NULL
             AND release_record.status = 'released-to-admin'
           UNION ALL
           SELECT ar.spectacle_category_id, distribution.quantity
           FROM distributions distribution
           JOIN aid_requests ar ON ar.id = distribution.aid_request_id
           WHERE ar.item_id = ? AND ar.spectacle_category_id IS NOT NULL
             AND distribution.source = 'central-stock'
             AND NOT EXISTS (
                 SELECT 1 FROM goods_request_aid_requests link
                 WHERE link.aid_request_id = ar.id
             )
        ) AS movements GROUP BY category_id"
    );
    $used->execute([$itemId, $itemId, $itemId]);
    foreach ($used->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $categoryId = (int) $row['category_id'];
        $balances[$categoryId] = ($balances[$categoryId] ?? 0) - (int) $row['amount'];
    }

    $returns = $database->prepare(
        "SELECT ar.spectacle_category_id, SUM(ret.quantity) AS amount
         FROM item_returns ret
         JOIN distributions distribution ON distribution.id = ret.distribution_id
         JOIN aid_requests ar ON ar.id = distribution.aid_request_id
         WHERE ar.item_id = ? AND ar.spectacle_category_id IS NOT NULL
           AND ret.reusable = 1 AND ret.restore_to = 'central-stock'
           AND ret.stock_review_status = 'accepted'
         GROUP BY ar.spectacle_category_id"
    );
    $returns->execute([$itemId]);
    foreach ($returns->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $categoryId = (int) $row['spectacle_category_id'];
        $balances[$categoryId] = ($balances[$categoryId] ?? 0) + (int) $row['amount'];
    }
    return $balances;
}

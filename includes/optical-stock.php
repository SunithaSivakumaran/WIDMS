<?php
declare(strict_types=1);

/** Return a stable key for a signed prescription power. */
function widmsPowerKey(float $power): string
{
    return number_format(round($power, 2), 2, '.', '');
}

/** Identify Contact Lenses for their quantity-only Central Stock routing. */
function widmsIsOpticalItem(string $itemName, string $variety = '', string $category = ''): bool
{
    return (bool) preg_match(
        '/contact\s*lens/i',
        trim($itemName)
    );
}

/**
 * Calculate remaining Central Stock for every signed power of an optical item.
 *
 * Received quantities come from the immutable receipt power breakdown. Active
 * beneficiary-linked goods requests are treated as reservations, including
 * dispatched requests, because those units have left Central Stock. Direct
 * optical distributions that predate this workflow are also deducted.
 *
 * @return array<string,int> Map such as ['-2.00' => 10, '4.00' => 6].
 */
function widmsOpticalPowerBalances(PDO $database, int $itemId, bool $lockRows = false): array
{
    if ($itemId < 1) {
        return [];
    }

    $receiptSql =
        "SELECT id, quantity, power, power_breakdown
         FROM stock_receipts
         WHERE item_id = :item_id AND stock_destination = 'central'
         ORDER BY id" . ($lockRows ? ' FOR UPDATE' : '');
    $receiptStatement = $database->prepare($receiptSql);
    $receiptStatement->execute(['item_id' => $itemId]);

    $balances = [];
    foreach ($receiptStatement->fetchAll() as $receipt) {
        $breakdown = json_decode((string) ($receipt['power_breakdown'] ?? ''), true);
        if (is_array($breakdown) && $breakdown !== []) {
            foreach ($breakdown as $entry) {
                if (!is_array($entry) || !isset($entry['power'], $entry['count'])) {
                    continue;
                }
                $magnitude = filter_var($entry['power'], FILTER_VALIDATE_FLOAT);
                $count = filter_var($entry['count'], FILTER_VALIDATE_INT);
                if ($magnitude === false || $count === false || $magnitude < 0 || $count < 1) {
                    continue;
                }
                $signedPower = ((string) ($entry['sign'] ?? '+') === '-' ? -1 : 1) * (float) $magnitude;
                $key = widmsPowerKey($signedPower);
                $balances[$key] = ($balances[$key] ?? 0) + (int) $count;
            }
            continue;
        }

        if ($receipt['power'] !== null) {
            $key = widmsPowerKey((float) $receipt['power']);
            $balances[$key] = ($balances[$key] ?? 0) + max(0, (int) $receipt['quantity']);
        }
    }

    $reservationSql =
        "SELECT ar.prescribed_power, SUM(ar.quantity) AS reserved_quantity
         FROM goods_request_aid_requests link
         JOIN goods_requests goods ON goods.id = link.goods_request_id
         JOIN aid_requests ar ON ar.id = link.aid_request_id
         WHERE ar.item_id = :item_id
           AND ar.prescribed_power IS NOT NULL
           AND goods.status IN ('pending-admin-approval', 'approved-awaiting-dispatch', 'dispatched')
         GROUP BY ar.prescribed_power" . ($lockRows ? ' FOR UPDATE' : '');
    $reservationStatement = $database->prepare($reservationSql);
    $reservationStatement->execute(['item_id' => $itemId]);
    foreach ($reservationStatement->fetchAll() as $reservation) {
        $key = widmsPowerKey((float) $reservation['prescribed_power']);
        $balances[$key] = ($balances[$key] ?? 0) - (int) $reservation['reserved_quantity'];
    }

    // Stock released to an Administrator is no longer in Central Stock, even
    // before the final beneficiary handover is recorded. Once distributed,
    // the normal distribution deduction below takes over instead.
    $adminReleaseStatement = $database->prepare(
        "SELECT ar.prescribed_power, SUM(ar.quantity) AS released_quantity
         FROM admin_direct_releases release_record
         JOIN aid_requests ar ON ar.id = release_record.aid_request_id
         WHERE ar.item_id = :item_id AND ar.prescribed_power IS NOT NULL
           AND release_record.status = 'released-to-admin'
         GROUP BY ar.prescribed_power"
    );
    $adminReleaseStatement->execute(['item_id' => $itemId]);
    foreach ($adminReleaseStatement->fetchAll() as $release) {
        $key = widmsPowerKey((float) $release['prescribed_power']);
        $balances[$key] = ($balances[$key] ?? 0) - (int) $release['released_quantity'];
    }

    $legacyStatement = $database->prepare(
        'SELECT ar.prescribed_power, SUM(distribution.quantity) AS distributed_quantity
         FROM distributions distribution
         JOIN aid_requests ar ON ar.id = distribution.aid_request_id
         WHERE ar.item_id = :item_id
           AND ar.prescribed_power IS NOT NULL
           AND NOT EXISTS (
               SELECT 1 FROM goods_request_aid_requests link
               WHERE link.aid_request_id = ar.id
           )
         GROUP BY ar.prescribed_power'
    );
    $legacyStatement->execute(['item_id' => $itemId]);
    foreach ($legacyStatement->fetchAll() as $distribution) {
        $key = widmsPowerKey((float) $distribution['prescribed_power']);
        $balances[$key] = ($balances[$key] ?? 0) - (int) $distribution['distributed_quantity'];
    }

    // A reusable optical item returned by a Subject Officer is back in Central
    // Stock at its original signed power. Officer-pool returns stay out of this
    // balance, because they are available only in that SSO's pool.
    $returnStatement = $database->prepare(
        "SELECT ar.prescribed_power, SUM(ret.quantity) AS returned_quantity
         FROM item_returns ret
         JOIN distributions distribution ON distribution.id = ret.distribution_id
         JOIN aid_requests ar ON ar.id = distribution.aid_request_id
         WHERE ar.item_id = :item_id
           AND ar.prescribed_power IS NOT NULL
           AND ret.reusable = 1
           AND ret.restore_to = 'central-stock'
           AND ret.stock_review_status = 'accepted'
         GROUP BY ar.prescribed_power"
    );
    $returnStatement->execute(['item_id' => $itemId]);
    foreach ($returnStatement->fetchAll() as $return) {
        $key = widmsPowerKey((float) $return['prescribed_power']);
        $balances[$key] = ($balances[$key] ?? 0) + (int) $return['returned_quantity'];
    }

    ksort($balances, SORT_NATURAL);
    return $balances;
}

function widmsOpticalPowerAvailable(
    PDO $database,
    int $itemId,
    float $power,
    bool $lockRows = false
): int {
    $balances = widmsOpticalPowerBalances($database, $itemId, $lockRows);
    return max(0, (int) ($balances[widmsPowerKey($power)] ?? 0));
}

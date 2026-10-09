<?php
declare(strict_types=1);

require_once __DIR__ . '/spectacle-categories.php';

/**
 * Use the same role-shared stock and request checks for the bundle page,
 * dashboard and sidebar. Individual submission history remains user-scoped.
 * "available" means the request has a selectable checkbox on the bundle page.
 *
 * @return array{available: array<int, array<string, mixed>>, waiting: array<int, array<string, mixed>>}
 */
function widmsApprovedAidBundleAvailability(PDO $database): array
{
    $statement = $database->prepare(
        "SELECT ar.id, ar.quantity, ar.spectacle_category_id, ar.created_at,
                b.full_name AS beneficiary_name, b.nic, b.elders_card_number,
                i.id AS item_id, i.item_name, i.variety, i.quantity AS central_stock,
                c.name AS category_name, spectacle_type.name AS spectacle_category_name,
                ds.name AS division_name, d.name AS district_name,
                submitter.full_name AS submitter_name, submitter.username AS submitter_username,
                submitter.role AS submitter_role
         FROM aid_requests ar
         JOIN beneficiaries b ON b.id = ar.beneficiary_id
         JOIN inventory_items i ON i.id = ar.item_id
         JOIN item_categories c ON c.id = i.category_id
         LEFT JOIN spectacle_categories spectacle_type ON spectacle_type.id = ar.spectacle_category_id
         JOIN users submitter ON submitter.id = ar.submitted_by
         JOIN ds_divisions ds ON ds.id = b.ds_division_id
         JOIN districts d ON d.id = ds.district_id
         WHERE submitter.role IN ('subject-officer', 'social-service-officer', 'admin')
           AND ar.status = 'approved'
           AND NOT EXISTS (SELECT 1 FROM distributions distribution WHERE distribution.aid_request_id = ar.id)
           AND NOT EXISTS (SELECT 1 FROM goods_fulfillments fulfillment WHERE fulfillment.aid_request_id = ar.id)
           AND NOT EXISTS (SELECT 1 FROM admin_direct_releases direct_release WHERE direct_release.aid_request_id = ar.id)
           AND NOT EXISTS (SELECT 1 FROM goods_requests goods WHERE goods.aid_request_id = ar.id AND goods.status <> 'rejected')
           AND NOT EXISTS (
               SELECT 1
               FROM goods_request_aid_requests link
               JOIN goods_requests goods ON goods.id = link.goods_request_id
               WHERE link.aid_request_id = ar.id AND goods.status <> 'rejected'
           )
           AND NOT EXISTS (
               SELECT 1 FROM users sso
               JOIN division_pools pool ON pool.ds_division_id = sso.ds_division_id AND pool.item_id = ar.item_id
               WHERE sso.role = 'social-service-officer' AND sso.status = 'active'
                 AND sso.ds_division_id = b.ds_division_id AND b.status = 'active'
                 AND i.can_sso_distribute = 1
                 AND (pool.allocated - pool.distributed + pool.reused) >= ar.quantity
           )
         ORDER BY ar.created_at, ar.id"
    );
    $statement->execute();

    $available = [];
    $waiting = [];
    $spectacleBalancesByItem = [];
    $reservedByItem = [];
    $reservedStatement = $database->prepare(
        "SELECT COALESCE(SUM(quantity), 0) FROM goods_requests
         WHERE item_id = ? AND status IN ('pending-admin-approval', 'approved-awaiting-dispatch')"
    );

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $request) {
        $itemId = (int) $request['item_id'];
        if (!array_key_exists($itemId, $reservedByItem)) {
            $reservedStatement->execute([$itemId]);
            $reservedByItem[$itemId] = (int) $reservedStatement->fetchColumn();
        }
        $request['central_available'] = max(0, (int) $request['central_stock'] - $reservedByItem[$itemId]);
        $request['is_spectacles'] = widmsIsSpectacleItem((string) $request['item_name']);
        $request['spectacle_available'] = null;
        if ($request['is_spectacles'] && $request['spectacle_category_id'] !== null) {
            $spectacleBalancesByItem[$itemId] ??= widmsSpectacleCategoryBalances($database, $itemId);
            $request['spectacle_available'] = max(
                0,
                (int) ($spectacleBalancesByItem[$itemId][(int) $request['spectacle_category_id']] ?? 0)
            );
        }

        $ready = $request['central_available'] >= (int) $request['quantity']
            && (!$request['is_spectacles'] || ($request['spectacle_available'] ?? 0) >= (int) $request['quantity']);
        if ($ready) {
            $available[] = $request;
        } else {
            $waiting[] = $request;
        }
    }

    return ['available' => $available, 'waiting' => $waiting];
}

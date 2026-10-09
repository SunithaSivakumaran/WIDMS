<?php
declare(strict_types=1);

/**
 * Hide an unread action alert from the bell once its action was completed elsewhere.
 * This does not mark it read or change any notification shown on a table row.
 */
function notificationActionIsPending(PDO $database, string $key): bool
{
    $sql = null;
    $parameters = [];

    if (preg_match('/^sc-(\d+)-requested$/', $key, $match)) {
        $sql = "SELECT 1 FROM spectacle_camps WHERE id = ? AND status = 'pending'";
        $parameters = [(int) $match[1]];
    } elseif (preg_match('/^sc-(\d+)-(stock-request-batch|release-needed-batch)-(.+)$/', $key, $match)) {
        $sql = 'SELECT 1 FROM spectacle_camp_stock_requests WHERE camp_id = ? AND batch_ref = ? AND status = ? LIMIT 1';
        $parameters = [(int) $match[1], $match[3], $match[2] === 'stock-request-batch' ? 'pending' : 'approved'];
    } elseif (preg_match('/^sc-(\d+)-release-needed-(\d+)$/', $key, $match)) {
        $sql = "SELECT 1 FROM spectacle_camp_stock_requests WHERE camp_id = ? AND id = ? AND status = 'approved'";
        $parameters = [(int) $match[1], (int) $match[2]];
    } elseif (preg_match('/^sc-(\d+)-received-(\d+)$/', $key, $match)) {
        $sql = "SELECT 1 FROM spectacle_camp_receipts receipt
                WHERE receipt.camp_id = ? AND receipt.id = ?
                  AND NOT EXISTS (
                    SELECT 1 FROM spectacle_camp_stock_requests request
                    WHERE request.camp_id = receipt.camp_id
                      AND request.created_at >= receipt.created_at
                      AND request.status <> 'rejected'
                  )";
        $parameters = [(int) $match[1], (int) $match[2]];
    } elseif (preg_match('/^sc-(\d+)-completed$/', $key, $match)) {
        $sql = "SELECT 1 FROM (
                    SELECT spectacle_category_id, COUNT(*) quantity
                    FROM spectacle_camp_participants
                    WHERE camp_id = ? AND status = 'approved' AND spectacle_category_id IS NOT NULL
                    GROUP BY spectacle_category_id
                ) selected
                LEFT JOIN (
                    SELECT COALESCE(line.spectacle_category_id, receipt.spectacle_category_id) category_id,
                           SUM(COALESCE(line.quantity, receipt.quantity)) quantity
                    FROM spectacle_camp_receipts receipt
                    LEFT JOIN stock_receipt_spectacle_lines line ON line.stock_receipt_id = receipt.stock_receipt_id
                    WHERE receipt.camp_id = ?
                    GROUP BY COALESCE(line.spectacle_category_id, receipt.spectacle_category_id)
                ) received ON received.category_id = selected.spectacle_category_id
                WHERE selected.quantity > COALESCE(received.quantity, 0) LIMIT 1";
        $parameters = [(int) $match[1], (int) $match[1]];
    } elseif (preg_match('/^sc-(\d+)-released(?:-batch-.+|-\d+)$/', $key, $match)) {
        $sql = "SELECT 1 FROM spectacle_camp_participants participant
                WHERE participant.camp_id = ? AND participant.status = 'approved'
                  AND NOT EXISTS (
                    SELECT 1 FROM spectacle_camp_distributions distribution
                    WHERE distribution.participant_id = participant.id
                  ) LIMIT 1";
        $parameters = [(int) $match[1]];
    } elseif (preg_match('/^aid-bundle-(.+)$/', $key, $match)) {
        $sql = "SELECT 1 FROM goods_requests WHERE LOWER(request_batch_ref) = ? AND status = 'pending-admin-approval' LIMIT 1";
        $parameters = [$match[1]];
    } elseif (preg_match('/^goods-release-(\d+)-\d+$/', $key, $match)) {
        $sql = "SELECT 1 FROM goods_requests WHERE id = ? AND status = 'approved-awaiting-dispatch'";
        $parameters = [(int) $match[1]];
    } elseif (preg_match('/^admin-direct-release-(\d+)$/', $key, $match)) {
        $sql = "SELECT 1 FROM admin_direct_releases WHERE aid_request_id = ? AND status = 'awaiting-store-keeper'";
        $parameters = [(int) $match[1]];
    } elseif (preg_match('/^admin-direct-ready-(\d+)$/', $key, $match)) {
        $sql = "SELECT 1 FROM admin_direct_releases WHERE aid_request_id = ? AND status = 'released-to-admin'";
        $parameters = [(int) $match[1]];
    } elseif (preg_match('/^return-review-(\d+)$/', $key, $match)) {
        $sql = "SELECT 1 FROM item_returns WHERE id = ? AND stock_review_status = 'pending'";
        $parameters = [(int) $match[1]];
    } elseif (preg_match('/^approved-(?:aid-ready|subject-distribution|optical-routing|aid-stock-needed)-(\d+)$/', $key, $match)) {
        $sql = "SELECT 1 FROM aid_requests ar WHERE ar.id = ? AND ar.status = 'approved'
                AND NOT EXISTS (SELECT 1 FROM distributions d WHERE d.aid_request_id = ar.id)
                AND NOT EXISTS (SELECT 1 FROM admin_direct_releases dr WHERE dr.aid_request_id = ar.id)
                AND NOT EXISTS (SELECT 1 FROM goods_fulfillments f WHERE f.aid_request_id = ar.id)
                AND NOT EXISTS (SELECT 1 FROM goods_requests g WHERE g.aid_request_id = ar.id AND g.status <> 'rejected')
                AND NOT EXISTS (
                    SELECT 1 FROM goods_request_aid_requests link
                    JOIN goods_requests g ON g.id = link.goods_request_id
                    WHERE link.aid_request_id = ar.id AND g.status <> 'rejected'
                )";
        $parameters = [(int) $match[1]];
    } else {
        // Decision and progress updates are information, not unfinished actions.
        return true;
    }

    $statement = $database->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchColumn() !== false;
}

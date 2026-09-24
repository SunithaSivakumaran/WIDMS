USE widms;

-- Rejected optical release requests must remain visible in history while the
-- same beneficiary aid request can be selected again later. Active duplicates
-- are prevented by transactional application validation.
SET @has_aid_link_index := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'goods_request_aid_requests'
      AND index_name = 'idx_goods_request_aid'
);
SET @add_aid_link_index := IF(
    @has_aid_link_index = 0,
    'ALTER TABLE goods_request_aid_requests ADD INDEX idx_goods_request_aid (aid_request_id)',
    'SELECT 1'
);
PREPARE widms_add_aid_link_index FROM @add_aid_link_index;
EXECUTE widms_add_aid_link_index;
DEALLOCATE PREPARE widms_add_aid_link_index;

SET @has_unique_aid_link := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'goods_request_aid_requests'
      AND index_name = 'uq_goods_batch_aid_request'
      AND non_unique = 0
);
SET @drop_unique_aid_link := IF(
    @has_unique_aid_link > 0,
    'ALTER TABLE goods_request_aid_requests DROP INDEX uq_goods_batch_aid_request',
    'SELECT 1'
);
PREPARE widms_drop_unique_aid_link FROM @drop_unique_aid_link;
EXECUTE widms_drop_unique_aid_link;
DEALLOCATE PREPARE widms_drop_unique_aid_link;

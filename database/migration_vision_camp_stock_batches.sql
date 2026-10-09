-- Keep existing single-type requests readable; new camp-level requests share a batch reference.
ALTER TABLE spectacle_camp_stock_requests
    ADD COLUMN batch_ref CHAR(32) NULL AFTER camp_id,
    ADD INDEX idx_scsr_batch (camp_id, batch_ref);

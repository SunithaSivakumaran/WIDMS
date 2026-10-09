-- Existing returns were already processed under the former immediate-restock flow.
-- Only new good returns received by a Subject Officer need Store Keeper acceptance.
ALTER TABLE item_returns
    ADD COLUMN IF NOT EXISTS stock_review_status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'accepted' AFTER restore_to,
    ADD COLUMN IF NOT EXISTS stock_reviewed_by INT UNSIGNED NULL AFTER stock_review_status,
    ADD COLUMN IF NOT EXISTS stock_reviewed_at DATETIME NULL AFTER stock_reviewed_by,
    ADD COLUMN IF NOT EXISTS stock_review_note VARCHAR(500) NULL AFTER stock_reviewed_at;

CREATE INDEX IF NOT EXISTS idx_return_stock_review ON item_returns (stock_review_status, restore_to, processed_at);

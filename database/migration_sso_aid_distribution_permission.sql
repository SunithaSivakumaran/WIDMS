-- Existing aid keeps its current SSO distribution behavior. New and edited
-- aid items can explicitly opt out of SSO distribution.
ALTER TABLE inventory_items
    ADD COLUMN IF NOT EXISTS can_sso_distribute TINYINT(1) NOT NULL DEFAULT 1 AFTER is_returnable;

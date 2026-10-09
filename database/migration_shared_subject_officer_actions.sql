-- Subject Officers share pending work, but completed actions retain the
-- identity of the officer who actually performed them.
ALTER TABLE goods_requests
    ADD COLUMN IF NOT EXISTS allocated_to_sso_by INT UNSIGNED NULL AFTER allocated_to_sso_at;

ALTER TABLE goods_fulfillments
    ADD COLUMN IF NOT EXISTS handed_to_sso_by INT UNSIGNED NULL AFTER handed_to_sso_at;

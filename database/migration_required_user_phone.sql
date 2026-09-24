USE widms;

-- Do not invent contact details. Existing missing numbers must be supplied
-- before this migration runs; the constraint fails safely if any remain.
ALTER TABLE users MODIFY COLUMN phone VARCHAR(25) NOT NULL;
ALTER TABLE users ADD CONSTRAINT IF NOT EXISTS chk_users_phone_required
    CHECK (phone REGEXP '[0-9]' AND CHAR_LENGTH(TRIM(phone)) >= 7);
ALTER TABLE registration_requests ADD CONSTRAINT IF NOT EXISTS chk_registration_phone_required
    CHECK (phone REGEXP '[0-9]' AND CHAR_LENGTH(TRIM(phone)) >= 7);

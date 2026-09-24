-- Administrative direct issues are supplied from Central Stock, not an SSO pool.
-- Preserve the existing enum values for historical distributions.
ALTER TABLE distributions
    MODIFY source ENUM('officer-pool', 'vision-camp', 'central-stock')
    NOT NULL DEFAULT 'officer-pool';

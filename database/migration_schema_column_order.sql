USE widms;

-- Match the established column order as well as the column definitions.
-- Existing values, indexes and relationships remain unchanged.
ALTER TABLE disability_aid_items
    MODIFY COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER restriction_months;

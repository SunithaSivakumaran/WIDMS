-- Existing participant records keep an unknown gender; never invent personal data.
ALTER TABLE spectacle_camp_participants
    ADD COLUMN IF NOT EXISTS gender ENUM('male','female','other') NULL AFTER full_name;

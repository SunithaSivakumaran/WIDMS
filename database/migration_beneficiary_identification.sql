USE widms;

ALTER TABLE beneficiaries
    ADD COLUMN IF NOT EXISTS elders_card_number VARCHAR(30) NULL AFTER nic;

ALTER TABLE beneficiaries
    ADD UNIQUE INDEX IF NOT EXISTS uq_beneficiaries_elders_card
    (elders_card_number);

ALTER TABLE aid_requests
    ADD COLUMN IF NOT EXISTS identification_method
    ENUM('nic', 'elders-card', 'both')
    NULL AFTER beneficiary_id;
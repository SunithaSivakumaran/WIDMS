USE widms;

ALTER TABLE registration_requests
    ADD COLUMN IF NOT EXISTS sms_status ENUM('not-sent','sending','sent','failed','unknown') NOT NULL DEFAULT 'not-sent',
    ADD COLUMN IF NOT EXISTS sms_error_code VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS sms_gateway_reference VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS sms_sent_at DATETIME NULL;

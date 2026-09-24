USE widms;

-- Direct requests may be made for applicants whose date of birth is unknown.
ALTER TABLE beneficiaries
    MODIFY date_of_birth DATE NULL;

-- Only trusted Admin/Subject Officer forms can set this flag. Admin approval
-- uses it to bypass active-request and waiting-period eligibility rules.
ALTER TABLE aid_requests
    ADD COLUMN IF NOT EXISTS eligibility_override TINYINT(1) NOT NULL DEFAULT 0 AFTER notes,
    ADD COLUMN IF NOT EXISTS direct_request_document VARCHAR(255) NULL AFTER eligibility_override;

USE widms;

-- Service-centre residents have a fixed home location rather than a GN Division.
ALTER TABLE beneficiaries
    MODIFY gn_division_id INT UNSIGNED NULL;

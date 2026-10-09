-- Existing camp batches keep their historical date and time; new requests record the venue.
ALTER TABLE spectacle_camp_stock_requests
    ADD COLUMN planned_distribution_place VARCHAR(255) NULL AFTER planned_distribution_time;

-- Older camp requests and handovers retain their recorded dates; new records include a time.
ALTER TABLE spectacle_camp_stock_requests
    ADD COLUMN planned_distribution_time TIME NULL AFTER planned_distribution_date;

ALTER TABLE spectacle_camp_distributions
    ADD COLUMN distribution_time TIME NULL AFTER distribution_date;

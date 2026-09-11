USE widms;

ALTER TABLE correction_requests
    MODIFY COLUMN error_type ENUM(
        'wrong-unit-cost',
        'wrong-quantity',
        'wrong-bill-number',
        'wrong-supplier',
        'wrong-date',
        'wrong-cost',
        'wrong-item',
        'wrong-payment-amount',
        'wrong-check-number',
        'wrong-payment-date',
        'other'
    ) NOT NULL;

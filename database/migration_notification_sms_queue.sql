USE widms;

CREATE TABLE IF NOT EXISTS notification_sms_state (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    activated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_sms_outbox (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    notification_key VARCHAR(160) NOT NULL,
    message VARCHAR(500) NOT NULL,
    status ENUM('queued','sending','sent','failed','unknown','skipped') NOT NULL DEFAULT 'queued',
    error_code VARCHAR(40) NULL,
    gateway_reference VARCHAR(100) NULL,
    attempted_at DATETIME NULL,
    accepted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notification_sms_recipient (user_id, notification_key),
    KEY idx_notification_sms_queue (status, id),
    CONSTRAINT fk_notification_sms_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The same events shown in the notification bell, without recipient/beneficiary
-- names, phone numbers, addresses or medical details in the SMS summary.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW notification_sms_candidates AS
SELECT n.user_id, CONCAT('user-notification-',n.id) notification_key,
       n.category, CONCAT('N-',n.id) reference_code, n.created_at occurred_at
FROM user_notifications n JOIN users u ON u.id=n.user_id AND u.status='active'
WHERE n.target_url NOT LIKE '%page=vision-camp%'
  AND n.target_url NOT LIKE '%page=contact-lens-orders%'
  AND n.target_url NOT LIKE '%page=pending-lens-handover%'
UNION ALL
SELECT u.id,CONCAT('registration-',r.id),'User registration',CONCAT('REG-',r.id),r.created_at
FROM registration_requests r JOIN users u ON u.role='admin' AND u.status='active'
WHERE r.status='pending'
UNION ALL
SELECT u.id,CONCAT('correction-',c.id),'Correction request',CONCAT('CR-',c.id),c.created_at
FROM correction_requests c JOIN users u ON u.role='admin' AND u.status='active'
WHERE c.status='pending'
UNION ALL
SELECT u.id,CONCAT('aid-',a.id),'Aid request',CONCAT('AR-',a.id),a.created_at
FROM aid_requests a JOIN users u ON u.role='admin' AND u.status='active'
WHERE a.status='pending'
UNION ALL
SELECT u.id,CONCAT('goods-',g.first_id),'Stock quota request',g.batch_reference,g.created_at
FROM (
    SELECT MIN(id) first_id,COALESCE(NULLIF(request_batch_ref,''),CONCAT('GR-',id)) batch_reference,
           MIN(created_at) created_at,requested_by
    FROM goods_requests WHERE status='pending-admin-approval'
    GROUP BY COALESCE(NULLIF(request_batch_ref,''),CONCAT('GR-',id)),requested_by
) g JOIN users u ON u.role='admin' AND u.status='active'
UNION ALL
SELECT u.id,CONCAT('dispatch-',g.id),'Approved stock quota',CONCAT('GR-',g.id),g.approved_at
FROM goods_requests g JOIN users u ON u.role='store-keeper' AND u.status='active'
WHERE g.status='approved-awaiting-dispatch'
UNION ALL
SELECT u.id,CONCAT('payment-',s.id),'Supplier payment reminder',CONCAT('BAT-',s.id),s.created_at
FROM stock_receipts s JOIN users u ON u.role='store-keeper' AND u.status='active'
WHERE s.balance_amount>0
UNION ALL
SELECT u.id,CONCAT('fulfillment-',f.id),'Goods received',CONCAT('FUL-',f.id),f.created_at
FROM goods_fulfillments f JOIN users u ON u.id=f.subject_officer_id AND u.role='subject-officer' AND u.status='active'
WHERE f.status='with-subject-officer'
UNION ALL
SELECT u.id,CONCAT('sso-handover-',f.id),'Pending handover',CONCAT('FUL-',f.id),COALESCE(f.handed_to_sso_at,f.created_at)
FROM goods_fulfillments f JOIN users u ON u.id=f.sso_id AND u.role='social-service-officer' AND u.status='active'
WHERE f.status='pending-sso-handover';

-- Snapshot existing alerts only on first activation. These are NOT SMS jobs.
-- This protects old alerts (including same-second timestamps) against replay.
INSERT INTO notification_sms_outbox (user_id,notification_key,message,status,error_code)
SELECT c.user_id,c.notification_key,'','skipped','before-activation'
FROM notification_sms_candidates c
WHERE NOT EXISTS (SELECT 1 FROM notification_sms_state WHERE id=1)
ON DUPLICATE KEY UPDATE id=id;
INSERT IGNORE INTO notification_sms_state (id) VALUES (1);

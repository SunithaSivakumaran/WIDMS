-- Install defaults only when missing; never overwrite an administrator's
-- settings on an existing installation.
INSERT IGNORE INTO system_settings
    (setting_key, setting_value, setting_type, setting_group, description)
VALUES
    ('low_stock_threshold', '10', 'integer', 'general', 'Minimum stock quantity before a low-stock alert is triggered'),
    ('session_timeout_minutes', '30', 'integer', 'general', 'Minutes of inactivity before automatic logout'),
    ('max_failed_logins', '5', 'integer', 'general', 'Maximum failed login attempts before an account is locked'),
    ('audit_retention_years', '5', 'integer', 'general', 'Minimum years audit logs are retained'),
    ('notify_low_stock', 'true', 'boolean', 'notification', 'Send alert when item stock drops below threshold'),
    ('notify_pending_approval', 'true', 'boolean', 'notification', 'Notify Admin of pending registration or request'),
    ('notify_payment_due', 'true', 'boolean', 'notification', 'Alert Store Keeper of outstanding supplier payments'),
    ('email_notifications', 'true', 'boolean', 'notification', 'Send email notifications in addition to in-system alerts');

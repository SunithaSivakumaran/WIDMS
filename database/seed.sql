USE widms;

INSERT INTO users (full_name, username, password_hash, role, status) VALUES
('System Administrator', 'admin@widms.gov', '$2y$10$X0FwRht7Q0C.JqXyIflzpeh4Y3ELemZy701MZT6m9blGcTsOioY2i', 'admin', 'active'),
('Subject Officer', 'subject@widms.gov', '$2y$10$Eb3LEL4FIqtTe1KGFcXsCOttfqZYJ5kln1nOxHGArw34pdVqFUi/m', 'subject-officer', 'active'),
('Store Keeper', 'store@widms.gov', '$2y$10$ATASnZ59iJlNsRazDaC.aulIweRZb1vMzzcSqGCaAC7JyGDxdOhFi', 'store-keeper', 'active'),
('Social Service Officer', 'social@widms.gov', '$2y$10$aDf7T0XuX15L.ErDBSBe2OO68H/o0af8wOsmbcMfpPYEzNmky4Btq', 'social-service-officer', 'active')
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    role = VALUES(role),
    status = VALUES(status);

INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_group, description) VALUES
('low_stock_threshold', '10', 'integer', 'general', 'Minimum stock quantity before a low-stock alert is triggered'),
('session_timeout_minutes', '30', 'integer', 'general', 'Minutes of inactivity before automatic logout'),
('max_failed_logins', '5', 'integer', 'general', 'Maximum failed login attempts before an account is locked'),
('audit_retention_years', '5', 'integer', 'general', 'Minimum years audit logs are retained'),
('notify_low_stock', 'true', 'boolean', 'notification', 'Send alert when item stock drops below threshold'),
('notify_pending_approval', 'true', 'boolean', 'notification', 'Notify Admin of pending registration or request'),
('notify_payment_due', 'true', 'boolean', 'notification', 'Alert Store Keeper of outstanding supplier payments'),
('email_notifications', 'true', 'boolean', 'notification', 'Send email notifications in addition to in-system alerts')
ON DUPLICATE KEY UPDATE
    setting_type = VALUES(setting_type),
    setting_group = VALUES(setting_group),
    description = VALUES(description);

INSERT INTO suppliers (company_name) VALUES
('ABC Medical Co. Ltd'),
('Vision Care Co. Ltd'),
('HealthTech Pvt Ltd')
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name);

INSERT INTO inventory_items (item_name, category, variety) VALUES
('Wheelchair', 'Mobility Aid', 'Standard'),
('Glasses', 'Vision Aid', 'Standard'),
('Hearing Aid', 'Medical Aid', 'Behind-the-ear'),
('Crutches', 'Mobility Aid', 'Adjustable')
ON DUPLICATE KEY UPDATE category = VALUES(category);

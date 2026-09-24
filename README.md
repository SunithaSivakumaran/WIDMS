# Welfare Inventory & Distribution Management System (WIDMS)

WIDMS is implemented with HTML, CSS, JavaScript, Bootstrap, PHP, and MySQL. It includes authentication, role-specific dashboards, beneficiary and geographic master data, aid and goods workflows, supplier stock and payments, officer pools, returns, vision camps, contact-lens workflows, corrections, and audit history.

## Database setup

1. Start MariaDB (as bundled with XAMPP) and make sure the configured account can create a database.
2. Run `php database/migrate.php` from the project root. It creates `widms` when missing, installs all required tables, and prints a one-time random password for the first administrator on a new installation.
3. Run `php database/verify.php`.

Every account requires a phone number. On a new installation, enter the first administrator's mobile number when prompted, or set `WIDMS_ADMIN_PHONE` before running migrations for unattended setup. Existing users' numbers are never replaced automatically.

The XAMPP defaults are in `config/database.php`. On another machine, set `WIDMS_DB_HOST`, `WIDMS_DB_PORT`, `WIDMS_DB_NAME`, `WIDMS_DB_USER`, and `WIDMS_DB_PASS` if they differ. Migration does not copy records from another computer; restore a database backup separately if you need existing users or operational data. It never runs the retired demo-data cleanup scripts.

The migration runner is idempotent and includes the entities and relationships from the WIDMS ER diagram. See `database/README.md` for the table-name mappings used by the application.

## Account approval SMS

New account applications require a unique Salary Number (1–30 digits, with leading zeros preserved). After approval, the login is `swpcs` followed by that number, for example `swpcs00123`. The approval SMS and email include the username and instructions to use the password entered at signup. Passwords remain hashed; they are never stored as readable text or sent by SMS. Existing accounts and older pending requests retain their email usernames. Salary numbers are reserved even on rejected applications to prevent duplicate registrations.

After an Admin approves an account request, WIDMS sends the applicant a confirmation SMS using the phone number recorded on the application. SMS is sent only after the active account has been committed. Rejections do not send an account-created SMS. Existing email notifications continue independently.

Copy `config/sms.local.example.php` to `config/sms.local.php` and enter the Textit.biz credentials, or set `WIDMS_SMS_ENABLED=true`, `WIDMS_SMS_USER_ID`, and `WIDMS_SMS_PASSWORD`. Keep the local file private; Git ignores it. Configure these credentials again when deploying to another server. Run `php database/migrate.php` to install the SMS status columns.

The integration uses HTTPS POST according to the [Textit.biz Basic API](https://www.textit.biz/integration_Basic_HTTP_API.php). Approval remains valid if notification fails. `registration_requests.sms_status` records `sent` when the gateway accepts the SMS, not when a handset confirms delivery. Failed, interrupted, or uncertain attempts are not automatically resent; check the gateway report before any manual resend to avoid duplicates.

`php scripts/test-sms.php` checks configuration without contacting the gateway. `php tests/registration-sms.php` runs isolated tests without sending messages or using the project database. To send a real test message to a number you control, run `php scripts/test-sms.php --send 07XXXXXXXX`.

## System notification SMS

New in-system alerts are also queued for each active recipient's saved phone number, including Admin pending requests, Store Keeper dispatch/payment alerts, Subject Officer goods received, SSO handovers, and stored workflow notifications. SMS contains the role, category and reference; beneficiary names, prescriptions and detailed notification bodies remain inside WIDMS. Accounts sharing a phone receive separate notifications addressed to their respective roles.

`migration_notification_sms_queue.sql` installs the queue, source view, and activation baseline. Existing alerts are skipped, not texted retroactively. Successful CSRF-validated web form submissions collect and send a bounded batch after database transactions finish, including submissions that redirect. Opening pages and reading the notification bell do not send SMS. Account approval SMS continues separately.

For dependable background delivery and larger queues, schedule `php scripts/send-notification-sms.php --send` every minute using Windows Task Scheduler or cron. This worker sends real SMS; it is not a test. `php scripts/send-notification-sms.php --check` only reports queue counts. No scheduler is installed automatically by the project. When copying the project to another server, configure the gateway credentials and scheduled worker there as well.

Jobs are claimed once. Failed, uncertain or interrupted (`sending`) jobs are not automatically retried because the gateway may already have delivered them. Approval and stock operations stay committed even when SMS fails. `php tests/notification-sms.php` tests routing and duplicate protection with connection-local temporary tables and fake senders; it does not contact the gateway or modify operational data.

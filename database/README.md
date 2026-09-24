# WIDMS database setup

Run `php database/migrate.php` from the project root to install or update the complete schema. The runner creates the configured database if needed, applies schema/workflow migrations in dependency order, and records completed files in `widms_schema_migrations`. On a new installation it creates one administrator with a random password printed once to the terminal; save that password and change it after signing in. It does not install the fixed-password demo accounts in `seed.sql` or run the retired data-clearing migrations.

MariaDB must already be running, and the configured account needs permission to create the database. The SQL migrations use MariaDB syntax and are not guaranteed on other database engines. Connection defaults are in `config/database.php`; `WIDMS_DB_HOST`, `WIDMS_DB_PORT`, `WIDMS_DB_NAME`, `WIDMS_DB_USER`, and `WIDMS_DB_PASS` can override them per installation. Existing operational records are not copied from another machine by migration; use a database backup/restore for that. Southern Province geography remains a separate optional import because it downloads an external dataset and can reconcile existing rows.

Run `php database/verify.php` to check required tables and core stock/workflow invariants. Operational tables are intentionally not seeded with dummy records.

Run `php database/migrate.php --check` to validate the migration file list without connecting to or changing a database.

All users require a phone number. `migration_required_user_phone.sql` adds the database constraints; it never fills missing numbers with invented values. For a new installation, the migration runner asks for the first administrator's mobile number in an interactive terminal. For unattended installation, set `WIDMS_ADMIN_PHONE` to the actual administrator's mobile number before running the migration. Existing installations do not need this setting when users already exist. Existing accounts with missing numbers must be corrected with verified contact details before the constraint migration can succeed.

Run `php database/compare-migrations.php` to compare the current structure against a fresh database built by the registered migrations. It checks all table/view definitions, column order/types/defaults/nullability, collations, indexes, foreign keys, and check constraints, and flags any triggers, routines, or events for further review. Row data and auto-increment counters are not compared. The command creates a randomly named disposable database through `migrate.php`, removes only that comparison database afterward, and never modifies the source database. The database account needs CREATE/DROP permissions for this check. Exit code 0 means the structures match; 1 means differences were found or the check failed.

`migration_schema_structure_alignment.sql` preserves the legacy `widms_data_migrations` table and makes the existing disability-type collation explicit. It does not run retired cleanup scripts or copy operational data.

`migration_schema_column_order.sql` preserves the established column order in `disability_aid_items` without changing field values.

The schema is aligned with the WIDMS ER diagram. Existing application-oriented names are retained where they represent the same entity (`stock_receipts` for item stock, `supplier_payments` for payments, and `activity_logs` for audit logs). `migration_er_alignment.sql` supplies the detailed vision-camp and contact-lens entities and cross-workflow references from the diagram.

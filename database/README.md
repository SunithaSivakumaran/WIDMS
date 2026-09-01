# WIDMS database setup

Run `php database/migrate.php` from the project root to install or update the complete schema. The runner applies the base schema and every idempotent workflow migration in dependency order.

Run `php database/verify.php` to check required tables and core stock/workflow invariants. Operational tables are intentionally not seeded with dummy records.

The schema is aligned with the WIDMS ER diagram. Existing application-oriented names are retained where they represent the same entity (`stock_receipts` for item stock, `supplier_payments` for payments, and `activity_logs` for audit logs). `migration_er_alignment.sql` supplies the detailed vision-camp and contact-lens entities and cross-workflow references from the diagram.

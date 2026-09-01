# Welfare Inventory & Distribution Management System (WIDMS)

WIDMS is implemented with HTML, CSS, JavaScript, Bootstrap, PHP, and MySQL. It includes authentication, role-specific dashboards, beneficiary and geographic master data, aid and goods workflows, supplier stock and payments, officer pools, returns, vision camps, contact-lens workflows, corrections, and audit history.

## Database setup

1. Create a MySQL database named `widms`.
2. Configure the connection in `config/database.php`.
3. Run `php database/migrate.php`.
4. Run `php database/verify.php`.

The migration runner is idempotent and includes the entities and relationships from the WIDMS ER diagram. See `database/README.md` for the table-name mappings used by the application.

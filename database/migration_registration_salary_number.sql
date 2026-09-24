USE widms;

-- Nullable for existing accounts/requests; do not invent salary numbers or rename logins.
ALTER TABLE users ADD COLUMN IF NOT EXISTS salary_number VARCHAR(30) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(120) NULL;
ALTER TABLE registration_requests ADD COLUMN IF NOT EXISTS salary_number VARCHAR(30) NULL;

UPDATE users SET email = username WHERE email IS NULL AND username LIKE '%@%';
CREATE UNIQUE INDEX IF NOT EXISTS uq_users_salary_number ON users (salary_number);
CREATE UNIQUE INDEX IF NOT EXISTS uq_users_email ON users (email);
CREATE UNIQUE INDEX IF NOT EXISTS uq_registration_salary_number ON registration_requests (salary_number);

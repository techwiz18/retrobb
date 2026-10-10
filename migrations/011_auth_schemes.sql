-- RetroBB 011: legacy password schemes for imported accounts.
ALTER TABLE users ADD COLUMN auth_scheme VARCHAR(20) NOT NULL DEFAULT 'modern';
ALTER TABLE users ADD COLUMN passwd_salt VARCHAR(255) NOT NULL DEFAULT '';

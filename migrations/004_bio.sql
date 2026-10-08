-- RetroBB 004: member bio (MySQL 8.0+ dialect).
-- TEXT columns cannot carry a plain DEFAULT here; keep it nullable and
-- normalise existing rows. App code treats NULL as ''.
ALTER TABLE users ADD COLUMN bio TEXT NULL;
UPDATE users SET bio='' WHERE bio IS NULL;

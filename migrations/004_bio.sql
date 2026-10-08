-- RetroBB 004: member bio for the profile editor.
-- (SQLite has no ADD COLUMN IF NOT EXISTS; the migrate runner ignores
-- "duplicate column name" so re-runs stay idempotent.)
ALTER TABLE users ADD COLUMN bio TEXT NOT NULL DEFAULT '';

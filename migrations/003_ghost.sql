-- RetroBB 003: ghost topics remember their target so they can redirect.
-- (SQLite has no ADD COLUMN IF NOT EXISTS; the migrate runner ignores
-- "duplicate column name" so re-runs stay idempotent.)
ALTER TABLE topics ADD COLUMN moved_to_id INTEGER NULL DEFAULT NULL;

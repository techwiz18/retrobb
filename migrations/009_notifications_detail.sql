-- RetroBB 009: notification detail text (e.g. warning reasons).
ALTER TABLE notifications ADD COLUMN detail VARCHAR(500) NOT NULL DEFAULT '';

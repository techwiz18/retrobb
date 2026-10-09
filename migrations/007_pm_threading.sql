-- RetroBB 007: PM reply threading (MySQL 8.0+ dialect).
ALTER TABLE pms ADD COLUMN reply_to_id INT NOT NULL DEFAULT 0;
CREATE INDEX idx_pms_reply ON pms (reply_to_id);
ALTER TABLE pm_drafts ADD COLUMN reply_to_id INT NOT NULL DEFAULT 0;

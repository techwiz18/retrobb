-- RetroBB 006: PM sent/trash/drafts (MySQL 8.0+ dialect).
ALTER TABLE pms ADD COLUMN sender_deleted TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE pms ADD COLUMN recipient_deleted TINYINT(1) NOT NULL DEFAULT 0;
CREATE INDEX idx_pms_trash ON pms (sender_deleted, recipient_deleted);
CREATE TABLE IF NOT EXISTS pm_drafts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  to_name VARCHAR(50) NOT NULL DEFAULT '',
  subject VARCHAR(120) NOT NULL DEFAULT '',
  body_bbcode MEDIUMTEXT NOT NULL,
  created_at VARCHAR(32) NOT NULL,
  updated_at VARCHAR(32) NOT NULL
);
CREATE INDEX idx_drafts_user ON pm_drafts (user_id, updated_at);

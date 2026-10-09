-- RetroBB 005: social batch — mentions/alerts, reactions, PMs (MySQL 8.0+ dialect).
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  actor_id INT NOT NULL,
  type VARCHAR(30) NOT NULL DEFAULT 'mention',
  topic_id INT NOT NULL DEFAULT 0,
  post_id INT NOT NULL DEFAULT 0,
  created_at VARCHAR(32) NOT NULL,
  read_at VARCHAR(32) NULL
);
CREATE INDEX idx_notif_user ON notifications (user_id, read_at, id);
CREATE TABLE IF NOT EXISTS post_reactions (
  post_id INT NOT NULL,
  user_id INT NOT NULL,
  reaction VARCHAR(20) NOT NULL DEFAULT 'like',
  created_at VARCHAR(32) NOT NULL,
  PRIMARY KEY (post_id, user_id)
);
CREATE INDEX idx_react_post ON post_reactions (post_id, reaction);
CREATE TABLE IF NOT EXISTS pms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  recipient_id INT NOT NULL,
  subject VARCHAR(120) NOT NULL DEFAULT '',
  body_bbcode MEDIUMTEXT NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  created_at VARCHAR(32) NOT NULL,
  read_at VARCHAR(32) NULL
);
CREATE INDEX idx_pms_recip ON pms (recipient_id, id);
CREATE INDEX idx_pms_sender ON pms (sender_id, id);

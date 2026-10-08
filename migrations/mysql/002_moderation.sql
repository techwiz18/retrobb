-- RetroBB 002: trust & safety (MySQL 8.0+ dialect).
CREATE TABLE IF NOT EXISTS reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT NOT NULL,
  topic_id INT NOT NULL,
  reporter_id INT NOT NULL,
  reason VARCHAR(500) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  created_at VARCHAR(32) NOT NULL,
  handled_by INT NULL,
  handled_at VARCHAR(32) NULL,
  handle_note VARCHAR(500) NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS warnings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  warned_by INT NOT NULL,
  reason VARCHAR(500) NOT NULL DEFAULT '',
  created_at VARCHAR(32) NOT NULL
);
CREATE TABLE IF NOT EXISTS bans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  banned_by INT NOT NULL,
  reason VARCHAR(500) NOT NULL DEFAULT '',
  expires_at VARCHAR(32) NULL,
  created_at VARCHAR(32) NOT NULL,
  lifted_at VARCHAR(32) NULL
);
CREATE TABLE IF NOT EXISTS modlog (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_id INT NOT NULL,
  action VARCHAR(50) NOT NULL,
  target_type VARCHAR(30) NOT NULL DEFAULT '',
  target_id INT NOT NULL DEFAULT 0,
  detail VARCHAR(500) NOT NULL DEFAULT '',
  created_at VARCHAR(32) NOT NULL
);
CREATE INDEX idx_reports_status ON reports (status, id);
CREATE INDEX idx_bans_user ON bans (user_id, lifted_at);
CREATE INDEX idx_modlog_actor ON modlog (actor_id, id);

-- RetroBB 002: trust & safety (reports, warnings, bans, mod log).
CREATE TABLE IF NOT EXISTS reports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  post_id INTEGER NOT NULL,
  topic_id INTEGER NOT NULL,
  reporter_id INTEGER NOT NULL,
  reason VARCHAR(500) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  created_at VARCHAR(32) NOT NULL,
  handled_by INTEGER NULL,
  handled_at VARCHAR(32) NULL,
  handle_note VARCHAR(500) NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS warnings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  warned_by INTEGER NOT NULL,
  reason VARCHAR(500) NOT NULL DEFAULT '',
  created_at VARCHAR(32) NOT NULL
);
CREATE TABLE IF NOT EXISTS bans (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  banned_by INTEGER NOT NULL,
  reason VARCHAR(500) NOT NULL DEFAULT '',
  expires_at VARCHAR(32) NULL,
  created_at VARCHAR(32) NOT NULL,
  lifted_at VARCHAR(32) NULL
);
CREATE TABLE IF NOT EXISTS modlog (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_id INTEGER NOT NULL,
  action VARCHAR(50) NOT NULL,
  target_type VARCHAR(30) NOT NULL DEFAULT '',
  target_id INTEGER NOT NULL DEFAULT 0,
  detail VARCHAR(500) NOT NULL DEFAULT '',
  created_at VARCHAR(32) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_reports_status ON reports (status, id);
CREATE INDEX IF NOT EXISTS idx_bans_user ON bans (user_id, lifted_at);
CREATE INDEX IF NOT EXISTS idx_modlog_actor ON modlog (actor_id, id);

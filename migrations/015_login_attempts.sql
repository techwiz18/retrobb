-- Login brute-force throttle: per-IP failure counts. Small table, application-pruned.
CREATE TABLE IF NOT EXISTS login_attempts (
  ip VARCHAR(45) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  last_at DATETIME NOT NULL,
  PRIMARY KEY (ip)
);

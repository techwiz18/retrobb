-- RetroBB 012: password-reset tokens for forgot-password flow.
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at VARCHAR(32) NOT NULL,
  created_at VARCHAR(32) NOT NULL,
  used_at VARCHAR(32) NULL
);
CREATE INDEX idx_resets_user ON password_resets (user_id, expires_at);
CREATE INDEX idx_resets_token ON password_resets (token_hash);

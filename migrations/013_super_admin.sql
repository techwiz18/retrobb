-- Super-admin tier: only supers hold unrestricted power. Regular admins
-- keep day-to-day moderation; staff management and site settings are super-only.
ALTER TABLE users ADD COLUMN is_super TINYINT(1) NOT NULL DEFAULT 0;
CREATE INDEX idx_users_super ON users (is_super, id);

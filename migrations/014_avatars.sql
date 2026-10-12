-- User avatars: uploads (public/avatars, hashed names) or hotlinked URLs.
-- kind: 'none' | 'upload' | 'link'. url: remote URL (link). file: basename (upload).
ALTER TABLE users ADD COLUMN avatar_kind VARCHAR(10) NOT NULL DEFAULT 'none';
ALTER TABLE users ADD COLUMN avatar_url VARCHAR(500) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN avatar_file VARCHAR(64) NOT NULL DEFAULT '';

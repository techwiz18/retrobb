-- RetroBB 001 initial schema (MySQL 8.0+ dialect).
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  user_group VARCHAR(20) NOT NULL DEFAULT 'member',
  posts_count INT NOT NULL DEFAULT 0,
  bio TEXT NULL,
  created_at VARCHAR(32) NOT NULL
);
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  sort INT NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS forums (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(100) NOT NULL DEFAULT '',
  description VARCHAR(500) NOT NULL DEFAULT '',
  sort INT NOT NULL DEFAULT 0,
  topics_count INT NOT NULL DEFAULT 0,
  posts_count INT NOT NULL DEFAULT 0,
  last_topic_id INT NULL,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS topics (
  id INT AUTO_INCREMENT PRIMARY KEY,
  forum_id INT NOT NULL,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(100) NOT NULL DEFAULT '',
  pinned INT NOT NULL DEFAULT 0,
  locked INT NOT NULL DEFAULT 0,
  views INT NOT NULL DEFAULT 0,
  posts_count INT NOT NULL DEFAULT 0,
  created_at VARCHAR(32) NOT NULL,
  last_post_at VARCHAR(32) NOT NULL DEFAULT '',
  last_post_user_id INT NULL,
  moved_to_id INT NULL DEFAULT NULL,
  FOREIGN KEY (forum_id) REFERENCES forums(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  topic_id INT NOT NULL,
  user_id INT NOT NULL,
  body_bbcode MEDIUMTEXT NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  created_at VARCHAR(32) NOT NULL,
  edited_at VARCHAR(32) NULL,
  FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS plugins (
  name VARCHAR(100) PRIMARY KEY,
  enabled INT NOT NULL DEFAULT 1
);
CREATE INDEX idx_topics_forum ON topics (forum_id, last_post_at);
CREATE INDEX idx_posts_topic ON posts (topic_id, id);

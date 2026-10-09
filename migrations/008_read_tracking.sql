-- RetroBB 008: per-user read tracking for real "new posts" markers.
CREATE TABLE IF NOT EXISTS topic_reads (
  user_id INT NOT NULL,
  topic_id INT NOT NULL,
  last_post_id INT NOT NULL DEFAULT 0,
  updated_at VARCHAR(32) NOT NULL,
  PRIMARY KEY (user_id, topic_id)
);
CREATE INDEX idx_reads_topic ON topic_reads (topic_id, last_post_id);

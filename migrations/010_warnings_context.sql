-- RetroBB 010: link warnings back to the reported post/topic.
ALTER TABLE warnings ADD COLUMN post_id INT NOT NULL DEFAULT 0;
ALTER TABLE warnings ADD COLUMN topic_id INT NOT NULL DEFAULT 0;
CREATE INDEX idx_warnings_user ON warnings (user_id, id);

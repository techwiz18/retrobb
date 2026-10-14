-- Hot-path indexes: per-user post lookups, bare recency ordering, PM box
-- composites, report duplicate checks. All additive, safe to re-run.
CREATE INDEX idx_posts_user ON posts (user_id, id);
CREATE INDEX idx_topics_last ON topics (last_post_at, id);
CREATE INDEX idx_pms_recip_del ON pms (recipient_id, recipient_deleted, id);
CREATE INDEX idx_pms_sender_del ON pms (sender_id, sender_deleted, id);
CREATE INDEX idx_reports_lookup ON reports (post_id, reporter_id, status);

-- One open report per (post, reporter): the app checks before inserting, but
-- double-clicks slipped two rows through. Dedupe first (keep the earliest),
-- then let the key enforce it. Re-reporting after resolve/dismiss still
-- works: a different status is a different key.
DELETE r1 FROM reports r1
JOIN reports r2 ON r2.post_id = r1.post_id AND r2.reporter_id = r1.reporter_id AND r2.status = r1.status AND r2.id < r1.id;
ALTER TABLE reports ADD UNIQUE KEY uq_report_once (post_id, reporter_id, status);

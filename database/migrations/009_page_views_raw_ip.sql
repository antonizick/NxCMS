-- Visitor log (admin request: per-visit table with IP + page/article
-- accessed). page_views already tracks each request but only kept ip_hash
-- (privacy-by-design, see PageView.php docblock) — Nick explicitly asked to
-- see raw IPs per visit, same posture already used for activity_log and
-- contact_submissions, so this adds the raw address alongside the hash
-- rather than replacing it (the hash still powers the existing "unique
-- visitors" aggregate on the dashboard).
ALTER TABLE page_views
    ADD COLUMN ip_address VARCHAR(45) NULL AFTER ip_hash;

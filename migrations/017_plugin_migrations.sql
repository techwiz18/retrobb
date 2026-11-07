-- Tracks per-plugin migrations so plugins/<name>/migrations/*.sql run once
-- via bin/migrate.php (issue #20). Applied by the core loop itself.
CREATE TABLE IF NOT EXISTS plugin_migrations (
    plugin VARCHAR(64) NOT NULL,
    file VARCHAR(128) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (plugin, file)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

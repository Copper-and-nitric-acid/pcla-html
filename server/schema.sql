-- PCLA API · SQLite schema
-- 首次请求任意 API 时会自动执行，无需手动导入。

CREATE TABLE IF NOT EXISTS downloads (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    channel    TEXT    NOT NULL,
    ip_hash    TEXT    NOT NULL,
    user_agent TEXT    NOT NULL DEFAULT '',
    referer    TEXT    NOT NULL DEFAULT '',
    created_at TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_downloads_created ON downloads (created_at);
CREATE INDEX IF NOT EXISTS idx_downloads_ip ON downloads (ip_hash, channel, created_at);

CREATE TABLE IF NOT EXISTS feedback (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT    NOT NULL,
    contact    TEXT    NOT NULL DEFAULT '',
    message    TEXT    NOT NULL,
    status     TEXT    NOT NULL DEFAULT 'pending'
                       CHECK (status IN ('pending', 'approved', 'rejected')),
    ip_hash    TEXT    NOT NULL,
    user_agent TEXT    NOT NULL DEFAULT '',
    created_at TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_feedback_created ON feedback (created_at);
CREATE INDEX IF NOT EXISTS idx_feedback_status ON feedback (status, created_at);
CREATE INDEX IF NOT EXISTS idx_feedback_ip ON feedback (ip_hash, created_at);

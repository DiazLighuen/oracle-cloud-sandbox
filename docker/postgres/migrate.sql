-- Migration: add is_admin column to existing users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin BOOLEAN NOT NULL DEFAULT FALSE;

-- Migration: store Google OAuth token JSON (access_token + refresh_token + expiry)
ALTER TABLE users ADD COLUMN IF NOT EXISTS google_token TEXT;

-- Migration: YouTube watched videos
CREATE TABLE IF NOT EXISTS watched_videos (
    user_id    UUID        NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    video_id   VARCHAR(20) NOT NULL,
    watched_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, video_id)
);
CREATE INDEX IF NOT EXISTS watched_videos_user_idx ON watched_videos (user_id, watched_at DESC);

-- Migration: notification log table
CREATE TABLE IF NOT EXISTS notification_log (
    id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    type           VARCHAR(50) NOT NULL,
    severity       VARCHAR(10) NOT NULL,
    container_name VARCHAR(255),
    container_id   VARCHAR(64),
    message        TEXT NOT NULL,
    sent_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

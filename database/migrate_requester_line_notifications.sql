SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS line_user_id VARCHAR(33) NULL AFTER email;

INSERT IGNORE INTO schema_migrations(version)
VALUES ('003_requester_line_notifications');

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS line_link_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_line_link_user FOREIGN KEY(user_id) REFERENCES users(id),
  INDEX idx_line_link_user(user_id, used_at),
  INDEX idx_line_link_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations(version)
VALUES ('004_line_account_linking');

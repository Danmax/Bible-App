CREATE TABLE IF NOT EXISTS rate_limits (
    action_key VARCHAR(255) NOT NULL,
    attempts INT NOT NULL DEFAULT 1,
    window_started_at INT NOT NULL,
    PRIMARY KEY (action_key),
    INDEX idx_window_started_at (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
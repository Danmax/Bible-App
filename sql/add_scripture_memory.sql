CREATE TABLE IF NOT EXISTS scripture_memory_cards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    bookmark_id BIGINT UNSIGNED NOT NULL,
    review_count INT UNSIGNED NOT NULL DEFAULT 0,
    correct_count INT UNSIGNED NOT NULL DEFAULT 0,
    interval_days INT UNSIGNED NOT NULL DEFAULT 1,
    next_review_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_memory_bookmark (user_id, bookmark_id),
    KEY idx_memory_due (user_id, next_review_at),
    CONSTRAINT fk_memory_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_memory_bookmark FOREIGN KEY (bookmark_id) REFERENCES bookmarks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

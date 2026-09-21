CREATE TABLE IF NOT EXISTS guided_studies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    book_id BIGINT UNSIGNED NOT NULL,
    chapter_number INT UNSIGNED NOT NULL,
    start_verse INT UNSIGNED NOT NULL DEFAULT 0,
    end_verse INT UNSIGNED NOT NULL DEFAULT 0,
    translation VARCHAR(20) NOT NULL,
    observation MEDIUMTEXT NOT NULL,
    interpretation MEDIUMTEXT NOT NULL,
    application MEDIUMTEXT NOT NULL,
    prayer MEDIUMTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_guided_studies_user_updated (user_id, updated_at),
    KEY idx_guided_studies_passage (book_id, chapter_number, start_verse, end_verse),
    CONSTRAINT fk_guided_studies_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_guided_studies_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

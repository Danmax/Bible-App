CREATE TABLE IF NOT EXISTS public_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_by_user_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    summary TEXT NOT NULL,
    session_type VARCHAR(60) NOT NULL DEFAULT 'study',
    host_name VARCHAR(160) NULL,
    location_name VARCHAR(160) NULL,
    meeting_url VARCHAR(255) NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NULL,
    capacity INT UNSIGNED NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'published',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_public_sessions_status_start (status, start_at),
    CONSTRAINT fk_public_sessions_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

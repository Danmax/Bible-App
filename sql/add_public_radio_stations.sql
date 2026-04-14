CREATE TABLE IF NOT EXISTS public_radio_stations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_by_user_id BIGINT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    kind VARCHAR(60) NOT NULL DEFAULT 'Music',
    tagline VARCHAR(255) NOT NULL,
    stream_url VARCHAR(255) NULL,
    listen_url VARCHAR(255) NOT NULL,
    youtube_playlist_id VARCHAR(80) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'published',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_public_radio_status_sort (status, is_featured, sort_order, id),
    CONSTRAINT fk_public_radio_stations_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sermon_note_folders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    parent_folder_id BIGINT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sermon_note_folders_user (user_id, sort_order),
    CONSTRAINT fk_sermon_note_folders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sermon_note_folders_parent FOREIGN KEY (parent_folder_id) REFERENCES sermon_note_folders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sermon_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    folder_id BIGINT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    speaker_name VARCHAR(160) NULL,
    series_name VARCHAR(160) NULL,
    service_date DATE NULL,
    source_url VARCHAR(255) NULL,
    share_code VARCHAR(16) NOT NULL UNIQUE,
    summary_text TEXT NULL,
    speaker_notes_text MEDIUMTEXT NULL,
    content_html MEDIUMTEXT NOT NULL,
    content_text MEDIUMTEXT NOT NULL,
    storm_board_json MEDIUMTEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    layout_mode VARCHAR(20) NOT NULL DEFAULT 'split',
    is_starred TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sermon_notes_user_updated (user_id, updated_at),
    KEY idx_sermon_notes_folder_updated (folder_id, updated_at),
    CONSTRAINT fk_sermon_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sermon_notes_folder FOREIGN KEY (folder_id) REFERENCES sermon_note_folders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sermon_note_verse_refs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sermon_note_id BIGINT UNSIGNED NOT NULL,
    verse_id BIGINT UNSIGNED NOT NULL,
    reference_kind VARCHAR(32) NOT NULL DEFAULT 'citation',
    reference_label VARCHAR(160) NULL,
    quote_text TEXT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sermon_note_verse_refs_note (sermon_note_id, sort_order),
    KEY idx_sermon_note_verse_refs_verse (verse_id),
    CONSTRAINT fk_sermon_note_verse_refs_note FOREIGN KEY (sermon_note_id) REFERENCES sermon_notes(id) ON DELETE CASCADE,
    CONSTRAINT fk_sermon_note_verse_refs_verse FOREIGN KEY (verse_id) REFERENCES verses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sermon_note_reference_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sermon_note_id BIGINT UNSIGNED NOT NULL,
    tag_type VARCHAR(32) NOT NULL,
    label VARCHAR(160) NOT NULL,
    detail_text VARCHAR(255) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sermon_note_reference_tags_note (sermon_note_id, tag_type, sort_order),
    CONSTRAINT fk_sermon_note_reference_tags_note FOREIGN KEY (sermon_note_id) REFERENCES sermon_notes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

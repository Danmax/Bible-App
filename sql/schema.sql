CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'member',
    city VARCHAR(120) NULL,
    avatar_url VARCHAR(255) NULL,
    primary_flag VARCHAR(24) NULL,
    secondary_flag VARCHAR(24) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_change_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    new_email VARCHAR(190) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_change_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NULL,
    target_user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(120) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    context_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_logs_actor (actor_user_id),
    KEY idx_audit_logs_target (target_user_id),
    KEY idx_audit_logs_event_type (event_type),
    CONSTRAINT fk_audit_logs_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_logs_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    session_token_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    last_seen_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_sessions_user (user_id),
    KEY idx_user_sessions_expires_at (expires_at),
    CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS friend_invites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_user_id BIGINT UNSIGNED NOT NULL,
    recipient_user_id BIGINT UNSIGNED NULL,
    recipient_email VARCHAR(190) NOT NULL,
    invite_token VARCHAR(48) NULL,
    invite_token_hash CHAR(64) NOT NULL UNIQUE,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    responded_at DATETIME NULL DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_friend_invites_sender (sender_user_id),
    KEY idx_friend_invites_recipient (recipient_user_id),
    CONSTRAINT fk_friend_invites_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_friend_invites_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS friendships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_one_id BIGINT UNSIGNED NOT NULL,
    user_two_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_friend_pair (user_one_id, user_two_id),
    CONSTRAINT fk_friendships_user_one FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_friendships_user_two FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS books (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    abbreviation VARCHAR(10) NOT NULL,
    testament VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id BIGINT UNSIGNED NOT NULL,
    chapter_number INT UNSIGNED NOT NULL,
    verse_number INT UNSIGNED NOT NULL,
    verse_text TEXT NOT NULL,
    translation VARCHAR(20) NOT NULL DEFAULT 'KJV',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_verse (book_id, chapter_number, verse_number, translation),
    CONSTRAINT fk_verses_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookmarks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    verse_id BIGINT UNSIGNED NOT NULL,
    note TEXT NULL,
    tag VARCHAR(100) NULL,
    selected_text TEXT NULL,
    highlight_color VARCHAR(20) NULL,
    selection_start INT UNSIGNED NULL,
    selection_end INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bookmarks_user_verse (user_id, verse_id),
    CONSTRAINT fk_bookmarks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookmarks_verse FOREIGN KEY (verse_id) REFERENCES verses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    verse_id BIGINT UNSIGNED NULL,
    title VARCHAR(160) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_study_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_study_notes_verse FOREIGN KEY (verse_id) REFERENCES verses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS reading_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    duration_days INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_reading_progress (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    reading_plan_id BIGINT UNSIGNED NOT NULL,
    day_number INT UNSIGNED NOT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_progress_day (user_id, reading_plan_id, day_number),
    CONSTRAINT fk_reading_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reading_progress_plan FOREIGN KEY (reading_plan_id) REFERENCES reading_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS yearly_goals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    year YEAR NOT NULL,
    goal_title VARCHAR(160) NOT NULL,
    goal_type VARCHAR(60) NOT NULL,
    target_value INT UNSIGNED NULL,
    current_value INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_yearly_goals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS planner_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    event_date DATETIME NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    related_community_event_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_planner_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prayer_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    details TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prayer_entries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_event_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    icon VARCHAR(20) NULL,
    color VARCHAR(20) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_by_user_id BIGINT UNSIGNED NULL,
    category_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    settings_json LONGTEXT NULL,
    visibility VARCHAR(30) NOT NULL DEFAULT 'public',
    image_url VARCHAR(255) NULL,
    location_name VARCHAR(160) NULL,
    location_address VARCHAR(255) NULL,
    meeting_url VARCHAR(255) NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'published',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_community_events_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_community_events_category FOREIGN KEY (category_id) REFERENCES community_event_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_event_rsvps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    community_event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    response VARCHAR(30) NOT NULL DEFAULT 'interested',
    bring_item_id BIGINT UNSIGNED NULL,
    bring_item_label VARCHAR(160) NULL,
    bring_item_note VARCHAR(255) NULL,
    remind_three_days TINYINT(1) NOT NULL DEFAULT 1,
    remind_same_day TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_event_user_rsvp (community_event_id, user_id),
    CONSTRAINT fk_community_event_rsvps_event FOREIGN KEY (community_event_id) REFERENCES community_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_community_event_rsvps_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_community_event_rsvps_item FOREIGN KEY (bring_item_id) REFERENCES community_event_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_event_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    community_event_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    claimed_by_user_id BIGINT UNSIGNED NULL,
    label VARCHAR(160) NOT NULL,
    details VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    assigned_by_host TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_community_event_items_event (community_event_id, sort_order, id),
    KEY idx_community_event_items_claimed (claimed_by_user_id),
    CONSTRAINT fk_community_event_items_event FOREIGN KEY (community_event_id) REFERENCES community_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_community_event_items_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_community_event_items_claimed_by FOREIGN KEY (claimed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_event_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    community_event_id BIGINT UNSIGNED NOT NULL,
    sender_user_id BIGINT UNSIGNED NULL,
    message_type VARCHAR(40) NOT NULL DEFAULT 'update',
    subject VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    delivered_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_community_event_messages_event (community_event_id, created_at),
    CONSTRAINT fk_community_event_messages_event FOREIGN KEY (community_event_id) REFERENCES community_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_community_event_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS city VARCHAR(120) NULL AFTER role,
    ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) NULL AFTER city,
    ADD COLUMN IF NOT EXISTS primary_flag VARCHAR(24) NULL AFTER avatar_url,
    ADD COLUMN IF NOT EXISTS secondary_flag VARCHAR(24) NULL AFTER primary_flag;

ALTER TABLE bookmarks
    ADD COLUMN IF NOT EXISTS selected_text TEXT NULL AFTER tag,
    ADD COLUMN IF NOT EXISTS highlight_color VARCHAR(20) NULL AFTER selected_text,
    ADD COLUMN IF NOT EXISTS selection_start INT UNSIGNED NULL AFTER highlight_color,
    ADD COLUMN IF NOT EXISTS selection_end INT UNSIGNED NULL AFTER selection_start;

ALTER TABLE friend_invites
    ADD COLUMN IF NOT EXISTS invite_token_hash CHAR(64) NULL AFTER recipient_email;

CREATE TABLE IF NOT EXISTS studies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_by_user_id BIGINT UNSIGNED NULL,
    template_key VARCHAR(60) NULL,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    summary TEXT NULL,
    description TEXT NULL,
    duration_days INT UNSIGNED NOT NULL DEFAULT 7,
    cover_image_url VARCHAR(500) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    visibility VARCHAR(30) NOT NULL DEFAULT 'public',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_studies_slug (slug),
    KEY idx_studies_status_featured (status, is_featured, updated_at),
    CONSTRAINT fk_studies_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_steps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_id BIGINT UNSIGNED NOT NULL,
    day_number INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    section_title VARCHAR(180) NULL,
    content MEDIUMTEXT NULL,
    video_title VARCHAR(180) NULL,
    youtube_video_id VARCHAR(80) NULL,
    video_unlock_rule VARCHAR(40) NOT NULL DEFAULT 'after_step',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_study_step_day (study_id, day_number),
    KEY idx_study_steps_study_sort (study_id, sort_order, id),
    CONSTRAINT fk_study_steps_study FOREIGN KEY (study_id) REFERENCES studies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_step_verses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_step_id BIGINT UNSIGNED NOT NULL,
    reference_text VARCHAR(120) NOT NULL,
    verse_id BIGINT UNSIGNED NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_study_step_verses_step (study_step_id, sort_order, id),
    KEY idx_study_step_verses_verse (verse_id),
    CONSTRAINT fk_study_step_verses_step FOREIGN KEY (study_step_id) REFERENCES study_steps(id) ON DELETE CASCADE,
    CONSTRAINT fk_study_step_verses_verse FOREIGN KEY (verse_id) REFERENCES verses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_step_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_step_id BIGINT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_study_step_questions_step (study_step_id, sort_order, id),
    CONSTRAINT fk_study_step_questions_step FOREIGN KEY (study_step_id) REFERENCES study_steps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_step_challenges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_step_id BIGINT UNSIGNED NOT NULL,
    challenge_text TEXT NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_study_step_challenges_step (study_step_id, sort_order, id),
    CONSTRAINT fk_study_step_challenges_step FOREIGN KEY (study_step_id) REFERENCES study_steps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_step_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_step_id BIGINT UNSIGNED NOT NULL,
    item_type VARCHAR(40) NOT NULL DEFAULT 'devotional',
    title VARCHAR(180) NOT NULL,
    body MEDIUMTEXT NULL,
    resource_url VARCHAR(500) NULL,
    bible_reference VARCHAR(180) NULL,
    unlock_rule VARCHAR(40) NOT NULL DEFAULT 'none',
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_study_step_items_step (study_step_id, sort_order, id),
    CONSTRAINT fk_study_step_items_step FOREIGN KEY (study_step_id) REFERENCES study_steps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_study_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    badge_awarded_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY unique_user_study_enrollment (study_id, user_id),
    KEY idx_user_study_enrollments_user (user_id, status, started_at),
    CONSTRAINT fk_user_study_enrollments_study FOREIGN KEY (study_id) REFERENCES studies(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_study_enrollments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_study_step_progress (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    study_step_id BIGINT UNSIGNED NOT NULL,
    reflection_response TEXT NULL,
    challenge_completed_at TIMESTAMP NULL DEFAULT NULL,
    video_unlocked_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_study_step_progress (enrollment_id, study_step_id),
    KEY idx_user_study_step_progress_step (study_step_id),
    CONSTRAINT fk_user_study_step_progress_enrollment FOREIGN KEY (enrollment_id) REFERENCES user_study_enrollments(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_study_step_progress_step FOREIGN KEY (study_step_id) REFERENCES study_steps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_study_item_progress (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    study_step_item_id BIGINT UNSIGNED NOT NULL,
    completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_study_item_progress (enrollment_id, study_step_item_id),
    KEY idx_user_study_item_progress_item (study_step_item_id),
    CONSTRAINT fk_user_study_item_progress_enrollment FOREIGN KEY (enrollment_id) REFERENCES user_study_enrollments(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_study_item_progress_item FOREIGN KEY (study_step_item_id) REFERENCES study_step_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_invites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_id BIGINT UNSIGNED NOT NULL,
    enrollment_id BIGINT UNSIGNED NULL,
    sender_user_id BIGINT UNSIGNED NOT NULL,
    recipient_email VARCHAR(190) NULL,
    invite_token VARCHAR(120) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    accepted_by_user_id BIGINT UNSIGNED NULL,
    accepted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_study_invite_token (invite_token),
    KEY idx_study_invites_study (study_id, created_at),
    KEY idx_study_invites_sender (sender_user_id, created_at),
    CONSTRAINT fk_study_invites_study FOREIGN KEY (study_id) REFERENCES studies(id) ON DELETE CASCADE,
    CONSTRAINT fk_study_invites_enrollment FOREIGN KEY (enrollment_id) REFERENCES user_study_enrollments(id) ON DELETE SET NULL,
    CONSTRAINT fk_study_invites_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_study_invites_accepted_by FOREIGN KEY (accepted_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_discussion_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_id BIGINT UNSIGNED NOT NULL,
    enrollment_id BIGINT UNSIGNED NULL,
    sender_user_id BIGINT UNSIGNED NULL,
    message_text TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_study_discussion_study (study_id, created_at),
    CONSTRAINT fk_study_discussion_study FOREIGN KEY (study_id) REFERENCES studies(id) ON DELETE CASCADE,
    CONSTRAINT fk_study_discussion_enrollment FOREIGN KEY (enrollment_id) REFERENCES user_study_enrollments(id) ON DELETE SET NULL,
    CONSTRAINT fk_study_discussion_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_badges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_id BIGINT UNSIGNED NOT NULL,
    badge_name VARCHAR(160) NOT NULL,
    badge_description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_study_badge (study_id),
    CONSTRAINT fk_study_badges_study FOREIGN KEY (study_id) REFERENCES studies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_study_badges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    study_badge_id BIGINT UNSIGNED NOT NULL,
    enrollment_id BIGINT UNSIGNED NULL,
    awarded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_study_badge (user_id, study_badge_id),
    KEY idx_user_study_badges_user (user_id, awarded_at),
    CONSTRAINT fk_user_study_badges_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_study_badges_badge FOREIGN KEY (study_badge_id) REFERENCES study_badges(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_study_badges_enrollment FOREIGN KEY (enrollment_id) REFERENCES user_study_enrollments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS study_editor_access_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    request_message TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_study_editor_request_user (user_id),
    KEY idx_study_editor_requests_status (status, created_at),
    CONSTRAINT fk_study_editor_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_study_editor_requests_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

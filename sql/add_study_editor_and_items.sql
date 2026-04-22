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

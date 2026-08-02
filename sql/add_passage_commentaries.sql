CREATE TABLE IF NOT EXISTS commentary_resources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(160) NULL,
    description TEXT NULL,
    tradition_label VARCHAR(100) NULL,
    study_level VARCHAR(32) NOT NULL DEFAULT 'devotional',
    license_name VARCHAR(160) NOT NULL,
    license_url VARCHAR(500) NULL,
    source_url VARCHAR(500) NOT NULL,
    priority INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_commentary_resource_slug (slug),
    KEY idx_commentary_resources_active_priority (is_active, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commentary_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resource_id BIGINT UNSIGNED NOT NULL,
    scripture_reference_id BIGINT UNSIGNED NOT NULL,
    section_title VARCHAR(255) NULL,
    body_text MEDIUMTEXT NOT NULL,
    source_url VARCHAR(500) NULL,
    source_key VARCHAR(190) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_commentary_entry_source (resource_id, source_key),
    KEY idx_commentary_entries_reference (scripture_reference_id, resource_id),
    KEY idx_commentary_entries_resource_order (resource_id, sort_order, id),
    CONSTRAINT fk_commentary_entries_resource FOREIGN KEY (resource_id) REFERENCES commentary_resources(id) ON DELETE CASCADE,
    CONSTRAINT fk_commentary_entries_reference FOREIGN KEY (scripture_reference_id) REFERENCES scripture_references(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

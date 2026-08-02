CREATE TABLE IF NOT EXISTS scripture_references (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    start_book_id BIGINT UNSIGNED NOT NULL,
    start_chapter INT UNSIGNED NOT NULL,
    start_verse INT UNSIGNED NOT NULL,
    end_book_id BIGINT UNSIGNED NOT NULL,
    end_chapter INT UNSIGNED NOT NULL,
    end_verse INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_scripture_reference (
        start_book_id,
        start_chapter,
        start_verse,
        end_book_id,
        end_chapter,
        end_verse
    ),
    KEY idx_scripture_reference_lookup (start_book_id, start_chapter, start_verse),
    CONSTRAINT fk_scripture_references_start_book FOREIGN KEY (start_book_id) REFERENCES books(id) ON DELETE CASCADE,
    CONSTRAINT fk_scripture_references_end_book FOREIGN KEY (end_book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cross_references (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_reference_id BIGINT UNSIGNED NOT NULL,
    target_reference_id BIGINT UNSIGNED NOT NULL,
    rank_score INT UNSIGNED NOT NULL DEFAULT 0,
    relationship_type VARCHAR(40) NOT NULL DEFAULT 'related',
    source_dataset VARCHAR(100) NOT NULL,
    source_key VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cross_reference (source_reference_id, target_reference_id, source_dataset),
    KEY idx_cross_references_source_rank (source_reference_id, rank_score),
    KEY idx_cross_references_target (target_reference_id),
    CONSTRAINT fk_cross_references_source FOREIGN KEY (source_reference_id) REFERENCES scripture_references(id) ON DELETE CASCADE,
    CONSTRAINT fk_cross_references_target FOREIGN KEY (target_reference_id) REFERENCES scripture_references(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

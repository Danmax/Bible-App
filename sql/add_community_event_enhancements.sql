ALTER TABLE community_events
    ADD COLUMN IF NOT EXISTS settings_json LONGTEXT NULL AFTER event_type;

ALTER TABLE community_event_rsvps
    ADD COLUMN IF NOT EXISTS bring_item_id BIGINT UNSIGNED NULL AFTER response,
    ADD COLUMN IF NOT EXISTS bring_item_label VARCHAR(160) NULL AFTER bring_item_id,
    ADD COLUMN IF NOT EXISTS bring_item_note VARCHAR(255) NULL AFTER bring_item_label,
    ADD COLUMN IF NOT EXISTS remind_three_days TINYINT(1) NOT NULL DEFAULT 1 AFTER bring_item_note,
    ADD COLUMN IF NOT EXISTS remind_same_day TINYINT(1) NOT NULL DEFAULT 1 AFTER remind_three_days;

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

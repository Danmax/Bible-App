ALTER TABLE bookmarks
    DROP INDEX unique_user_verse_bookmark,
    ADD COLUMN selected_text TEXT NULL AFTER tag,
    ADD COLUMN highlight_color VARCHAR(20) NULL AFTER selected_text,
    ADD COLUMN selection_start INT UNSIGNED NULL AFTER highlight_color,
    ADD COLUMN selection_end INT UNSIGNED NULL AFTER selection_start,
    ADD INDEX idx_bookmarks_user_verse (user_id, verse_id);

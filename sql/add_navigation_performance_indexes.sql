SET @database_name = DATABASE();

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @database_name
      AND table_name = 'verses'
      AND index_name = 'idx_verses_translation'
);
SET @sql = IF(
    @index_exists = 0,
    'ALTER TABLE verses ADD INDEX idx_verses_translation (translation)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @database_name
      AND table_name = 'verses'
      AND index_name = 'idx_verses_translation_book_chapter'
);
SET @sql = IF(
    @index_exists = 0,
    'ALTER TABLE verses ADD INDEX idx_verses_translation_book_chapter (translation, book_id, chapter_number, verse_number)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

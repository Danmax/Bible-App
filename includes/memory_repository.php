<?php

declare(strict_types=1);

function scripture_memory_available(): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    try {
        $available = db()->query("SHOW TABLES LIKE 'scripture_memory_cards'")->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function fetch_scripture_memory_cards(int $userId, int $limit = 24): array
{
    $statement = db()->prepare(
        'SELECT scripture_memory_cards.*, bookmarks.id AS bookmark_id, verses.id AS verse_id, verses.verse_text,
                verses.chapter_number, verses.verse_number, verses.translation, books.name AS book_name
        FROM scripture_memory_cards
        INNER JOIN bookmarks ON bookmarks.id = scripture_memory_cards.bookmark_id AND bookmarks.user_id = scripture_memory_cards.user_id
        INNER JOIN verses ON verses.id = bookmarks.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE scripture_memory_cards.user_id = :user_id
        ORDER BY scripture_memory_cards.next_review_at ASC, scripture_memory_cards.updated_at DESC
        LIMIT :limit'
    );
    $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function create_scripture_memory_card(int $userId, int $bookmarkId): bool
{
    $statement = db()->prepare(
        'INSERT IGNORE INTO scripture_memory_cards (user_id, bookmark_id, next_review_at)
        SELECT :user_id, bookmarks.id, NOW()
        FROM bookmarks
        WHERE bookmarks.id = :bookmark_id AND bookmarks.user_id = :user_id'
    );
    $statement->execute(['user_id' => $userId, 'bookmark_id' => $bookmarkId]);

    return $statement->rowCount() > 0;
}

function review_scripture_memory_card(int $cardId, int $userId, bool $remembered): void
{
    $statement = db()->prepare(
        'UPDATE scripture_memory_cards
        SET review_count = review_count + 1,
            correct_count = correct_count + :correct_count,
            interval_days = CASE WHEN :interval_correct = 1 THEN LEAST(interval_days * 2, 60) ELSE 1 END,
            next_review_at = DATE_ADD(NOW(), INTERVAL CASE WHEN :review_correct = 1 THEN LEAST(interval_days * 2, 60) ELSE 1 END DAY)
        WHERE id = :id AND user_id = :user_id'
    );
    $correct = $remembered ? 1 : 0;
    $statement->execute(['id' => $cardId, 'user_id' => $userId, 'correct_count' => $correct, 'interval_correct' => $correct, 'review_correct' => $correct]);
}

<?php

declare(strict_types=1);

function fetch_library_matches(int $userId, string $query, string $type = 'all', int $bookId = 0, int $page = 1, ?PDO $connection = null): array
{
    $pdo = $connection ?? db();
    $query = mb_substr(trim($query), 0, 200);
    $selects = [];
    $params = [];
    foreach (['saved', 'notes'] as $kind) {
        if (in_array($type, ['saved', 'notes'], true) && $type !== $kind) { continue; }
        $table = $kind === 'saved' ? 'bookmarks' : 'study_notes';
        $title = $kind === 'saved' ? "COALESCE(bookmarks.tag, 'Saved verse')" : 'study_notes.title';
        $content = $kind === 'saved' ? "COALESCE(bookmarks.note, '')" : 'study_notes.content';
        $date = $kind === 'saved' ? 'bookmarks.created_at' : 'study_notes.updated_at';
        $where = [$table . '.user_id = :' . $kind . '_owner'];
        $params[$kind . '_owner'] = $userId;
        if ($query !== '') {
            $where[] = '(' . $title . " LIKE :" . $kind . "_title ESCAPE '!' OR " . $content . " LIKE :" . $kind . "_content ESCAPE '!' OR verses.verse_text LIKE :" . $kind . "_verse ESCAPE '!')";
            foreach (['title', 'content', 'verse'] as $field) { $params[$kind . '_' . $field] = scripture_search_like($query); }
        }
        if ($bookId > 0) { $where[] = 'verses.book_id = :' . $kind . '_book'; $params[$kind . '_book'] = $bookId; }
        $selects[] = "SELECT '" . $kind . "' AS kind, " . $table . '.id, ' . $title . ' AS title, ' . $content . ' AS content, ' . $date . ' AS saved_at,
            verses.book_id, verses.chapter_number, verses.verse_number, verses.translation, verses.verse_text, books.name AS book_name
            FROM ' . $table . ' LEFT JOIN verses ON verses.id = ' . $table . '.verse_id LEFT JOIN books ON books.id = verses.book_id WHERE ' . implode(' AND ', $where);
    }
    $union = implode(' UNION ALL ', $selects);
    $statement = $pdo->prepare('SELECT COUNT(*) FROM (' . $union . ') library_matches');
    $statement->execute($params);
    $total = (int) $statement->fetchColumn();
    $pages = max(1, (int) ceil($total / 20));
    $page = max(1, min($page, $pages));
    $statement = $pdo->prepare('SELECT * FROM (' . $union . ') library_matches ORDER BY saved_at DESC, kind ASC, id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20));
    $statement->execute($params);
    return ['results' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'page_count' => $pages];
}

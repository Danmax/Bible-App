<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Save whole-verse marks without replacing the member's annotations. */
function save_reader_marks(int $userId, array $verseIds, string $action, string $color = '', ?PDO $pdo = null): array
{
    $colors = ['neon-yellow', 'neon-green', 'neon-blue', 'neon-orange', 'neon-pink'];
    if (!in_array($action, ['highlight', 'bookmark', 'clear-highlight'], true)
        || ($action === 'highlight' && !in_array($color, $colors, true))) {
        throw new InvalidArgumentException('Choose a valid highlight color or action.');
    }
    if ($verseIds === [] || count($verseIds) > 176) {
        throw new InvalidArgumentException('Select between 1 and 176 verses.');
    }
    foreach ($verseIds as $id) {
        if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new InvalidArgumentException('Select valid verses.');
        }
    }
    $verseIds = array_values(array_unique(array_map('intval', $verseIds)));
    $pdo ??= db();
    $statement = $pdo->prepare('SELECT id, book_id, chapter_number, translation FROM verses WHERE id IN (' . implode(',', array_fill(0, count($verseIds), '?')) . ') ORDER BY verse_number');
    $statement->execute($verseIds);
    $verses = $statement->fetchAll();
    if (count($verses) !== count($verseIds)) {
        throw new InvalidArgumentException('One of the selected verses is unavailable.');
    }
    $chapter = $verses[0];
    foreach ($verses as $verse) {
        if ((int) $verse['book_id'] !== (int) $chapter['book_id']
            || (int) $verse['chapter_number'] !== (int) $chapter['chapter_number']
            || $verse['translation'] !== $chapter['translation']) {
            throw new InvalidArgumentException('Select verses from the same chapter and translation.');
        }
    }

    $pdo->beginTransaction();
    try {
        $find = $pdo->prepare('SELECT id FROM bookmarks WHERE user_id = :user_id AND verse_id = :verse_id AND selected_text IS NULL AND selection_start IS NULL AND selection_end IS NULL LIMIT 1');
        $insert = $pdo->prepare('INSERT INTO bookmarks (user_id, verse_id, highlight_color) VALUES (:user_id, :verse_id, :color)');
        $update = $pdo->prepare('UPDATE bookmarks SET highlight_color = :color WHERE id = :id AND user_id = :user_id');
        $clear = $pdo->prepare('UPDATE bookmarks SET highlight_color = NULL WHERE user_id = :user_id AND verse_id = :verse_id');
        foreach ($verseIds as $verseId) {
            $params = ['user_id' => $userId, 'verse_id' => $verseId];
            if ($action === 'clear-highlight') {
                $clear->execute($params);
                continue;
            }
            $find->execute($params);
            $existing = $find->fetch();
            if ($existing === false) {
                $insert->execute($params + ['color' => $action === 'highlight' ? $color : null]);
            } elseif ($action === 'highlight') {
                $update->execute(['id' => $existing['id'], 'user_id' => $userId, 'color' => $color]);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
    return $verseIds;
}

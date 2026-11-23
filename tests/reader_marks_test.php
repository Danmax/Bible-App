<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/reader_marks.php';
require_once __DIR__ . '/../includes/helpers.php';

function check_mark(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE verses (id INTEGER PRIMARY KEY, book_id INTEGER, chapter_number INTEGER, verse_number INTEGER, translation TEXT)');
$pdo->exec('CREATE TABLE bookmarks (id INTEGER PRIMARY KEY, user_id INTEGER, verse_id INTEGER, tag TEXT, note TEXT, selected_text TEXT, selection_start INTEGER, selection_end INTEGER, highlight_color TEXT)');
$pdo->exec("INSERT INTO verses VALUES (1,43,1,1,'KJV'), (2,43,1,2,'KJV'), (3,43,1,3,'KJV'), (4,43,2,1,'KJV'), (5,43,1,1,'WEB')");
$pdo->exec("INSERT INTO bookmarks VALUES (1,10,1,'promise','Keep this reflection',NULL,NULL,NULL,'neon-pink'), (2,11,1,'private','Other member',NULL,NULL,NULL,'neon-green'), (3,10,1,'phrase','Phrase note','beginning',0,9,'neon-yellow')");

save_reader_marks(10, [1, 3, 3], 'highlight', 'neon-blue', $pdo);
$marks = $pdo->query('SELECT * FROM bookmarks ORDER BY id')->fetchAll();
check_mark(count($marks) === 4 && (int) $marks[3]['verse_id'] === 3, 'Nonadjacent verses must save exactly once without selecting the gap.');
check_mark($marks[0]['tag'] === 'promise' && $marks[0]['note'] === 'Keep this reflection' && $marks[0]['highlight_color'] === 'neon-blue', 'Changing a color erased the annotation.');
check_mark($marks[1]['highlight_color'] === 'neon-green' && $marks[1]['note'] === 'Other member', 'A mark changed another account.');
save_reader_marks(10, [1, 3], 'highlight', 'neon-orange', $pdo);
check_mark((int) $pdo->query('SELECT COUNT(*) FROM bookmarks')->fetchColumn() === 4, 'Saving again created duplicate full-verse marks.');
save_reader_marks(10, [1, 2], 'bookmark', '', $pdo);
check_mark($pdo->query('SELECT highlight_color FROM bookmarks WHERE id = 1')->fetchColumn() === 'neon-orange', 'Bookmark action removed an existing highlight.');
check_mark((int) $pdo->query('SELECT COUNT(*) FROM bookmarks WHERE user_id = 10 AND verse_id = 2')->fetchColumn() === 1, 'Bookmark was not created.');

foreach ([[1,4], [1,5], [1,999], [], [1,[]], [0], ['1 OR 1=1'], array_fill(0,177,1)] as $invalid) {
    try {
        save_reader_marks(10, $invalid, 'highlight', 'neon-pink', $pdo);
        throw new RuntimeException('Invalid selection was accepted.');
    } catch (InvalidArgumentException $expected) {}
}
try {
    save_reader_marks(10, [1], 'highlight', 'invalid-color', $pdo);
    throw new RuntimeException('Invalid color was accepted.');
} catch (InvalidArgumentException $expected) {}

// A database error partway through a selection must leave every verse intact.
$pdo->exec("CREATE TRIGGER fail_mark BEFORE INSERT ON bookmarks WHEN NEW.user_id = 12 AND NEW.verse_id = 3 BEGIN SELECT RAISE(ABORT, 'test failure'); END");
try {
    save_reader_marks(12, [1, 3], 'highlight', 'neon-blue', $pdo);
    throw new RuntimeException('Expected a database failure.');
} catch (PDOException $expected) {}
check_mark((int) $pdo->query('SELECT COUNT(*) FROM bookmarks WHERE user_id = 12')->fetchColumn() === 0, 'A failed save left a partial selection behind.');

save_reader_marks(10, [1], 'clear-highlight', '', $pdo);
$cleared = $pdo->query('SELECT * FROM bookmarks WHERE user_id = 10 AND verse_id = 1')->fetchAll();
check_mark(count($cleared) === 2 && $cleared[0]['note'] === 'Keep this reflection' && $cleared[1]['note'] === 'Phrase note', 'Removing a highlight deleted annotations.');
check_mark(render_verse_text_with_highlights('beginning of a verse', $cleared) === 'beginning of a verse', 'Cleared partial highlights still rendered.');
check_mark(str_contains(render_verse_text_with_highlights('<script>text</script>', [['highlight_color' => 'neon-blue']]), '&lt;script&gt;'), 'Highlight rendering allowed HTML.');
check_mark(substr_count(render_verse_text_with_highlights('beginning of a verse', [['highlight_color' => 'neon-blue'], ['highlight_color' => 'neon-pink', 'selection_start' => 0, 'selection_end' => 9]]), '<mark') === 1, 'Whole-verse colors should match before and after reload.');
echo "Reader multi-select, annotation preservation, ownership, validation, atomic saves, and highlight rendering checks passed.\n";

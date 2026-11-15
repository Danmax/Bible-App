<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bible_repository.php';
require_once __DIR__ . '/../includes/library_search.php';

function check_search(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
$books = [];
foreach (canonical_book_definitions() as $id => $book) { $books[] = ['id' => $id] + $book; }
foreach (['John 3:16', 'john3:16', 'Jn3:16', 'Jn 3.16', 'JHN 3:16'] as $input) {
    $reference = parse_reference_query($input, $books);
    check_search($reference['book_id'] === 43 && $reference['chapter'] === 3 && $reference['start_verse'] === 16, 'Reference failed: ' . $input);
}
foreach (['Psalm 23', 'Psalms 23', 'Ps23'] as $input) { check_search(parse_reference_query($input, $books)['book_id'] === 19, 'Psalm alias failed'); }
foreach (['1 Cor 13:4–7', '1Cor13:4—7', '1 Cor 13:4-7'] as $input) { check_search(parse_reference_query($input, $books)['end_verse'] === 7, 'Pasted range failed'); }
check_search(parse_reference_query('John3:16-4:2', $books)['end_chapter'] === 4, 'Cross-chapter reference failed');
foreach (['John 999:1', 'John 3:18-16', 'John 0:0', 'John 3:0', 'John 4:1-3:2'] as $input) {
    try { parse_reference_query($input, $books); throw new RuntimeException('Invalid reference accepted: ' . $input); }
    catch (InvalidArgumentException $expected) { }
}
check_search(parse_reference_query('faith hope', $books) === null, 'Keyword was treated as a reference');
check_search(scripture_search_match(['verse_text' => 'a glove'], 'love', 'KJV', 'all') === null, 'Substring received a literal match');
check_search(scripture_search_match(['verse_text' => 'God is good.'], '"God is good"', 'KJV', 'all') !== null, 'Quoted stop words were lost');
check_search(scripture_search_match(['verse_text' => 'God alone is good.'], '"God is good"', 'KJV', 'all') === null, 'Quoted phrase was broadened');
check_search(scripture_search_match(['verse_text' => 'I am with you'], 'I am', 'KJV', 'all') !== null, 'Short-word phrase failed');
check_search(!str_contains(scripture_highlight_matches('<script>love</script>', 'love'), '<script>'), 'Unsafe result highlighting');

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, name TEXT, abbreviation TEXT, testament TEXT)');
$pdo->exec('CREATE TABLE verses (id INTEGER PRIMARY KEY, book_id INTEGER, chapter_number INTEGER, verse_number INTEGER, translation TEXT, verse_text TEXT)');
$pdo->exec("INSERT INTO books VALUES (1, 'Genesis', 'Gen', 'Old Testament'), (66, 'Revelation', 'Rev', 'New Testament')");
$insert = $pdo->prepare('INSERT INTO verses VALUES (?, ?, ?, ?, ?, ?)');
for ($i = 1; $i <= 450; $i++) { $insert->execute([$i, 1, 1, $i, 'KJV', 'Faith carries our hope through hardship']); }
$insert->execute([451, 66, 1, 1, 'KJV', 'Faith hope']);
$insert->execute([452, 66, 1, 2, 'KJV', 'God is good']);
$insert->execute([453, 66, 1, 3, 'WEB', 'Faith hope']);
$result = fetch_keyword_verses('faith hope', 'KJV', 20, 'relevance', [], $pdo);
check_search($result['total'] === 451 && $result['results'][0]['id'] === 451, 'Late-book high-relevance match was excluded');
$ids = [];
for ($page = 1; $page <= 23; $page++) {
    $result = fetch_keyword_verses('faith hope', 'KJV', 20, 'canonical', ['page' => $page], $pdo);
    array_push($ids, ...array_column($result['results'], 'id'));
}
check_search(count($ids) === 451 && count(array_unique($ids)) === 451 && end($ids) === 451, 'Pagination omitted or duplicated verses');
check_search(fetch_keyword_verses('faith hope', 'KJV', 20, 'canonical', ['scope' => 'new'], $pdo)['total'] === 1, 'Testament filter failed');
check_search(fetch_keyword_verses('faith hope', 'KJV', 20, 'canonical', ['book_id' => 1], $pdo)['total'] === 450, 'Book filter failed');
check_search(fetch_keyword_verses('God faithful', 'KJV', 20, 'relevance', [], $pdo)['total'] === 0, 'All-word mode broadened the query');
check_search(fetch_keyword_verses('God faithful', 'KJV', 20, 'relevance', ['match' => 'any'], $pdo)['total'] === 1, 'Any-word mode failed');
check_search(fetch_keyword_verses('faith hope', 'WEB', 20, 'canonical', [], $pdo)['total'] === 1, 'Translation filter failed');
check_search(fetch_keyword_verses('%_', 'KJV', 20, 'canonical', [], $pdo)['total'] === 0, 'Wildcard-only input was expanded');

$pdo->exec('CREATE TABLE bookmarks (id INTEGER PRIMARY KEY, user_id INTEGER, verse_id INTEGER, tag TEXT, note TEXT, created_at TEXT)');
$pdo->exec('CREATE TABLE study_notes (id INTEGER PRIMARY KEY, user_id INTEGER, verse_id INTEGER, title TEXT, content TEXT, updated_at TEXT)');
$pdo->exec("INSERT INTO bookmarks VALUES (1, 10, 452, 'comfort', 'personal peace', '2026-01-01'), (2, 11, 452, 'private', 'personal peace', '2026-01-01')");
$pdo->exec("INSERT INTO study_notes VALUES (1, 10, 452, 'Prayer', 'personal peace', '2026-01-02'), (2, 11, 452, 'Secret', 'personal peace', '2026-01-02')");
$result = fetch_library_matches(10, 'personal peace', 'all', 0, 1, $pdo);
check_search($result['total'] === 2 && count($result['results']) === 2, 'Library search leaked or omitted owned results');
check_search(fetch_library_matches(10, 'personal peace', 'notes', 0, 1, $pdo)['total'] === 1, 'Library type filter failed');
check_search(fetch_library_matches(10, 'personal peace', 'all', 1, 1, $pdo)['total'] === 0, 'Library book filter failed');
check_search(fetch_library_matches(12, 'personal peace', 'all', 0, 1, $pdo)['total'] === 0, 'Library search leaked another account');
echo "Scripture reference, ranking, paging, filters, highlighting, and Library ownership checks passed.\n";

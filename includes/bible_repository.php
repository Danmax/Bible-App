<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/system_repository.php';

function fetch_recent_notes(int $userId, int $limit = 3): array
{
    $statement = db()->prepare(
        'SELECT study_notes.*, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation
        FROM study_notes
        LEFT JOIN verses ON verses.id = study_notes.verse_id
        LEFT JOIN books ON books.id = verses.book_id
        WHERE study_notes.user_id = :user_id
        ORDER BY study_notes.updated_at DESC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function fetch_recent_bookmarks(int $userId, int $limit = 3): array
{
    $statement = db()->prepare(
        'SELECT bookmarks.*, verses.book_id, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation, verses.verse_text
        FROM bookmarks
        INNER JOIN verses ON verses.id = bookmarks.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE bookmarks.user_id = :user_id
        ORDER BY bookmarks.created_at DESC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function fetch_books(): array
{
    return db()->query('SELECT id, name, abbreviation FROM books ORDER BY id ASC')->fetchAll();
}

function fetch_book_catalog(string $translation): array
{
    if (uses_external_translation($translation)) {
        $translation = 'KJV';
    }

    $statement = db()->prepare(
        'SELECT books.id, books.name, books.abbreviation, books.testament, COUNT(DISTINCT verses.chapter_number) AS chapter_count
        FROM books
        LEFT JOIN verses
            ON verses.book_id = books.id
            AND verses.translation = :translation
        GROUP BY books.id, books.name, books.abbreviation, books.testament
        ORDER BY books.id ASC'
    );
    $statement->execute(['translation' => $translation]);

    return $statement->fetchAll();
}

function fetch_book_by_id(int $bookId): ?array
{
    $statement = db()->prepare('SELECT id, name, abbreviation, testament FROM books WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $bookId]);
    $book = $statement->fetch();

    return $book ?: null;
}

function fetch_book_chapters(int $bookId, string $translation): array
{
    if (uses_external_translation($translation)) {
        $translation = 'KJV';
    }

    $statement = db()->prepare(
        'SELECT chapter_number, COUNT(*) AS verse_count
        FROM verses
        WHERE book_id = :book_id AND translation = :translation
        GROUP BY chapter_number
        ORDER BY chapter_number ASC'
    );
    $statement->execute([
        'book_id' => $bookId,
        'translation' => $translation,
    ]);

    return $statement->fetchAll();
}

function fetch_chapter_verses(int $bookId, int $chapterNumber, string $translation): array
{
    if (uses_external_translation($translation)) {
        return fetch_external_translation_chapter_verses($bookId, $chapterNumber, $translation);
    }

    $statement = db()->prepare(
        'SELECT verses.*, books.name AS book_name, books.abbreviation
        FROM verses
        INNER JOIN books ON books.id = verses.book_id
        WHERE verses.book_id = :book_id
            AND verses.chapter_number = :chapter_number
            AND verses.translation = :translation
        ORDER BY verses.verse_number ASC'
    );
    $statement->execute([
        'book_id' => $bookId,
        'chapter_number' => $chapterNumber,
        'translation' => $translation,
    ]);

    return $statement->fetchAll();
}

function supported_translations(): array
{
    return ['MSB', 'KJV', 'WEB', 'NIV', 'NKJV', 'NLT', 'RVR'];
}

function fetch_available_translations(): array
{
    $statement = db()->query('SELECT DISTINCT translation FROM verses ORDER BY translation ASC');
    $storedTranslations = array_map(
        static fn(array $row): string => (string) $row['translation'],
        $statement->fetchAll()
    );

    $translations = array_values(array_unique(array_merge(supported_translations(), $storedTranslations)));

    return array_values(array_filter(
        $translations,
        static function (string $translation) use ($storedTranslations): bool {
            if (in_array($translation, $storedTranslations, true)) {
                return true;
            }

            return uses_external_translation($translation) && external_translation_available($translation);
        }
    ));
}

function uses_external_translation(string $translation): bool
{
    return external_translation_provider_config($translation) !== null;
}

function external_translation_available(string $translation): bool
{
    $provider = external_translation_provider_config($translation);

    if ($provider === null || !($provider['implemented'] ?? false)) {
        return false;
    }

    $envKey = (string) ($provider['env_key'] ?? '');

    if ($envKey === '') {
        return true;
    }

    return trim((string) getenv($envKey)) !== '';
}

function external_translation_provider_config(string $translation): ?array
{
    $translation = strtoupper(trim($translation));

    return match ($translation) {
        'NLT' => [
            'provider' => 'nlt',
            'env_key' => 'NLT_API_KEY',
            'implemented' => true,
        ],
        'NIV' => [
            'provider' => 'youversion',
            'env_key' => 'YOUVERSION_APP_KEY',
            'implemented' => false,
        ],
        default => null,
    };
}

function parse_reference_query(string $query, array $books): ?array
{
    $normalizedQuery = preg_replace('/\s+/', ' ', trim($query));

    if ($normalizedQuery === '') {
        return null;
    }

    if (!preg_match('/^(.+?)\s+(\d+)(?::(\d+)(?:-(\d+))?)?$/i', $normalizedQuery, $matches)) {
        return null;
    }

    $bookQuery = normalize_book_key($matches[1]);
    $chapter = (int) $matches[2];
    $startVerse = isset($matches[3]) ? (int) $matches[3] : null;
    $endVerse = isset($matches[4]) ? (int) $matches[4] : null;

    foreach ($books as $book) {
        $nameKey = normalize_book_key((string) $book['name']);
        $abbreviationKey = normalize_book_key((string) $book['abbreviation']);

        if ($bookQuery !== $nameKey && $bookQuery !== $abbreviationKey) {
            continue;
        }

        return [
            'book_id' => (int) $book['id'],
            'book_name' => (string) $book['name'],
            'chapter' => $chapter,
            'start_verse' => $startVerse,
            'end_verse' => $endVerse,
        ];
    }

    return null;
}

function search_scripture(string $query, string $translation): array
{
    if (uses_external_translation($translation)) {
        return search_external_translation($query, $translation);
    }

    $books = fetch_books();
    $reference = parse_reference_query($query, $books);

    if ($reference !== null) {
        return fetch_reference_verses($reference, $translation);
    }

    return fetch_keyword_verses($query, $translation);
}

function search_external_translation(string $query, string $translation): array
{
    $books = fetch_books();
    $reference = parse_reference_query($query, $books);

    if ($reference !== null) {
        return fetch_external_translation_reference_verses($reference, $translation);
    }

    return fetch_external_translation_keyword_verses($query, $translation, $books);
}

function fetch_external_translation_reference_verses(array $reference, string $translation): array
{
    $book = fetch_book_by_id((int) $reference['book_id']);

    if ($book === null) {
        return [
            'mode' => 'reference',
            'results' => [],
            'heading' => build_reference_heading($reference, $translation),
        ];
    }

    $referenceString = build_external_translation_reference_string(
        (string) $book['name'],
        (int) $reference['chapter'],
        isset($reference['start_verse']) ? (int) $reference['start_verse'] : null,
        isset($reference['end_verse']) ? (int) $reference['end_verse'] : null
    );

    $html = external_translation_api_get($translation, '/api/passages', [
        'ref' => $referenceString,
        'version' => $translation,
    ]);

    $verseIdMap = fetch_canonical_verse_id_map((int) $reference['book_id'], (int) $reference['chapter']);
    $verses = parse_external_translation_passage_html(
        $html,
        (int) $book['id'],
        (string) $book['name'],
        (string) $book['abbreviation'],
        (int) $reference['chapter'],
        $translation,
        $verseIdMap
    );

    return [
        'mode' => 'reference',
        'results' => $verses,
        'heading' => build_reference_heading($reference, $translation),
    ];
}

function fetch_external_translation_keyword_verses(string $query, string $translation, array $books, int $limit = 25): array
{
    $html = external_translation_api_get($translation, '/api/search', [
        'text' => trim($query),
        'version' => $translation,
    ]);

    return [
        'mode' => 'keyword',
        'results' => parse_external_translation_search_html($html, $books, $translation, $limit),
        'heading' => 'Search Results',
    ];
}

function fetch_reference_verses(array $reference, string $translation): array
{
    $sql = 'SELECT verses.*, books.name AS book_name, books.abbreviation
        FROM verses
        INNER JOIN books ON books.id = verses.book_id
        WHERE verses.book_id = :book_id
            AND verses.chapter_number = :chapter_number
            AND verses.translation = :translation';

    $params = [
        'book_id' => $reference['book_id'],
        'chapter_number' => $reference['chapter'],
        'translation' => $translation,
    ];

    if ($reference['start_verse'] !== null) {
        $sql .= ' AND verses.verse_number >= :start_verse';
        $params['start_verse'] = $reference['start_verse'];
    }

    if ($reference['end_verse'] !== null) {
        $sql .= ' AND verses.verse_number <= :end_verse';
        $params['end_verse'] = $reference['end_verse'];
    } elseif ($reference['start_verse'] !== null) {
        $sql .= ' AND verses.verse_number = :exact_verse';
        $params['exact_verse'] = $reference['start_verse'];
    }

    $sql .= ' ORDER BY verses.verse_number ASC';

    $statement = db()->prepare($sql);
    $statement->execute($params);
    $verses = $statement->fetchAll();

    return [
        'mode' => 'reference',
        'results' => $verses,
        'heading' => build_reference_heading($reference, $translation),
    ];
}

function fetch_keyword_verses(string $query, string $translation, int $limit = 25): array
{
    $statement = db()->prepare(
        'SELECT verses.*, books.name AS book_name, books.abbreviation
        FROM verses
        INNER JOIN books ON books.id = verses.book_id
        WHERE verses.translation = :translation
            AND verses.verse_text LIKE :query
        ORDER BY books.id ASC, verses.chapter_number ASC, verses.verse_number ASC
        LIMIT ' . (int) $limit
    );
    $statement->execute([
        'translation' => $translation,
        'query' => '%' . trim($query) . '%',
    ]);

    return [
        'mode' => 'keyword',
        'results' => $statement->fetchAll(),
        'heading' => 'Search Results',
    ];
}

function fetch_featured_verses(string $translation, int $limit = 3): array
{
    if (uses_external_translation($translation)) {
        $reference = [
            'book_id' => 43,
            'book_name' => 'John',
            'chapter' => 3,
            'start_verse' => 16,
            'end_verse' => null,
        ];

        return fetch_external_translation_reference_verses($reference, $translation)['results'];
    }

    $statement = db()->prepare(
        'SELECT verses.*, books.name AS book_name, books.abbreviation
        FROM verses
        INNER JOIN books ON books.id = verses.book_id
        WHERE verses.translation = :translation
            AND (
                (books.name = :psalms AND verses.chapter_number = 23 AND verses.verse_number BETWEEN 1 AND 3)
                OR (books.name = :john AND verses.chapter_number = 3 AND verses.verse_number = 16)
                OR (books.name = :proverbs AND verses.chapter_number = 3 AND verses.verse_number BETWEEN 5 AND 6)
            )
        ORDER BY books.id ASC, verses.chapter_number ASC, verses.verse_number ASC
        LIMIT ' . (int) $limit
    );
    $statement->execute([
        'translation' => $translation,
        'psalms' => 'Psalms',
        'john' => 'John',
        'proverbs' => 'Proverbs',
    ]);

    return $statement->fetchAll();
}

function fetch_dynamic_scripture_series(string $translation, int $limit = 4): array
{
    $limit = max(1, $limit);

    if (uses_external_translation($translation)) {
        return array_slice(fetch_featured_verses($translation, $limit), 0, $limit);
    }

    $total = count_records(
        'SELECT COUNT(*) FROM verses WHERE translation = :translation',
        ['translation' => $translation]
    );

    if ($total <= 0) {
        return [];
    }

    $targetCount = min($limit, $total);
    $seed = (int) date('z') + 1;
    $offsets = [];
    $attempt = 0;

    while (count($offsets) < $targetCount && $attempt < ($targetCount * 10)) {
        $offset = (($seed * 97) + ($attempt * 389)) % $total;

        if (!in_array($offset, $offsets, true)) {
            $offsets[] = $offset;
        }

        $attempt++;
    }

    sort($offsets);
    $series = [];

    foreach ($offsets as $offset) {
        $statement = db()->prepare(
            'SELECT verses.*, books.name AS book_name, books.abbreviation
            FROM verses
            INNER JOIN books ON books.id = verses.book_id
            WHERE verses.translation = :translation
            ORDER BY books.id ASC, verses.chapter_number ASC, verses.verse_number ASC
            LIMIT 1 OFFSET ' . (int) $offset
        );
        $statement->execute(['translation' => $translation]);
        $verse = $statement->fetch();

        if ($verse !== false) {
            $series[] = $verse;
        }
    }

    return $series;
}

function fetch_thematic_scripture_series(string $translation): array
{
    $themes = [
        ['theme' => 'Hope', 'query' => 'Romans 15:13'],
        ['theme' => 'Wisdom', 'query' => 'James 1:5'],
        ['theme' => 'Peace', 'query' => 'Isaiah 26:3'],
        ['theme' => 'Faith', 'query' => 'Hebrews 11:1'],
    ];
    $books = fetch_books();
    $series = [];

    foreach ($themes as $theme) {
        $reference = parse_reference_query((string) $theme['query'], $books);

        if ($reference === null) {
            continue;
        }

        $results = uses_external_translation($translation)
            ? fetch_external_translation_reference_verses($reference, $translation)
            : fetch_reference_verses($reference, $translation);

        $verse = $results['results'][0] ?? null;

        if ($verse === null) {
            continue;
        }

        $series[] = [
            'theme' => (string) $theme['theme'],
            'query' => (string) $theme['query'],
            'verse' => $verse,
        ];
    }

    return $series;
}

function fetch_verse_by_id(int $verseId): ?array
{
    $statement = db()->prepare(
        'SELECT verses.*, books.name AS book_name, books.abbreviation
        FROM verses
        INNER JOIN books ON books.id = verses.book_id
        WHERE verses.id = :id
        LIMIT 1'
    );
    $statement->execute(['id' => $verseId]);
    $verse = $statement->fetch();

    return $verse ?: null;
}

function is_bookmarked(int $userId, int $verseId): bool
{
    return count_records(
        'SELECT COUNT(*) FROM bookmarks WHERE user_id = :user_id AND verse_id = :verse_id',
        ['user_id' => $userId, 'verse_id' => $verseId]
    ) > 0;
}

function save_bookmark_record(
    int $userId,
    int $verseId,
    string $tag = '',
    string $note = '',
    ?string $selectedText = null,
    ?string $highlightColor = null,
    ?int $selectionStart = null,
    ?int $selectionEnd = null
): void
{
    $normalizedTag = normalize_optional_text($tag);
    $normalizedNote = normalize_optional_text($note);
    $normalizedSelectedText = normalize_optional_text($selectedText ?? '');
    $normalizedColor = normalize_optional_text($highlightColor ?? '');
    $isSectionBookmark = $normalizedSelectedText !== null || $selectionStart !== null || $selectionEnd !== null;

    if (!$isSectionBookmark) {
        $existing = fetch_full_verse_bookmark($userId, $verseId);

        if ($existing !== null) {
            $statement = db()->prepare(
                'UPDATE bookmarks
                SET tag = :tag, note = :note
                WHERE id = :id AND user_id = :user_id'
            );
            $statement->execute([
                'id' => $existing['id'],
                'user_id' => $userId,
                'tag' => $normalizedTag,
                'note' => $normalizedNote,
            ]);

            return;
        }
    }

    $statement = db()->prepare(
        'INSERT INTO bookmarks (
            user_id,
            verse_id,
            tag,
            note,
            selected_text,
            highlight_color,
            selection_start,
            selection_end
        ) VALUES (
            :user_id,
            :verse_id,
            :tag,
            :note,
            :selected_text,
            :highlight_color,
            :selection_start,
            :selection_end
        )'
    );
    $statement->execute([
        'user_id' => $userId,
        'verse_id' => $verseId,
        'tag' => $normalizedTag,
        'note' => $normalizedNote,
        'selected_text' => $normalizedSelectedText,
        'highlight_color' => $normalizedColor ?: null,
        'selection_start' => $selectionStart,
        'selection_end' => $selectionEnd,
    ]);
}

function fetch_full_verse_bookmark(int $userId, int $verseId): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM bookmarks
        WHERE user_id = :user_id
            AND verse_id = :verse_id
            AND selected_text IS NULL
            AND selection_start IS NULL
            AND selection_end IS NULL
        LIMIT 1'
    );
    $statement->execute([
        'user_id' => $userId,
        'verse_id' => $verseId,
    ]);

    $bookmark = $statement->fetch();

    return $bookmark ?: null;
}

function fetch_favorite_bookmarks(int $userId, int $limit = 8): array
{
    $statement = db()->prepare(
        'SELECT bookmarks.*, verses.book_id, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation, verses.verse_text
        FROM bookmarks
        INNER JOIN verses ON verses.id = bookmarks.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE bookmarks.user_id = :user_id
        ORDER BY bookmarks.created_at DESC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function fetch_bookmarks_for_verses(int $userId, array $verseIds): array
{
    if ($verseIds === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($verseIds), '?'));
    $params = array_merge([$userId], array_map('intval', $verseIds));

    $statement = db()->prepare(
        'SELECT *
        FROM bookmarks
        WHERE user_id = ?
            AND verse_id IN (' . $placeholders . ')
        ORDER BY created_at ASC'
    );
    $statement->execute($params);

    $grouped = [];

    foreach ($statement->fetchAll() as $bookmark) {
        $grouped[(int) $bookmark['verse_id']][] = $bookmark;
    }

    return $grouped;
}

function update_bookmark_record(
    int $bookmarkId,
    int $userId,
    string $tag,
    string $note,
    ?string $highlightColor = null
): void
{
    $statement = db()->prepare(
        'UPDATE bookmarks
        SET tag = :tag, note = :note, highlight_color = :highlight_color
        WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $bookmarkId,
        'user_id' => $userId,
        'tag' => normalize_optional_text($tag),
        'note' => normalize_optional_text($note),
        'highlight_color' => normalize_optional_text($highlightColor ?? ''),
    ]);
}

function fetch_bookmark(int $bookmarkId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM bookmarks
        WHERE id = :id AND user_id = :user_id
        LIMIT 1'
    );
    $statement->execute(['id' => $bookmarkId, 'user_id' => $userId]);
    $bookmark = $statement->fetch();

    return $bookmark ?: null;
}

function fetch_bookmarks(int $userId): array
{
    $statement = db()->prepare(
        'SELECT bookmarks.*, verses.book_id, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation, verses.verse_text
        FROM bookmarks
        INNER JOIN verses ON verses.id = bookmarks.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE bookmarks.user_id = :user_id
        ORDER BY bookmarks.created_at DESC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function delete_bookmark_record(int $bookmarkId, int $userId): void
{
    $statement = db()->prepare('DELETE FROM bookmarks WHERE id = :id AND user_id = :user_id');
    $statement->execute(['id' => $bookmarkId, 'user_id' => $userId]);
}

function fetch_notes(int $userId): array
{
    $statement = db()->prepare(
        'SELECT study_notes.*, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation
        FROM study_notes
        LEFT JOIN verses ON verses.id = study_notes.verse_id
        LEFT JOIN books ON books.id = verses.book_id
        WHERE study_notes.user_id = :user_id
        ORDER BY study_notes.updated_at DESC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function fetch_note(int $noteId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT * FROM study_notes WHERE id = :id AND user_id = :user_id LIMIT 1'
    );
    $statement->execute(['id' => $noteId, 'user_id' => $userId]);
    $note = $statement->fetch();

    return $note ?: null;
}

function create_note_record(int $userId, string $title, string $content, ?int $verseId = null): void
{
    $statement = db()->prepare(
        'INSERT INTO study_notes (user_id, verse_id, title, content) VALUES (:user_id, :verse_id, :title, :content)'
    );
    $statement->execute([
        'user_id' => $userId,
        'verse_id' => $verseId,
        'title' => trim($title),
        'content' => trim($content),
    ]);
}

function update_note_record(int $noteId, int $userId, string $title, string $content, ?int $verseId = null): void
{
    $statement = db()->prepare(
        'UPDATE study_notes
        SET verse_id = :verse_id, title = :title, content = :content
        WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $noteId,
        'user_id' => $userId,
        'verse_id' => $verseId,
        'title' => trim($title),
        'content' => trim($content),
    ]);
}

function delete_note_record(int $noteId, int $userId): void
{
    $statement = db()->prepare('DELETE FROM study_notes WHERE id = :id AND user_id = :user_id');
    $statement->execute(['id' => $noteId, 'user_id' => $userId]);
}

function fetch_noteable_verses(int $userId): array
{
    $statement = db()->prepare(
        'SELECT DISTINCT verses.id, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation
        FROM bookmarks
        INNER JOIN verses ON verses.id = bookmarks.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE bookmarks.user_id = :user_id
        ORDER BY bookmarks.created_at DESC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function format_verse_reference(array $verse): string
{
    return sprintf(
        '%s %d:%d (%s)',
        $verse['book_name'],
        $verse['chapter_number'],
        $verse['verse_number'],
        $verse['translation']
    );
}

function build_reference_heading(array $reference, string $translation): string
{
    $heading = sprintf('%s %d', $reference['book_name'], $reference['chapter']);

    if ($reference['start_verse'] !== null && $reference['end_verse'] !== null) {
        $heading .= sprintf(':%d-%d', $reference['start_verse'], $reference['end_verse']);
    } elseif ($reference['start_verse'] !== null) {
        $heading .= sprintf(':%d', $reference['start_verse']);
    }

    return $heading . ' (' . $translation . ')';
}

function fetch_external_translation_chapter_verses(int $bookId, int $chapterNumber, string $translation): array
{
    $book = fetch_book_by_id($bookId);

    if ($book === null) {
        return [];
    }

    $html = external_translation_api_get($translation, '/api/passages', [
        'ref' => build_external_translation_reference_string((string) $book['name'], $chapterNumber),
        'version' => $translation,
    ]);

    return parse_external_translation_passage_html(
        $html,
        (int) $book['id'],
        (string) $book['name'],
        (string) $book['abbreviation'],
        $chapterNumber,
        $translation,
        fetch_canonical_verse_id_map($bookId, $chapterNumber)
    );
}

function fetch_canonical_verse_id_map(int $bookId, int $chapterNumber): array
{
    $statement = db()->prepare(
        'SELECT verse_number, id
        FROM verses
        WHERE book_id = :book_id
            AND chapter_number = :chapter_number
            AND translation = :translation
        ORDER BY verse_number ASC'
    );
    $statement->execute([
        'book_id' => $bookId,
        'chapter_number' => $chapterNumber,
        'translation' => 'KJV',
    ]);

    $map = [];

    foreach ($statement->fetchAll() as $row) {
        $map[(int) $row['verse_number']] = (int) $row['id'];
    }

    return $map;
}

function fetch_canonical_verse_id(int $bookId, int $chapterNumber, int $verseNumber): ?int
{
    $statement = db()->prepare(
        'SELECT id
        FROM verses
        WHERE book_id = :book_id
            AND chapter_number = :chapter_number
            AND verse_number = :verse_number
        ORDER BY CASE WHEN translation = :preferred_translation THEN 0 ELSE 1 END, id ASC
        LIMIT 1'
    );
    $statement->execute([
        'book_id' => $bookId,
        'chapter_number' => $chapterNumber,
        'verse_number' => $verseNumber,
        'preferred_translation' => 'KJV',
    ]);

    $value = $statement->fetchColumn();

    return $value === false ? null : (int) $value;
}

function build_external_translation_reference_string(
    string $bookName,
    int $chapterNumber,
    ?int $startVerse = null,
    ?int $endVerse = null
): string {
    $reference = $bookName . ' ' . $chapterNumber;

    if ($startVerse !== null && $endVerse !== null) {
        $reference .= ':' . $startVerse . '-' . $endVerse;
    } elseif ($startVerse !== null) {
        $reference .= ':' . $startVerse;
    }

    return $reference;
}

function external_translation_api_get(string $translation, string $path, array $params): string
{
    $provider = external_translation_provider_config($translation);

    if ($provider === null) {
        throw new RuntimeException('No external provider is configured for ' . strtoupper(trim($translation)) . '.');
    }

    $providerName = (string) ($provider['provider'] ?? '');
    $request = build_external_translation_request($providerName, $path, $params);
    $url = $request['url'];
    $headers = $request['headers'];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($response === false || $status >= 400) {
            throw new RuntimeException(build_external_translation_error_message($translation, $error));
        }

        return (string) $response;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => implode("\r\n", $headers) . "\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        throw new RuntimeException(build_external_translation_error_message($translation));
    }

    return $response;
}

function build_external_translation_request(string $provider, string $path, array $params): array
{
    return match ($provider) {
        'nlt' => build_nlt_translation_request($path, $params),
        'youversion' => throw new RuntimeException(
            'NIV support via YouVersion is configured but not implemented yet. Add the YouVersion request details or app key so it can be completed.'
        ),
        default => throw new RuntimeException('Unsupported external translation provider: ' . $provider . '.'),
    };
}

function build_nlt_translation_request(string $path, array $params): array
{
    $apiKey = trim((string) (getenv('NLT_API_KEY') ?: 'TEST'));
    $params['key'] = $apiKey;

    return [
        'url' => 'https://api.nlt.to' . $path . '?' . http_build_query($params),
        'headers' => ['Accept: text/html,application/json'],
    ];
}

function build_external_translation_error_message(string $translation, string $transportError = ''): string
{
    $message = 'The ' . strtoupper(trim($translation)) . ' API request failed';

    if ($transportError !== '') {
        $message .= ': ' . $transportError;
    } else {
        $message .= '.';
    }

    return $message;
}

function parse_external_translation_passage_html(
    string $html,
    int $bookId,
    string $bookName,
    string $abbreviation,
    int $chapterNumber,
    string $translation,
    array $verseIdMap
): array {
    if (!preg_match_all('/<verse_export\b([^>]*)>(.*?)<\/verse_export>/is', $html, $matches, PREG_SET_ORDER)) {
        return [];
    }

    $verses = [];

    foreach ($matches as $match) {
        $attributes = parse_external_translation_attributes($match[1]);
        $verseNumber = isset($attributes['vn']) ? (int) $attributes['vn'] : 0;

        if ($verseNumber <= 0) {
            continue;
        }

        $verseText = sanitize_external_translation_html($match[2]);

        if ($verseText === '') {
            continue;
        }

        $verses[] = [
            'id' => $verseIdMap[$verseNumber] ?? fetch_canonical_verse_id($bookId, $chapterNumber, $verseNumber) ?? 0,
            'book_id' => $bookId,
            'book_name' => $bookName,
            'abbreviation' => $abbreviation,
            'chapter_number' => $chapterNumber,
            'verse_number' => $verseNumber,
            'verse_text' => $verseText,
            'translation' => $translation,
        ];
    }

    return $verses;
}

function parse_external_translation_search_html(string $html, array $books, string $translation, int $limit = 25): array
{
    if (!preg_match_all('/<tr>\s*<td><a[^>]*>([^<]+)<\/a><\/td>\s*<td>(.*?)<\/td>\s*<\/tr>/is', $html, $matches, PREG_SET_ORDER)) {
        return [];
    }

    $results = [];

    foreach ($matches as $match) {
        $reference = parse_external_translation_dot_reference(trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')), $books);

        if ($reference === null) {
            continue;
        }

        $results[] = [
            'id' => fetch_canonical_verse_id($reference['book_id'], $reference['chapter_number'], $reference['verse_number']) ?? 0,
            'book_id' => $reference['book_id'],
            'book_name' => $reference['book_name'],
            'abbreviation' => $reference['abbreviation'],
            'chapter_number' => $reference['chapter_number'],
            'verse_number' => $reference['verse_number'],
            'verse_text' => sanitize_external_translation_html($match[2]),
            'translation' => $translation,
        ];

        if (count($results) >= $limit) {
            break;
        }
    }

    return $results;
}

function parse_external_translation_attributes(string $attributeString): array
{
    $attributes = [];

    if (preg_match_all('/([a-z_]+)="([^"]*)"/i', $attributeString, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $attributes[strtolower($match[1])] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    return $attributes;
}

function sanitize_external_translation_html(string $html): string
{
    $clean = preg_replace('/<a\b[^>]*class="a-tn"[^>]*>.*?<\/a>/is', '', $html) ?? $html;
    $clean = preg_replace('/<span\b[^>]*class="tn"[^>]*>.*?<\/span>/is', '', $clean) ?? $clean;
    $clean = preg_replace('/<span\b[^>]*class="vn"[^>]*>.*?<\/span>/is', '', $clean) ?? $clean;
    $clean = preg_replace('/<h[23]\b[^>]*>.*?<\/h[23]>/is', '', $clean) ?? $clean;
    $clean = preg_replace('/<\/p>\s*<p[^>]*>/i', ' ', $clean) ?? $clean;
    $clean = preg_replace('/<br\s*\/?>/i', ' ', $clean) ?? $clean;
    $clean = strip_tags($clean);
    $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;

    return trim($clean);
}

function parse_external_translation_dot_reference(string $reference, array $books): ?array
{
    if (!preg_match('/^([1-3]?\s*[A-Za-z]+)\.(\d+)\.(\d+)$/', str_replace(' ', '', $reference), $matches)) {
        return null;
    }

    $bookToken = normalize_book_key($matches[1]);
    $chapterNumber = (int) $matches[2];
    $verseNumber = (int) $matches[3];
    $lookup = external_translation_book_alias_lookup($books);
    $book = $lookup[$bookToken] ?? null;

    if ($book === null) {
        return null;
    }

    return [
        'book_id' => (int) $book['id'],
        'book_name' => (string) $book['name'],
        'abbreviation' => (string) $book['abbreviation'],
        'chapter_number' => $chapterNumber,
        'verse_number' => $verseNumber,
    ];
}

function external_translation_book_alias_lookup(array $books): array
{
    static $lookup = null;

    if ($lookup !== null) {
        return $lookup;
    }

    $lookup = [];

    foreach ($books as $book) {
        $aliases = [
            (string) $book['name'],
            (string) $book['abbreviation'],
        ];

        foreach (external_translation_manual_aliases() as $alias => $canonicalName) {
            if (strcasecmp((string) $book['name'], $canonicalName) === 0) {
                $aliases[] = $alias;
            }
        }

        foreach ($aliases as $alias) {
            $lookup[normalize_book_key($alias)] = $book;
        }
    }

    return $lookup;
}

function external_translation_manual_aliases(): array
{
    return [
        'Gen' => 'Genesis',
        'Exod' => 'Exodus',
        'Judg' => 'Judges',
        '1Sam' => '1 Samuel',
        '2Sam' => '2 Samuel',
        '1Kgs' => '1 Kings',
        '2Kgs' => '2 Kings',
        '1Chr' => '1 Chronicles',
        '2Chr' => '2 Chronicles',
        'Esth' => 'Esther',
        'Ps' => 'Psalms',
        'Pr' => 'Proverbs',
        'Prov' => 'Proverbs',
        'Eccl' => 'Ecclesiastes',
        'Ezek' => 'Ezekiel',
        'Obad' => 'Obadiah',
        'Zech' => 'Zechariah',
        'Matt' => 'Matthew',
        'Mk' => 'Mark',
        'Lk' => 'Luke',
        'Jn' => 'John',
        'Ac' => 'Acts',
        'Rom' => 'Romans',
        '1Cor' => '1 Corinthians',
        '2Cor' => '2 Corinthians',
        '1Thess' => '1 Thessalonians',
        '2Thess' => '2 Thessalonians',
        '1Tim' => '1 Timothy',
        '2Tim' => '2 Timothy',
        'Phlm' => 'Philemon',
        'Jas' => 'James',
        '1Pet' => '1 Peter',
        '2Pet' => '2 Peter',
        '1John' => '1 John',
        '2John' => '2 John',
        '3John' => '3 John',
        'Rev' => 'Revelation',
    ];
}

function youversion_version_id(string $translation): int
{
    $translation = strtoupper(trim($translation));
    
    return match ($translation) {
        'NIV' => 111,
        default => 1,
    };
}

function youversion_usfm_book(string $bookName): string
{
    $map = [
        'Genesis' => 'GEN', 'Exodus' => 'EXO', 'Leviticus' => 'LEV', 'Numbers' => 'NUM',
        'Deuteronomy' => 'DEU', 'Joshua' => 'JOS', 'Judges' => 'JDG', 'Ruth' => 'RUT',
        '1 Samuel' => '1SA', '2 Samuel' => '2SA', '1 Kings' => '1KI', '2 Kings' => '2KI',
        '1 Chronicles' => '1CH', '2 Chronicles' => '2CH', 'Ezra' => 'EZR', 'Nehemiah' => 'NEH',
        'Esther' => 'EST', 'Job' => 'JOB', 'Psalms' => 'PSA', 'Proverbs' => 'PRO',
        'Ecclesiastes' => 'ECC', 'Song of Solomon' => 'SNG', 'Isaiah' => 'ISA', 'Jeremiah' => 'JER',
        'Lamentations' => 'LAM', 'Ezekiel' => 'EZE', 'Daniel' => 'DAN', 'Hosea' => 'HOS',
        'Joel' => 'JOL', 'Amos' => 'AMO', 'Obadiah' => 'OBA', 'Jonah' => 'JON',
        'Micah' => 'MIC', 'Nahum' => 'NAH', 'Habakkuk' => 'HAB', 'Zephaniah' => 'ZEP',
        'Haggai' => 'HAG', 'Zechariah' => 'ZEC', 'Malachi' => 'MAL',
        'Matthew' => 'MAT', 'Mark' => 'MRK', 'Luke' => 'LUK', 'John' => 'JHN',
        'Acts' => 'ACT', 'Romans' => 'ROM', '1 Corinthians' => '1CO', '2 Corinthians' => '2CO',
        'Galatians' => 'GAL', 'Ephesians' => 'EPH', 'Philippians' => 'PHP', 'Colossians' => 'COL',
        '1 Thessalonians' => '1TH', '2 Thessalonians' => '2TH', '1 Timothy' => '1TI', '2 Timothy' => '2TI',
        'Titus' => 'TIT', 'Philemon' => 'PHM', 'Hebrews' => 'HEB', 'James' => 'JAS',
        '1 Peter' => '1PE', '2 Peter' => '2PE', '1 John' => '1JN', '2 John' => '2JN',
        '3 John' => '3JN', 'Jude' => 'JUD', 'Revelation' => 'REV'
    ];
    
    return $map[$bookName] ?? 'GEN';
}

function parse_youversion_passage_html(
    string $html,
    int $bookId,
    string $bookName,
    string $abbreviation,
    int $chapterNumber,
    string $translation,
    array $verseIdMap
): array {
    $html = preg_replace('/<span class="v"[^>]*>(\d+)[^<]*<\/span>/i', '|||v$1|||', $html);
    $cleanText = strip_tags((string) $html);
    $parts = explode('|||v', $cleanText);
    
    $verses = [];
    foreach ($parts as $part) {
        $subparts = explode('|||', $part, 2);
        if (count($subparts) !== 2) continue;
        
        $verseNumber = (int) $subparts[0];
        $verseText = trim(html_entity_decode($subparts[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $verseText = preg_replace('/\s+/u', ' ', $verseText);
        
        if ($verseNumber <= 0 || $verseText === '') continue;
        
        $verses[] = [
            'id' => $verseIdMap[$verseNumber] ?? fetch_canonical_verse_id($bookId, $chapterNumber, $verseNumber) ?? 0,
            'book_id' => $bookId,
            'book_name' => $bookName,
            'abbreviation' => $abbreviation,
            'chapter_number' => $chapterNumber,
            'verse_number' => $verseNumber,
            'verse_text' => (string) $verseText,
            'translation' => $translation,
        ];
    }
    
    return $verses;
}

function fetch_youversion_chapter_verses(int $bookId, int $chapterNumber, string $translation): array
{
    $book = fetch_book_by_id($bookId);
    if ($book === null) {
        return [];
    }

    $usfmBook = youversion_usfm_book((string) $book['name']);
    $versionId = youversion_version_id($translation);
    $path = '/chapter/' . $versionId . '.' . $usfmBook . '.' . $chapterNumber;
    
    $json = external_translation_api_get($translation, $path, []);
    $data = json_decode($json, true);
    $html = $data['data']['content'] ?? '';

    return parse_youversion_passage_html(
        $html,
        (int) $book['id'],
        (string) $book['name'],
        (string) $book['abbreviation'],
        $chapterNumber,
        $translation,
        fetch_canonical_verse_id_map($bookId, $chapterNumber)
    );
}

function fetch_youversion_reference_verses(array $reference, string $translation): array
{
    $book = fetch_book_by_id((int) $reference['book_id']);
    if ($book === null) {
        return [
            'mode' => 'reference',
            'results' => [],
            'heading' => build_reference_heading($reference, $translation),
        ];
    }

    $allChapterVerses = fetch_youversion_chapter_verses((int) $book['id'], (int) $reference['chapter'], $translation);
    
    $startVerse = $reference['start_verse'] !== null ? (int) $reference['start_verse'] : null;
    $endVerse = $reference['end_verse'] !== null ? (int) $reference['end_verse'] : $startVerse;

    $results = [];
    foreach ($allChapterVerses as $verse) {
        $vn = (int) $verse['verse_number'];
        if ($startVerse !== null) {
            if ($vn >= $startVerse && ($endVerse === null || $vn <= $endVerse)) {
                $results[] = $verse;
            }
        } else {
            $results[] = $verse;
        }
    }

    return [
        'mode' => 'reference',
        'results' => $results,
        'heading' => build_reference_heading($reference, $translation),
    ];
}

function fetch_youversion_keyword_verses(string $query, string $translation, array $books, int $limit = 25): array
{
    $versionId = youversion_version_id($translation);
    $json = external_translation_api_get($translation, '/search', [
        'query' => trim($query),
        'version_id' => $versionId,
    ]);
    
    $data = json_decode($json, true);
    $apiVerses = $data['data']['verses'] ?? [];
    
    $results = [];
    foreach ($apiVerses as $v) {
        $usfmId = $v['id'] ?? '';
        if (!$usfmId) continue;
        
        $parts = explode('.', $usfmId);
        if (count($parts) < 3) continue;
        
        $usfmBook = $parts[0];
        $chapter = (int) $parts[1];
        $verseNum = (int) $parts[2];
        
        $matchedBook = null;
        foreach ($books as $b) {
            if (youversion_usfm_book((string) $b['name']) === $usfmBook) {
                $matchedBook = $b;
                break;
            }
        }
        
        if (!$matchedBook) continue;

        $results[] = [
            'id' => fetch_canonical_verse_id((int) $matchedBook['id'], $chapter, $verseNum) ?? 0,
            'book_id' => (int) $matchedBook['id'],
            'book_name' => (string) $matchedBook['name'],
            'abbreviation' => (string) $matchedBook['abbreviation'],
            'chapter_number' => $chapter,
            'verse_number' => $verseNum,
            'verse_text' => sanitize_external_translation_html((string) ($v['text'] ?? '')),
            'translation' => $translation,
        ];
        
        if (count($results) >= $limit) break;
    }
    
    return [
        'mode' => 'keyword',
        'results' => $results,
        'heading' => 'Search Results',
    ];
}
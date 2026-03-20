<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function fetch_user_by_email(string $email): ?array
{
    $statement = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => mb_strtolower(trim($email))]);
    $user = $statement->fetch();

    return $user ?: null;
}

function fetch_user_by_id(int $userId): ?array
{
    $statement = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    return $user ?: null;
}

function create_user(string $name, string $email, string $password): array
{
    $normalizedEmail = mb_strtolower(trim($email));

    $statement = db()->prepare(
        'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)'
    );
    $statement->execute([
        'name' => trim($name),
        'email' => $normalizedEmail,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'member',
    ]);

    $user = fetch_user_by_id((int) db()->lastInsertId());

    if ($user === null) {
        throw new RuntimeException('User record was not created.');
    }

    return $user;
}

function update_user_profile_record(int $userId, string $name, string $email): array
{
    $statement = db()->prepare(
        'UPDATE users SET name = :name, email = :email WHERE id = :id'
    );
    $statement->execute([
        'id' => $userId,
        'name' => trim($name),
        'email' => mb_strtolower(trim($email)),
    ]);

    $user = fetch_user_by_id($userId);

    if ($user === null) {
        throw new RuntimeException('User record was not found after update.');
    }

    return $user;
}

function update_user_password_record(int $userId, string $password): void
{
    $statement = db()->prepare(
        'UPDATE users SET password_hash = :password_hash WHERE id = :id'
    );
    $statement->execute([
        'id' => $userId,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
}

function find_user_for_login(string $email, string $password): ?array
{
    $user = fetch_user_by_email($email);

    if ($user === null) {
        return null;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }

    return $user;
}

function create_password_reset_token(int $userId): string
{
    db()->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id')
        ->execute(['user_id' => $userId]);

    $token = bin2hex(random_bytes(24));
    $statement = db()->prepare(
        'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)'
    );
    $statement->execute([
        'user_id' => $userId,
        'token_hash' => hash('sha256', $token),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
    ]);

    return $token;
}

function fetch_password_reset_token(string $token): ?array
{
    $statement = db()->prepare(
        'SELECT password_reset_tokens.*, users.email
        FROM password_reset_tokens
        INNER JOIN users ON users.id = password_reset_tokens.user_id
        WHERE token_hash = :token_hash
            AND used_at IS NULL
            AND expires_at >= NOW()
        LIMIT 1'
    );
    $statement->execute([
        'token_hash' => hash('sha256', $token),
    ]);

    $record = $statement->fetch();

    return $record ?: null;
}

function reset_user_password_with_token(string $token, string $password): ?int
{
    $record = fetch_password_reset_token($token);

    if ($record === null) {
        return null;
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        update_user_password_record((int) $record['user_id'], $password);

        $statement = $pdo->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id'
        );
        $statement->execute(['id' => $record['id']]);

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    return (int) $record['user_id'];
}

function fetch_dashboard_stats(int $userId): array
{
    return [
        'bookmarks' => count_records(
            'SELECT COUNT(*) FROM bookmarks WHERE user_id = :user_id',
            ['user_id' => $userId]
        ),
        'notes' => count_records(
            'SELECT COUNT(*) FROM study_notes WHERE user_id = :user_id',
            ['user_id' => $userId]
        ),
        'goals' => count_records(
            'SELECT COUNT(*) FROM yearly_goals WHERE user_id = :user_id AND status = :status',
            ['user_id' => $userId, 'status' => 'active']
        ),
        'events' => count_records(
            'SELECT COUNT(*) FROM community_events WHERE status = :status AND start_at >= NOW()',
            ['status' => 'published']
        ),
    ];
}

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
        'SELECT bookmarks.*, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation, verses.verse_text
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

function fetch_upcoming_events(int $limit = 3): array
{
    $statement = db()->prepare(
        'SELECT community_events.*, community_event_categories.label AS category_label
        FROM community_events
        LEFT JOIN community_event_categories ON community_event_categories.id = community_events.category_id
        WHERE community_events.status = :status
            AND community_events.start_at >= NOW()
        ORDER BY community_events.start_at ASC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['status' => 'published']);

    return $statement->fetchAll();
}

function fetch_books(): array
{
    return db()->query('SELECT id, name, abbreviation FROM books ORDER BY id ASC')->fetchAll();
}

function fetch_book_catalog(string $translation): array
{
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
    return ['KJV', 'NIV', 'NKJV', 'NLT', 'RVR'];
}

function fetch_available_translations(): array
{
    $statement = db()->query('SELECT DISTINCT translation FROM verses ORDER BY translation ASC');
    $translations = array_map(
        static fn(array $row): string => (string) $row['translation'],
        $statement->fetchAll()
    );

    return array_values(array_unique(array_merge(supported_translations(), $translations)));
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
    $books = fetch_books();
    $reference = parse_reference_query($query, $books);

    if ($reference !== null) {
        return fetch_reference_verses($reference, $translation);
    }

    return fetch_keyword_verses($query, $translation);
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

function save_bookmark_record(int $userId, int $verseId, string $tag = '', string $note = ''): void
{
    if (is_bookmarked($userId, $verseId)) {
        $statement = db()->prepare(
            'UPDATE bookmarks SET tag = :tag, note = :note WHERE user_id = :user_id AND verse_id = :verse_id'
        );
        $statement->execute([
            'user_id' => $userId,
            'verse_id' => $verseId,
            'tag' => normalize_optional_text($tag),
            'note' => normalize_optional_text($note),
        ]);

        return;
    }

    $statement = db()->prepare(
        'INSERT INTO bookmarks (user_id, verse_id, tag, note) VALUES (:user_id, :verse_id, :tag, :note)'
    );
    $statement->execute([
        'user_id' => $userId,
        'verse_id' => $verseId,
        'tag' => normalize_optional_text($tag),
        'note' => normalize_optional_text($note),
    ]);
}

function fetch_bookmarks(int $userId): array
{
    $statement = db()->prepare(
        'SELECT bookmarks.*, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation, verses.verse_text
        FROM bookmarks
        INNER JOIN verses ON verses.id = bookmarks.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE bookmarks.user_id = :user_id
        ORDER BY bookmarks.created_at DESC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
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

function update_bookmark_record(int $bookmarkId, int $userId, string $tag, string $note): void
{
    $statement = db()->prepare(
        'UPDATE bookmarks SET tag = :tag, note = :note WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $bookmarkId,
        'user_id' => $userId,
        'tag' => normalize_optional_text($tag),
        'note' => normalize_optional_text($note),
    ]);
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

function format_event_date(?string $date): string
{
    if ($date === null || $date === '') {
        return 'TBD';
    }

    return date('M d', strtotime($date));
}

function truncate_text(string $text, int $length = 140): string
{
    $trimmed = trim($text);

    if (mb_strlen($trimmed) <= $length) {
        return $trimmed;
    }

    return rtrim(mb_substr($trimmed, 0, $length - 3)) . '...';
}

function count_records(string $sql, array $params = []): int
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

function normalize_optional_text(string $value): ?string
{
    $trimmed = trim($value);

    return $trimmed === '' ? null : $trimmed;
}

function normalize_book_key(string $value): string
{
    return preg_replace('/[^a-z0-9]/i', '', mb_strtolower(trim($value))) ?? '';
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

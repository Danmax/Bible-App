<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/system_repository.php';
require_once __DIR__ . '/scripture_search.php';

function fetch_recent_notes(int $userId, int $limit = 3): array
{
    $statement = db()->prepare(
        'SELECT study_notes.*, verses.book_id, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation
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
    return app_cache_remember('bible.books.v1.' . DB_NAME, 3600, static function (): array {
        return db()->query('SELECT id, name, abbreviation FROM books ORDER BY id ASC')->fetchAll();
    });
}

function fetch_book_catalog(string $translation): array
{
    if (uses_external_translation($translation)) {
        $translation = 'KJV';
    }

    return app_cache_remember('bible.book_catalog.v2.' . DB_NAME . '.' . strtoupper($translation), 1800, static function () use ($translation): array {
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
    });
}

function fetch_book_by_id(int $bookId): ?array
{
    return app_cache_remember('bible.book.v1.' . DB_NAME . '.' . $bookId, 3600, static function () use ($bookId): ?array {
        $statement = db()->prepare('SELECT id, name, abbreviation, testament FROM books WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $bookId]);
        $book = $statement->fetch();

        return $book ?: null;
    });
}

function fetch_book_chapters(int $bookId, string $translation): array
{
    if (uses_external_translation($translation)) {
        $translation = 'KJV';
    }

    return app_cache_remember('bible.book_chapters.v2.' . DB_NAME . '.' . strtoupper($translation) . '.' . $bookId, 1800, static function () use ($bookId, $translation): array {
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
    });
}

function fetch_chapter_verses(int $bookId, int $chapterNumber, string $translation): array
{
    if (uses_external_translation($translation)) {
        return fetch_external_translation_chapter_verses($bookId, $chapterNumber, $translation);
    }

    return app_cache_remember('bible.chapter_verses.v2.' . DB_NAME . '.' . strtoupper($translation) . '.' . $bookId . '.' . $chapterNumber, 1800, static function () use ($bookId, $chapterNumber, $translation): array {
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
    });
}

function supported_translations(): array
{
    return ['MSB', 'KJV', 'WEB', 'NIV', 'NKJV', 'NLT', 'RVR'];
}

function fetch_available_translations(): array
{
    return app_cache_remember('bible.available_translations.v2.' . DB_NAME, 1800, static function (): array {
        $storedTranslations = array_keys(fetch_stored_translation_counts());

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
    });
}

function fetch_stored_translation_counts(): array
{
    return app_cache_remember('bible.translation_counts.v2.' . DB_NAME, 1800, static function (): array {
        $statement = db()->query('SELECT translation, COUNT(*) AS verse_count FROM verses GROUP BY translation ORDER BY translation ASC');
        $counts = [];

        foreach ($statement->fetchAll() as $row) {
            $counts[(string) $row['translation']] = (int) $row['verse_count'];
        }

        return $counts;
    });
}

function fetch_translation_availability_map(array $translations): array
{
    $storedCounts = fetch_stored_translation_counts();
    $availability = [];

    foreach ($translations as $translation) {
        $translation = (string) $translation;
        $availability[$translation] = uses_external_translation($translation)
            ? external_translation_available($translation)
            : (($storedCounts[$translation] ?? 0) > 0);
    }

    return $availability;
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

function search_scripture(string $query, string $translation, string $sort = 'relevance', array $options = []): array
{
    $query = trim($query);
    if (mb_strlen($query) > 200) {
        throw new InvalidArgumentException('Keep your search to 200 characters or fewer.');
    }
    $books = fetch_books();
    $reference = parse_reference_query($query, $books);
    if ($reference !== null) {
        return fetch_reference_verses($reference, $translation);
    }
    if (uses_external_translation($translation)) {
        $search = search_external_translation($query, $translation);
        $search['provider_limited'] = true;
        return $search;
    }
    $search = fetch_keyword_verses($query, $translation, 20, $sort, $options);
    // Suggestions never replace the reader's words or run on each keystroke.
    if ($search['results'] === [] && strtoupper($translation) !== 'RVR') {
        $suggestion = scripture_search_correct_query($query, $translation);
        $search['suggested_query'] = $suggestion['corrections'] !== [] ? $suggestion['query'] : null;
    }
    return $search;
}

function scripture_search_synonym_map(): array
{
    return [
        'afraid' => ['fear', 'fearful', 'troubled', 'dismayed'],
        'anger' => ['wrath', 'angry', 'fury'],
        'anxious' => ['careful', 'worry', 'worrying', 'troubled'],
        'believe' => ['faith', 'trust', 'trusted', 'believed'],
        'comfort' => ['peace', 'rest', 'encourage', 'consolation'],
        'fear' => ['afraid', 'fearful', 'dismayed', 'troubled'],
        'forgive' => ['forgiven', 'forgiveness', 'pardon', 'mercy'],
        'grace' => ['favor', 'mercy', 'kindness'],
        'heal' => ['healed', 'healing', 'restore', 'restored'],
        'hope' => ['trust', 'wait', 'expectation'],
        'joy' => ['rejoice', 'glad', 'gladness'],
        'love' => ['charity', 'lovingkindness', 'kindness'],
        'mercy' => ['compassion', 'grace', 'pity', 'kindness'],
        'peace' => ['rest', 'comfort', 'quiet'],
        'pray' => ['prayer', 'supplication', 'petition', 'ask'],
        'redeem' => ['redeemed', 'redemption', 'save', 'saved'],
        'repent' => ['repentance', 'turn', 'converted'],
        'righteous' => ['righteousness', 'just', 'upright'],
        'save' => ['saved', 'salvation', 'deliver', 'delivered'],
        'sin' => ['sins', 'iniquity', 'transgression'],
        'strength' => ['strong', 'power', 'might'],
        'trust' => ['faith', 'believe', 'hope', 'confidence'],
        'wisdom' => ['wise', 'understanding', 'knowledge'],
    ];
}

function scripture_search_topic_map(): array
{
    return [
        'Anxiety & worry' => ['anxiety', 'anxious', 'worry', 'worried', 'care', 'troubled'],
        'Comfort' => ['comfort', 'comforted', 'consolation', 'encourage', 'rest'],
        'Faith & trust' => ['faith', 'believe', 'belief', 'trust', 'confidence'],
        'Forgiveness' => ['forgive', 'forgiven', 'forgiveness', 'pardon', 'mercy'],
        'Guidance & wisdom' => ['guidance', 'guide', 'wisdom', 'wise', 'understanding', 'counsel'],
        'Healing' => ['heal', 'healed', 'healing', 'restore', 'restored'],
        'Hope' => ['hope', 'hopeful', 'wait', 'expectation'],
        'Joy' => ['joy', 'rejoice', 'glad', 'gladness'],
        'Love' => ['love', 'loving', 'charity', 'kindness'],
        'Obedience' => ['obedience', 'obey', 'obeyed', 'command', 'commandments'],
        'Peace' => ['peace', 'quiet', 'rest', 'calm'],
        'Prayer' => ['pray', 'prayer', 'supplication', 'petition', 'ask'],
        'Salvation' => ['save', 'saved', 'salvation', 'redeem', 'redemption', 'deliver'],
        'Strength & courage' => ['strength', 'strong', 'power', 'might', 'courage', 'fear', 'afraid'],
    ];
}

function scripture_search_topics(array $terms): array
{
    $termSet = array_fill_keys(array_map('scripture_search_stem', $terms), true);
    $topics = [];

    foreach (scripture_search_topic_map() as $label => $topicTerms) {
        $matched = array_values(array_filter(
            $topicTerms,
            static fn(string $term): bool => isset($termSet[scripture_search_stem($term)])
        ));

        if ($matched !== []) {
            $topics[] = ['label' => $label, 'terms' => $topicTerms, 'matched_terms' => $matched];
        }
    }

    return $topics;
}

function scripture_search_stem(string $term): string
{
    $term = mb_strtolower(trim($term));
    $length = mb_strlen($term);

    foreach (['ing', 'ness', 'ment', 'tion', 'ed', 'es', 's'] as $suffix) {
        $suffixLength = mb_strlen($suffix);

        if ($length > $suffixLength + 3 && mb_substr($term, -$suffixLength) === $suffix) {
            return mb_substr($term, 0, $length - $suffixLength);
        }
    }

    return $term;
}

function scripture_search_terms(string $query): array
{
    $stopWords = scripture_analysis_stop_words();
    $terms = [];

    foreach (scripture_analysis_tokens($query) as $token) {
        if (mb_strlen($token) < 3 || isset($stopWords[$token])) {
            continue;
        }

        $terms[] = $token;
        $stem = scripture_search_stem($token);

        if ($stem !== $token && mb_strlen($stem) >= 3) {
            $terms[] = $stem;
        }
    }

    return array_values(array_unique($terms));
}

function scripture_search_vocabulary(string $translation): array
{
    if (uses_external_translation($translation)) {
        $translation = 'KJV';
    }

    return app_cache_remember('bible.search_vocabulary.v1.' . DB_NAME . '.' . strtoupper($translation), 86400, static function () use ($translation): array {
        $statement = db()->prepare('SELECT verse_text FROM verses WHERE translation = :translation');
        $statement->execute(['translation' => $translation]);
        $vocabulary = [];

        while ($verse = $statement->fetch()) {
            foreach (scripture_analysis_tokens((string) ($verse['verse_text'] ?? '')) as $word) {
                if (mb_strlen($word) >= 3) {
                    $vocabulary[$word] = ($vocabulary[$word] ?? 0) + 1;
                }
            }
        }

        return $vocabulary;
    });
}

function scripture_search_correct_query(string $query, string $translation): array
{
    $vocabulary = scripture_search_vocabulary($translation);

    if ($vocabulary === []) {
        return ['query' => $query, 'corrections' => []];
    }

    $corrections = [];

    foreach (array_unique(scripture_analysis_tokens($query)) as $term) {
        if (mb_strlen($term) < 4 || isset($vocabulary[$term]) || !preg_match('/^[a-z]+$/', $term)) {
            continue;
        }

        $maximumDistance = mb_strlen($term) <= 6 ? 1 : (mb_strlen($term) <= 10 ? 2 : 3);
        $bestWord = null;
        $bestDistance = $maximumDistance + 1;
        $bestFrequency = -1;

        foreach ($vocabulary as $word => $frequency) {
            if (abs(mb_strlen($word) - mb_strlen($term)) > $maximumDistance) {
                continue;
            }

            $distance = levenshtein($term, $word);
            if ($distance > $maximumDistance) {
                continue;
            }

            if ($distance < $bestDistance || ($distance === $bestDistance && $frequency > $bestFrequency)) {
                $bestWord = $word;
                $bestDistance = $distance;
                $bestFrequency = $frequency;
            }
        }

        if ($bestWord !== null) {
            $corrections[$term] = $bestWord;
        }
    }

    if ($corrections === []) {
        return ['query' => $query, 'corrections' => []];
    }

    $correctedQuery = preg_replace_callback(
        "/[\\p{L}][\\p{L}'-]*/u",
        static function (array $match) use ($corrections): string {
            $original = (string) $match[0];
            $replacement = $corrections[mb_strtolower($original)] ?? null;

            return $replacement === null ? $original : $replacement;
        },
        $query
    );

    return ['query' => $correctedQuery ?? $query, 'corrections' => $corrections];
}

function scripture_search_expanded_terms(array $terms): array
{
    $synonyms = scripture_search_synonym_map();
    $expanded = $terms;

    foreach ($terms as $term) {
        $stem = scripture_search_stem($term);

        foreach ([$term, $stem] as $lookup) {
            foreach (($synonyms[$lookup] ?? []) as $relatedTerm) {
                $expanded[] = $relatedTerm;
                $expanded[] = scripture_search_stem($relatedTerm);
            }
        }
    }

    // Topic families are intentionally bidirectional: searching "worry" can
    // surface verses using "peace", and searching "quiet" can find "peace".
    foreach (scripture_search_topics($terms) as $topic) {
        foreach ($topic['terms'] as $relatedTerm) {
            $expanded[] = $relatedTerm;
            $expanded[] = scripture_search_stem($relatedTerm);
        }
    }

    return array_values(array_unique(array_filter(
        $expanded,
        static fn(string $term): bool => mb_strlen($term) >= 3
    )));
}

function scripture_search_score(array $verse, string $normalizedQuery, array $queryTerms, array $expandedTerms): int
{
    $literal = scripture_search_match($verse, $normalizedQuery, 'KJV', 'any');
    if ($literal !== null) {
        return $literal['score'];
    }
    return array_filter($expandedTerms, static fn(string $term): bool => scripture_search_contains((string) $verse['verse_text'], $term)) ? 1 : 0;
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
    $endChapter = (int) ($reference['end_chapter'] ?? $reference['chapter']);
    if ($endChapter === (int) $reference['chapter']) {
        if (uses_external_translation($translation)) {
            return fetch_external_translation_reference_verses($reference, $translation);
        }
        $verses = fetch_chapter_verses((int) $reference['book_id'], (int) $reference['chapter'], $translation);
    } else {
        $verses = [];
        // Cross-chapter requests use the same translation adapter as the reader.
        if ($endChapter - (int) $reference['chapter'] > 10) {
            throw new InvalidArgumentException('Open up to 11 chapters at a time.');
        }
        for ($chapter = (int) $reference['chapter']; $chapter <= $endChapter; $chapter++) {
            $verses = array_merge($verses, fetch_chapter_verses((int) $reference['book_id'], $chapter, $translation));
        }
    }
    $start = $reference['start_verse'];
    $end = $reference['end_verse'] ?? $start;
    if ($verses !== [] && $end !== null) {
        $last = end($verses);
        if ((int) $last['chapter_number'] === $endChapter && $end > (int) $last['verse_number']) {
            throw new InvalidArgumentException('The ending verse is not available in this chapter and translation.');
        }
    }
    $verses = array_values(array_filter($verses, static function (array $verse) use ($reference, $endChapter, $start, $end): bool {
        $chapter = (int) $verse['chapter_number'];
        $number = (int) $verse['verse_number'];
        return ($start === null || $chapter > (int) $reference['chapter'] || $number >= $start)
            && ($end === null || $chapter < $endChapter || $number <= $end);
    }));
    return ['mode' => 'reference', 'results' => $verses, 'heading' => build_reference_heading($reference, $translation)];
}

/** Complete translation-scoped retrieval, with compact cached rankings and stable paging. */
function fetch_keyword_verses(string $query, string $translation, int $limit = 20, string $sort = 'relevance', array $options = [], ?PDO $connection = null): array
{
    $options = scripture_search_options($options);
    $sort = $sort === 'canonical' ? 'canonical' : 'relevance';
    $limit = max(1, min(100, $limit));
    $pdo = $connection ?? db();
    $tokens = array_values(array_unique(array_filter(preg_split("/[\s']+/u", scripture_search_text($query)) ?: [])));
    if (count($tokens) > 24) {
        throw new InvalidArgumentException('Try a shorter phrase with up to 24 words.');
    }
    if ($options['match'] === 'related' && strtoupper($translation) !== 'RVR') {
        $tokens = array_values(array_unique(array_merge($tokens, scripture_search_expanded_terms(scripture_search_terms($query)))));
    }
    $rankOptions = $options;
    unset($rankOptions['page']);
    $cacheKey = 'bible.search.v2.' . DB_NAME . '.' . hash('sha256', json_encode([$query, $translation, $sort, $rankOptions]));
    $buildRanking = static function () use ($pdo, $tokens, $query, $translation, $sort, $options): array {
        if (trim($query) === '' || $tokens === []) {
            return [];
        }
        $where = ['verses.translation = :translation'];
        $params = ['translation' => $translation];
        $candidates = [];
        foreach ($tokens as $index => $token) {
            $candidates[] = "verses.verse_text LIKE :term_" . $index . " ESCAPE '!'";
            $params['term_' . $index] = scripture_search_like($token);
        }
        $where[] = '(' . implode(' OR ', $candidates) . ')';
        if ($options['book_id'] > 0) {
            $where[] = 'verses.book_id = :book_id';
            $params['book_id'] = $options['book_id'];
        }
        if ($options['scope'] !== 'all') {
            $where[] = 'books.testament = :testament';
            $params['testament'] = $options['scope'] === 'old' ? 'Old Testament' : 'New Testament';
        }
        $statement = $pdo->prepare('SELECT verses.id, verses.book_id, verses.chapter_number, verses.verse_number, verses.verse_text
            FROM verses INNER JOIN books ON books.id = verses.book_id WHERE ' . implode(' AND ', $where));
        $statement->execute($params);
        $ranked = [];
        while ($verse = $statement->fetch()) {
            $match = scripture_search_match($verse, $query, $translation, $options['match']);
            if ($match !== null) {
                $ranked[] = ['id' => (int) $verse['id'], 'score' => $match['score'], 'reason' => $match['reason'],
                    'location' => [(int) $verse['book_id'], (int) $verse['chapter_number'], (int) $verse['verse_number']]];
            }
        }
        usort($ranked, static function (array $a, array $b) use ($sort): int {
            return ($sort === 'canonical' ? 0 : $b['score'] <=> $a['score']) ?: ($a['location'] <=> $b['location']);
        });
        return $ranked;
    };
    // An injected connection is used by isolated database regression fixtures.
    $ranked = $connection !== null ? $buildRanking() : app_cache_remember($cacheKey, 120, $buildRanking);
    $total = count($ranked);
    $pageCount = max(1, (int) ceil($total / $limit));
    $page = min($options['page'], $pageCount);
    $slice = array_slice($ranked, ($page - 1) * $limit, $limit);
    $results = [];
    if ($slice !== []) {
        $ids = array_column($slice, 'id');
        $statement = $pdo->prepare('SELECT verses.*, books.name AS book_name, books.abbreviation FROM verses INNER JOIN books ON books.id = verses.book_id WHERE verses.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $statement->execute($ids);
        $byId = [];
        foreach ($statement->fetchAll() as $verse) {
            $byId[(int) $verse['id']] = $verse;
        }
        foreach ($slice as $rank) {
            if (isset($byId[$rank['id']])) {
                $results[] = $byId[$rank['id']] + ['match_reason' => $rank['reason']];
            }
        }
    }
    return ['mode' => 'keyword', 'results' => $results, 'heading' => 'Search results', 'total' => $total,
        'page' => $page, 'page_count' => $pageCount, 'has_more' => $page < $pageCount, 'sort' => $sort];
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
    return app_cache_remember('bible.thematic_series.v2.' . DB_NAME . '.' . strtoupper($translation), 1800, static function () use ($translation): array {
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
    });
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
                SET tag = :tag, note = :note, highlight_color = :highlight_color
                WHERE id = :id AND user_id = :user_id'
            );
            $statement->execute([
                'id' => $existing['id'],
                'user_id' => $userId,
                'tag' => $normalizedTag,
                'note' => $normalizedNote,
                'highlight_color' => $normalizedColor ?: null,
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

function fetch_notes_for_verses(int $userId, array $verseIds, int $limit = 6): array
{
    $verseIds = array_values(array_unique(array_filter(
        array_map(static fn($verseId): int => (int) $verseId, $verseIds),
        static fn(int $verseId): bool => $verseId > 0
    )));

    if ($verseIds === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($verseIds), '?'));
    $statement = db()->prepare(
        'SELECT study_notes.*, verses.book_id, books.name AS book_name, verses.chapter_number, verses.verse_number, verses.translation
        FROM study_notes
        INNER JOIN verses ON verses.id = study_notes.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE study_notes.user_id = ? AND study_notes.verse_id IN (' . $placeholders . ')
        ORDER BY study_notes.updated_at DESC
        LIMIT ' . max(1, (int) $limit)
    );
    $statement->execute([$userId, ...$verseIds]);

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
    if ((int) ($reference['end_chapter'] ?? $reference['chapter']) !== (int) $reference['chapter']) {
        return $heading . ':' . $reference['start_verse'] . '–' . $reference['end_chapter'] . ':' . $reference['end_verse'] . ' (' . $translation . ')';
    }

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

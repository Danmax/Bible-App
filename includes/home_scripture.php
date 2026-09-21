<?php

declare(strict_types=1);

function home_daily_verse_moment(): string
{
    return (int) home_daily_now()->format('G') >= 18 ? 'evening' : 'day';
}

function home_daily_verse_index(): int
{
    return (int) home_daily_now()->format('z') % 365;
}

function home_daily_now(): DateTimeImmutable
{
    static $now = null;

    if ($now instanceof DateTimeImmutable) {
        return $now;
    }

    $configuredTimezone = trim((string) (getenv('APP_TIMEZONE') ?: 'America/New_York'));

    try {
        $timezone = new DateTimeZone($configuredTimezone);
    } catch (Throwable $exception) {
        $timezone = new DateTimeZone('America/New_York');
    }

    $now = new DateTimeImmutable('now', $timezone);

    return $now;
}

function home_daily_verse_payload(string $translation, int $seed): array
{
    $isEvening = home_daily_verse_moment() === 'evening';
    $fallbacks = home_daily_emergency_verses($isEvening);
    $selected = $fallbacks[$seed % count($fallbacks)];
    $payload = $selected + ['translation' => $translation];

    try {
        $dailyVerse = home_fetch_daily_verse_from_library($translation, $seed);

        if ($dailyVerse !== null) {
            return $dailyVerse;
        }

        $books = fetch_books();
        $reference = parse_reference_query((string) $selected['query'], $books);

        if ($reference !== null) {
            $results = fetch_reference_verses($reference, $translation);
            $verses = (array) ($results['results'] ?? []);

            if ($verses !== []) {
                $text = trim(implode(' ', array_map(
                    static fn(array $verse): string => trim((string) ($verse['verse_text'] ?? '')),
                    $verses
                )));

                if ($text !== '') {
                    $payload['text'] = truncate_text($text, 220);
                    $payload['reference'] = (string) ($results['heading'] ?? $selected['reference']);
                    $payload['translation'] = (string) (($verses[0]['translation'] ?? $translation));
                }
            }
        }
    } catch (Throwable $exception) {
        // Keep curated fallback content for the home page when the database is unavailable.
    }

    return $payload;
}

function home_fetch_daily_verse_from_library(string $translation, int $dayIndex): ?array
{
    $verses = app_cache_remember('home.daily_verse_pool.v2.' . DB_NAME . '.' . strtoupper($translation), 3600, static function () use ($translation): array {
        $statement = db()->prepare(
            'SELECT verses.*, books.name AS book_name, books.abbreviation
            FROM verses
            INNER JOIN books ON books.id = verses.book_id
            WHERE verses.translation = :translation
                AND CHAR_LENGTH(verses.verse_text) BETWEEN 45 AND 220
                AND verses.book_id IN (
                    19, 20, 23, 24, 25, 33,
                    40, 41, 42, 43, 44, 45, 46, 47,
                    48, 49, 50, 51, 52, 53, 54, 55,
                    56, 58, 59, 60, 61, 62, 65
                )
            ORDER BY MOD(
                ((verses.book_id * 1000000) + (verses.chapter_number * 1000) + verses.verse_number) * 1103515245 + 12345,
                2147483647
            ) ASC
            LIMIT 365'
        );
        $statement->execute(['translation' => $translation]);

        return $statement->fetchAll();
    });

    if ($verses === []) {
        return null;
    }

    $verse = $verses[$dayIndex % count($verses)];
    $bookName = (string) ($verse['book_name'] ?? 'Scripture');
    $chapter = (int) ($verse['chapter_number'] ?? 0);
    $verseNumber = (int) ($verse['verse_number'] ?? 0);
    $reference = sprintf('%s %d:%d', $bookName, $chapter, $verseNumber);

    return [
        'query' => $reference,
        'reference' => $reference,
        'text' => truncate_text(trim((string) ($verse['verse_text'] ?? '')), 220),
        'kicker' => home_daily_verse_kicker(),
        'message' => home_daily_verse_message($dayIndex),
        'translation' => (string) ($verse['translation'] ?? $translation),
    ];
}

function home_daily_verse_kicker(): string
{
    return home_daily_verse_moment() === 'evening' ? 'For tonight' : 'For today';
}

function home_daily_verse_message(int $dayIndex): string
{
    $messages = [
        'Let Scripture steady the next step in front of you.',
        'Carry this promise into the ordinary parts of the day.',
        'Let this word shape your attention before the noise does.',
        'Return to this truth when the day asks for patience.',
        'Keep this verse close as a prayer and a practice.',
        'Let God\'s Word give language to faith, hope, and obedience.',
        'Receive this passage slowly and answer it with trust.',
        'Let this truth interrupt worry and call you back to peace.',
        'Build today around what God has already spoken.',
        'Hold this verse with humility, courage, and expectation.',
        'Let the Word of God renew your mind and direct your path.',
        'Make room for this promise to become obedience today.',
    ];

    return $messages[$dayIndex % count($messages)];
}

function home_daily_emergency_verses(bool $isEvening): array
{
    return [
        [
            'query' => 'Proverbs 3:5-6',
            'reference' => 'Proverbs 3:5-6',
            'text' => 'Trust in the Lord with all your heart, and do not lean on your own understanding.',
            'kicker' => $isEvening ? 'For tonight' : 'For today',
            'message' => 'Let the day start from surrender instead of strain.',
        ],
        [
            'query' => 'Isaiah 40:31',
            'reference' => 'Isaiah 40:31',
            'text' => 'Those who hope in the Lord will renew their strength.',
            'kicker' => $isEvening ? 'Evening strength' : 'For today',
            'message' => 'Wait with expectancy and keep moving in quiet confidence.',
        ],
        [
            'query' => 'Philippians 4:6-7',
            'reference' => 'Philippians 4:6-7',
            'text' => 'Do not be anxious about anything, but in everything by prayer and petition present your requests to God.',
            'kicker' => $isEvening ? 'Evening peace' : 'For today',
            'message' => 'Turn pressure into prayer and let peace guard the mind.',
        ],
        [
            'query' => 'Romans 15:13',
            'reference' => 'Romans 15:13',
            'text' => 'May the God of hope fill you with all joy and peace as you trust in Him.',
            'kicker' => $isEvening ? 'Evening hope' : 'For today',
            'message' => 'Hope grows where trust stays rooted in God.',
        ],
        [
            'query' => 'Joshua 1:9',
            'reference' => 'Joshua 1:9',
            'text' => 'Be strong and courageous. Do not be afraid; do not be discouraged, for the Lord your God will be with you.',
            'kicker' => $isEvening ? 'For the evening' : 'For today',
            'message' => 'Walk forward with courage that comes from God\'s presence.',
        ],
        [
            'query' => 'Lamentations 3:22-23',
            'reference' => 'Lamentations 3:22-23',
            'text' => 'Because of the Lord\'s faithful love we do not perish, for His mercies never end. They are new every morning.',
            'kicker' => $isEvening ? 'Before rest' : 'For today',
            'message' => 'Start again with mercy that has already met the morning.',
        ],
    ];
}

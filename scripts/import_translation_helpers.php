<?php

declare(strict_types=1);

function canonical_book_definitions(): array
{
    return [
        1 => ['name' => 'Genesis', 'abbreviation' => 'Gen', 'testament' => 'Old Testament', 'aliases' => ['genesis']],
        2 => ['name' => 'Exodus', 'abbreviation' => 'Exo', 'testament' => 'Old Testament', 'aliases' => ['exodus']],
        3 => ['name' => 'Leviticus', 'abbreviation' => 'Lev', 'testament' => 'Old Testament', 'aliases' => ['leviticus']],
        4 => ['name' => 'Numbers', 'abbreviation' => 'Num', 'testament' => 'Old Testament', 'aliases' => ['numbers']],
        5 => ['name' => 'Deuteronomy', 'abbreviation' => 'Deut', 'testament' => 'Old Testament', 'aliases' => ['deuteronomy']],
        6 => ['name' => 'Joshua', 'abbreviation' => 'Josh', 'testament' => 'Old Testament', 'aliases' => ['joshua']],
        7 => ['name' => 'Judges', 'abbreviation' => 'Judg', 'testament' => 'Old Testament', 'aliases' => ['judges']],
        8 => ['name' => 'Ruth', 'abbreviation' => 'Ruth', 'testament' => 'Old Testament', 'aliases' => ['ruth']],
        9 => ['name' => '1 Samuel', 'abbreviation' => '1 Sam', 'testament' => 'Old Testament', 'aliases' => ['1samuel']],
        10 => ['name' => '2 Samuel', 'abbreviation' => '2 Sam', 'testament' => 'Old Testament', 'aliases' => ['2samuel']],
        11 => ['name' => '1 Kings', 'abbreviation' => '1 Kgs', 'testament' => 'Old Testament', 'aliases' => ['1kings']],
        12 => ['name' => '2 Kings', 'abbreviation' => '2 Kgs', 'testament' => 'Old Testament', 'aliases' => ['2kings']],
        13 => ['name' => '1 Chronicles', 'abbreviation' => '1 Chr', 'testament' => 'Old Testament', 'aliases' => ['1chronicles']],
        14 => ['name' => '2 Chronicles', 'abbreviation' => '2 Chr', 'testament' => 'Old Testament', 'aliases' => ['2chronicles']],
        15 => ['name' => 'Ezra', 'abbreviation' => 'Ezra', 'testament' => 'Old Testament', 'aliases' => ['ezra']],
        16 => ['name' => 'Nehemiah', 'abbreviation' => 'Neh', 'testament' => 'Old Testament', 'aliases' => ['nehemiah']],
        17 => ['name' => 'Esther', 'abbreviation' => 'Est', 'testament' => 'Old Testament', 'aliases' => ['esther']],
        18 => ['name' => 'Job', 'abbreviation' => 'Job', 'testament' => 'Old Testament', 'aliases' => ['job']],
        19 => ['name' => 'Psalms', 'abbreviation' => 'Ps', 'testament' => 'Old Testament', 'aliases' => ['psalm', 'psalms']],
        20 => ['name' => 'Proverbs', 'abbreviation' => 'Prov', 'testament' => 'Old Testament', 'aliases' => ['proverbs']],
        21 => ['name' => 'Ecclesiastes', 'abbreviation' => 'Eccl', 'testament' => 'Old Testament', 'aliases' => ['ecclesiastes']],
        22 => ['name' => 'Song of Solomon', 'abbreviation' => 'Song', 'testament' => 'Old Testament', 'aliases' => ['songofsolomon', 'songofsongs', 'solomonssong']],
        23 => ['name' => 'Isaiah', 'abbreviation' => 'Isa', 'testament' => 'Old Testament', 'aliases' => ['isaiah']],
        24 => ['name' => 'Jeremiah', 'abbreviation' => 'Jer', 'testament' => 'Old Testament', 'aliases' => ['jeremiah']],
        25 => ['name' => 'Lamentations', 'abbreviation' => 'Lam', 'testament' => 'Old Testament', 'aliases' => ['lamentations']],
        26 => ['name' => 'Ezekiel', 'abbreviation' => 'Ezek', 'testament' => 'Old Testament', 'aliases' => ['ezekiel']],
        27 => ['name' => 'Daniel', 'abbreviation' => 'Dan', 'testament' => 'Old Testament', 'aliases' => ['daniel']],
        28 => ['name' => 'Hosea', 'abbreviation' => 'Hos', 'testament' => 'Old Testament', 'aliases' => ['hosea']],
        29 => ['name' => 'Joel', 'abbreviation' => 'Joel', 'testament' => 'Old Testament', 'aliases' => ['joel']],
        30 => ['name' => 'Amos', 'abbreviation' => 'Amos', 'testament' => 'Old Testament', 'aliases' => ['amos']],
        31 => ['name' => 'Obadiah', 'abbreviation' => 'Obad', 'testament' => 'Old Testament', 'aliases' => ['obadiah']],
        32 => ['name' => 'Jonah', 'abbreviation' => 'Jonah', 'testament' => 'Old Testament', 'aliases' => ['jonah']],
        33 => ['name' => 'Micah', 'abbreviation' => 'Mic', 'testament' => 'Old Testament', 'aliases' => ['micah']],
        34 => ['name' => 'Nahum', 'abbreviation' => 'Nah', 'testament' => 'Old Testament', 'aliases' => ['nahum']],
        35 => ['name' => 'Habakkuk', 'abbreviation' => 'Hab', 'testament' => 'Old Testament', 'aliases' => ['habakkuk']],
        36 => ['name' => 'Zephaniah', 'abbreviation' => 'Zeph', 'testament' => 'Old Testament', 'aliases' => ['zephaniah']],
        37 => ['name' => 'Haggai', 'abbreviation' => 'Hag', 'testament' => 'Old Testament', 'aliases' => ['haggai']],
        38 => ['name' => 'Zechariah', 'abbreviation' => 'Zech', 'testament' => 'Old Testament', 'aliases' => ['zechariah']],
        39 => ['name' => 'Malachi', 'abbreviation' => 'Mal', 'testament' => 'Old Testament', 'aliases' => ['malachi']],
        40 => ['name' => 'Matthew', 'abbreviation' => 'Matt', 'testament' => 'New Testament', 'aliases' => ['matthew']],
        41 => ['name' => 'Mark', 'abbreviation' => 'Mark', 'testament' => 'New Testament', 'aliases' => ['mark']],
        42 => ['name' => 'Luke', 'abbreviation' => 'Luke', 'testament' => 'New Testament', 'aliases' => ['luke']],
        43 => ['name' => 'John', 'abbreviation' => 'John', 'testament' => 'New Testament', 'aliases' => ['john']],
        44 => ['name' => 'Acts', 'abbreviation' => 'Acts', 'testament' => 'New Testament', 'aliases' => ['acts']],
        45 => ['name' => 'Romans', 'abbreviation' => 'Rom', 'testament' => 'New Testament', 'aliases' => ['romans']],
        46 => ['name' => '1 Corinthians', 'abbreviation' => '1 Cor', 'testament' => 'New Testament', 'aliases' => ['1corinthians']],
        47 => ['name' => '2 Corinthians', 'abbreviation' => '2 Cor', 'testament' => 'New Testament', 'aliases' => ['2corinthians']],
        48 => ['name' => 'Galatians', 'abbreviation' => 'Gal', 'testament' => 'New Testament', 'aliases' => ['galatians']],
        49 => ['name' => 'Ephesians', 'abbreviation' => 'Eph', 'testament' => 'New Testament', 'aliases' => ['ephesians']],
        50 => ['name' => 'Philippians', 'abbreviation' => 'Phil', 'testament' => 'New Testament', 'aliases' => ['philippians']],
        51 => ['name' => 'Colossians', 'abbreviation' => 'Col', 'testament' => 'New Testament', 'aliases' => ['colossians']],
        52 => ['name' => '1 Thessalonians', 'abbreviation' => '1 Thess', 'testament' => 'New Testament', 'aliases' => ['1thessalonians']],
        53 => ['name' => '2 Thessalonians', 'abbreviation' => '2 Thess', 'testament' => 'New Testament', 'aliases' => ['2thessalonians']],
        54 => ['name' => '1 Timothy', 'abbreviation' => '1 Tim', 'testament' => 'New Testament', 'aliases' => ['1timothy']],
        55 => ['name' => '2 Timothy', 'abbreviation' => '2 Tim', 'testament' => 'New Testament', 'aliases' => ['2timothy']],
        56 => ['name' => 'Titus', 'abbreviation' => 'Titus', 'testament' => 'New Testament', 'aliases' => ['titus']],
        57 => ['name' => 'Philemon', 'abbreviation' => 'Phlm', 'testament' => 'New Testament', 'aliases' => ['philemon']],
        58 => ['name' => 'Hebrews', 'abbreviation' => 'Heb', 'testament' => 'New Testament', 'aliases' => ['hebrews']],
        59 => ['name' => 'James', 'abbreviation' => 'Jas', 'testament' => 'New Testament', 'aliases' => ['james']],
        60 => ['name' => '1 Peter', 'abbreviation' => '1 Pet', 'testament' => 'New Testament', 'aliases' => ['1peter']],
        61 => ['name' => '2 Peter', 'abbreviation' => '2 Pet', 'testament' => 'New Testament', 'aliases' => ['2peter']],
        62 => ['name' => '1 John', 'abbreviation' => '1 John', 'testament' => 'New Testament', 'aliases' => ['1john']],
        63 => ['name' => '2 John', 'abbreviation' => '2 John', 'testament' => 'New Testament', 'aliases' => ['2john']],
        64 => ['name' => '3 John', 'abbreviation' => '3 John', 'testament' => 'New Testament', 'aliases' => ['3john']],
        65 => ['name' => 'Jude', 'abbreviation' => 'Jude', 'testament' => 'New Testament', 'aliases' => ['jude']],
        66 => ['name' => 'Revelation', 'abbreviation' => 'Rev', 'testament' => 'New Testament', 'aliases' => ['revelation']],
    ];
}

function canonical_book_alias_map(array $bookDefinitions): array
{
    $aliasMap = [];

    foreach ($bookDefinitions as $bookId => $bookDefinition) {
        foreach ($bookDefinition['aliases'] as $alias) {
            $aliasMap[$alias] = $bookId;
        }

        $aliasMap[normalize_book_alias((string) $bookDefinition['name'])] = $bookId;
        $aliasMap[normalize_book_alias((string) $bookDefinition['abbreviation'])] = $bookId;
    }

    foreach (canonical_book_code_aliases() as $alias => $bookId) {
        $aliasMap[normalize_book_alias($alias)] = $bookId;
    }

    return $aliasMap;
}

function canonical_book_code_aliases(): array
{
    return [
        'GEN' => 1,
        'EXO' => 2,
        'LEV' => 3,
        'NUM' => 4,
        'DEU' => 5,
        'JOS' => 6,
        'JDG' => 7,
        'RUT' => 8,
        '1SA' => 9,
        '2SA' => 10,
        '1KI' => 11,
        '2KI' => 12,
        '1CH' => 13,
        '2CH' => 14,
        'EZR' => 15,
        'NEH' => 16,
        'EST' => 17,
        'JOB' => 18,
        'PSA' => 19,
        'PRO' => 20,
        'ECC' => 21,
        'SNG' => 22,
        'SOL' => 22,
        'ISA' => 23,
        'JER' => 24,
        'LAM' => 25,
        'EZK' => 26,
        'EZE' => 26,
        'DAN' => 27,
        'HOS' => 28,
        'JOL' => 29,
        'JOE' => 29,
        'AMO' => 30,
        'OBA' => 31,
        'JON' => 32,
        'MIC' => 33,
        'NAM' => 34,
        'HAB' => 35,
        'ZEP' => 36,
        'HAG' => 37,
        'ZEC' => 38,
        'MAL' => 39,
        'MAT' => 40,
        'MRK' => 41,
        'MAR' => 41,
        'LUK' => 42,
        'JHN' => 43,
        'JOH' => 43,
        'ACT' => 44,
        'ROM' => 45,
        '1CO' => 46,
        '2CO' => 47,
        'GAL' => 48,
        'EPH' => 49,
        'PHP' => 50,
        'PHI' => 50,
        'COL' => 51,
        '1TH' => 52,
        '2TH' => 53,
        '1TI' => 54,
        '2TI' => 55,
        'TIT' => 56,
        'PHM' => 57,
        'HEB' => 58,
        'JAS' => 59,
        'JAM' => 59,
        '1PE' => 60,
        '2PE' => 61,
        '1JN' => 62,
        '1JO' => 62,
        '2JN' => 63,
        '2JO' => 63,
        '3JN' => 64,
        '3JO' => 64,
        'JUD' => 65,
        'REV' => 66,
    ];
}

function normalize_book_alias(string $value): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower(trim($value))) ?? '';
}

function clean_verse_text(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
}

function insert_verse_batches(PDO $pdo, array $rows, int $batchSize): void
{
    $columns = ['book_id', 'chapter_number', 'verse_number', 'verse_text', 'translation'];

    foreach (array_chunk($rows, $batchSize) as $batch) {
        $placeholders = [];
        $params = [];

        foreach ($batch as $row) {
            $placeholders[] = '(?, ?, ?, ?, ?)';

            foreach ($columns as $column) {
                $params[] = $row[$column];
            }
        }

        $sql = 'INSERT INTO verses (book_id, chapter_number, verse_number, verse_text, translation) VALUES '
            . implode(', ', $placeholders);

        $statement = $pdo->prepare($sql);
        $statement->execute($params);
    }
}

function upsert_books(PDO $pdo, array $bookDefinitions): void
{
    $bookInsert = $pdo->prepare(
        'INSERT INTO books (id, name, abbreviation, testament)
        VALUES (:id, :name, :abbreviation, :testament)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            abbreviation = VALUES(abbreviation),
            testament = VALUES(testament)'
    );

    foreach ($bookDefinitions as $bookId => $bookDefinition) {
        $bookInsert->execute([
            'id' => $bookId,
            'name' => $bookDefinition['name'],
            'abbreviation' => $bookDefinition['abbreviation'],
            'testament' => $bookDefinition['testament'],
        ]);
    }
}

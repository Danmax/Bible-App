<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/import_kjv.php /path/to/verses-1769.json\n");
    exit(1);
}

$jsonPath = $argv[1];

if (!is_file($jsonPath)) {
    fwrite(STDERR, "File not found: {$jsonPath}\n");
    exit(1);
}

$json = file_get_contents($jsonPath);

if ($json === false) {
    fwrite(STDERR, "Unable to read file: {$jsonPath}\n");
    exit(1);
}

$verses = json_decode($json, true);

if (!is_array($verses)) {
    fwrite(STDERR, "Invalid JSON structure in {$jsonPath}\n");
    exit(1);
}

$bookDefinitions = [
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

$bookAliasMap = [];

foreach ($bookDefinitions as $bookId => $bookDefinition) {
    foreach ($bookDefinition['aliases'] as $alias) {
        $bookAliasMap[$alias] = $bookId;
    }
}

$pdo = db();
$pdo->beginTransaction();

try {
    $pdo->exec('DELETE FROM verses WHERE translation = "KJV"');

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

    $verseRows = [];
    $count = 0;

    foreach ($verses as $reference => $verseText) {
        if (!preg_match('/^(.+?)\s+(\d+):(\d+)$/', (string) $reference, $matches)) {
            continue;
        }

        $bookKey = normalize_book_alias($matches[1]);

        if (!isset($bookAliasMap[$bookKey])) {
            throw new RuntimeException('Unknown book name in source data: ' . $matches[1]);
        }

        $verseRows[] = [
            'book_id' => $bookAliasMap[$bookKey],
            'chapter_number' => (int) $matches[2],
            'verse_number' => (int) $matches[3],
            'verse_text' => clean_verse_text((string) $verseText),
            'translation' => 'KJV',
        ];

        $count++;
    }

    insert_verse_batches($pdo, $verseRows, 500);

    $pdo->commit();
    fwrite(STDOUT, "Imported {$count} KJV verses.\n");
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

function normalize_book_alias(string $value): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower(trim($value))) ?? '';
}

function clean_verse_text(string $text): string
{
    $normalized = preg_replace('/\s+/', ' ', trim($text)) ?? '';
    $normalized = ltrim($normalized, '# ');

    return trim($normalized);
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

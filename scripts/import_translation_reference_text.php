<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once __DIR__ . '/import_translation_helpers.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/import_translation_reference_text.php TRANSLATION /path/to/source.txt\n");
    exit(1);
}

$translation = strtoupper(trim((string) $argv[1]));
$sourcePath = (string) $argv[2];

if ($translation === '') {
    fwrite(STDERR, "Translation code is required.\n");
    exit(1);
}

if (!is_file($sourcePath)) {
    fwrite(STDERR, "File not found: {$sourcePath}\n");
    exit(1);
}

$contents = file_get_contents($sourcePath);

if ($contents === false) {
    fwrite(STDERR, "Unable to read file: {$sourcePath}\n");
    exit(1);
}

$contents = mb_convert_encoding($contents, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
$bookDefinitions = canonical_book_definitions();
$bookAliasMap = canonical_book_alias_map($bookDefinitions);
$lines = preg_split('/\R/', $contents) ?: [];
$verseRows = [];
$importedCount = 0;
$skippedCount = 0;

foreach ($lines as $line) {
    $parsedVerse = parse_reference_text_line($line);

    if ($parsedVerse === null) {
        $skippedCount++;
        continue;
    }

    $bookKey = normalize_book_alias($parsedVerse['book']);

    if (!isset($bookAliasMap[$bookKey])) {
        $skippedCount++;
        continue;
    }

    $verseText = clean_verse_text($parsedVerse['verse_text']);

    if ($verseText === '') {
        $skippedCount++;
        continue;
    }

    $verseRows[] = [
        'book_id' => $bookAliasMap[$bookKey],
        'chapter_number' => (int) $parsedVerse['chapter_number'],
        'verse_number' => (int) $parsedVerse['verse_number'],
        'verse_text' => $verseText,
        'translation' => $translation,
    ];
    $importedCount++;
}

if ($verseRows === []) {
    fwrite(STDERR, "No verse rows could be parsed from {$sourcePath}\n");
    exit(1);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $pdo->prepare('DELETE FROM verses WHERE translation = :translation')
        ->execute(['translation' => $translation]);

    upsert_books($pdo, $bookDefinitions);
    insert_verse_batches($pdo, $verseRows, 500);

    $pdo->commit();
    fwrite(STDOUT, "Imported {$importedCount} {$translation} verses.\n");
    fwrite(STDOUT, "Skipped {$skippedCount} non-verse or unsupported lines.\n");
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

function parse_reference_text_line(string $line): ?array
{
    $trimmedLine = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? '');

    if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
        return null;
    }

    if (!str_contains($trimmedLine, "\t")) {
        return null;
    }

    [$reference, $verseText] = array_pad(explode("\t", $trimmedLine, 2), 2, '');
    $reference = trim($reference);
    $verseText = trim($verseText);

    if ($reference === '' || $verseText === '' || strtolower($reference) === 'verse') {
        return null;
    }

    if (!preg_match('/^(.+?)\s+(\d+):(\d+)$/u', $reference, $matches)) {
        return null;
    }

    return [
        'book' => trim((string) $matches[1]),
        'chapter_number' => (int) $matches[2],
        'verse_number' => (int) $matches[3],
        'verse_text' => $verseText,
    ];
}

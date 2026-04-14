<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once __DIR__ . '/import_translation_helpers.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/import_translation_vpl.php TRANSLATION /path/to/source.vpl\n");
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

$bookDefinitions = canonical_book_definitions();
$bookAliasMap = canonical_book_alias_map($bookDefinitions);
$lines = preg_split('/\R/', $contents) ?: [];
$verseRows = [];
$importedCount = 0;
$skippedMetadataCount = 0;
$skippedUnknownBookCount = 0;

foreach ($lines as $line) {
    $parsedVerse = parse_vpl_line($line);

    if ($parsedVerse === null) {
        $skippedMetadataCount++;
        continue;
    }

    $bookKey = normalize_book_alias($parsedVerse['book']);

    if (!isset($bookAliasMap[$bookKey])) {
        $skippedUnknownBookCount++;
        continue;
    }

    $verseText = clean_verse_text($parsedVerse['verse_text']);

    if ($verseText === '') {
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

    if ($skippedUnknownBookCount > 0) {
        fwrite(STDOUT, "Skipped {$skippedUnknownBookCount} lines for books outside the app's current 66-book catalog.\n");
    }

    if ($skippedMetadataCount > 0) {
        fwrite(STDOUT, "Ignored {$skippedMetadataCount} non-verse lines.\n");
    }
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

function parse_vpl_line(string $line): ?array
{
    $trimmedLine = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? '');

    if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
        return null;
    }

    $referencePart = $trimmedLine;
    $verseText = '';

    if (str_contains($trimmedLine, "\t")) {
        [$referencePart, $verseText] = array_pad(explode("\t", $trimmedLine, 2), 2, '');
        $referencePart = trim($referencePart);
        $verseText = trim($verseText);
    }

    if ($verseText === '' && preg_match('/^(.+?\s+\d+:\d+)\s+(.*)$/u', $trimmedLine, $matches)) {
        $referencePart = trim((string) $matches[1]);
        $verseText = trim((string) $matches[2]);
    }

    if (!preg_match('/^(.+?)\s+(\d+):(\d+)$/u', $referencePart, $referenceMatches)) {
        return null;
    }

    return [
        'book' => trim((string) $referenceMatches[1]),
        'chapter_number' => (int) $referenceMatches[2],
        'verse_number' => (int) $referenceMatches[3],
        'verse_text' => $verseText,
    ];
}

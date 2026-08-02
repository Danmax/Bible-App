<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';
require_once __DIR__ . '/import_translation_helpers.php';
require_once __DIR__ . '/scripture_reference_import_helpers.php';

const CROSS_REFERENCE_DATASET = 'OpenBible.info Cross References (CC BY 4.0)';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/import_cross_references.php /path/to/cross_references.txt\n");
    exit(1);
}

$sourcePath = (string) $argv[1];

if (!is_file($sourcePath) || !is_readable($sourcePath)) {
    fwrite(STDERR, "Cross-reference file is not readable: {$sourcePath}\n");
    exit(1);
}

$handle = fopen($sourcePath, 'rb');

if ($handle === false) {
    fwrite(STDERR, "Unable to open cross-reference file: {$sourcePath}\n");
    exit(1);
}

$bookAliases = canonical_book_alias_map(canonical_book_definitions());
$pdo = db();
$referenceIds = [];
$imported = 0;
$skipped = 0;
$lineNumber = 0;

$referenceInsert = $pdo->prepare(
    'INSERT INTO scripture_references (
        start_book_id,
        start_chapter,
        start_verse,
        end_book_id,
        end_chapter,
        end_verse
    ) VALUES (
        :start_book_id,
        :start_chapter,
        :start_verse,
        :end_book_id,
        :end_chapter,
        :end_verse
    )
    ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
);
$crossReferenceInsert = $pdo->prepare(
    'INSERT INTO cross_references (
        source_reference_id,
        target_reference_id,
        rank_score,
        relationship_type,
        source_dataset,
        source_key
    ) VALUES (
        :source_reference_id,
        :target_reference_id,
        :rank_score,
        "related",
        :source_dataset,
        :source_key
    )
    ON DUPLICATE KEY UPDATE
        rank_score = VALUES(rank_score),
        source_key = VALUES(source_key),
        updated_at = CURRENT_TIMESTAMP'
);

$pdo->beginTransaction();

try {
    while (($row = fgetcsv($handle, 0, "\t", '"', '')) !== false) {
        $lineNumber++;

        if ($row === [] || str_starts_with(trim((string) ($row[0] ?? '')), '#')) {
            continue;
        }

        $source = parse_osis_scripture_reference((string) ($row[0] ?? ''), $bookAliases);
        $target = parse_osis_scripture_reference((string) ($row[1] ?? ''), $bookAliases);

        if ($source === null || $target === null) {
            if ($lineNumber === 1) {
                continue;
            }

            $skipped++;
            continue;
        }

        $sourceId = find_or_create_canonical_scripture_reference($pdo, $referenceInsert, $referenceIds, $source);
        $targetId = find_or_create_canonical_scripture_reference($pdo, $referenceInsert, $referenceIds, $target);
        $rankScore = max(0, (int) ($row[2] ?? 0));

        $crossReferenceInsert->execute([
            'source_reference_id' => $sourceId,
            'target_reference_id' => $targetId,
            'rank_score' => $rankScore,
            'source_dataset' => CROSS_REFERENCE_DATASET,
            'source_key' => substr(trim((string) $row[0]) . '>' . trim((string) $row[1]), 0, 190),
        ]);
        $imported++;

        if ($imported % 2000 === 0) {
            $pdo->commit();
            fwrite(STDOUT, "Imported {$imported} cross-references...\n");
            $pdo->beginTransaction();
        }
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fclose($handle);
    fwrite(STDERR, "Import failed near line {$lineNumber}: {$exception->getMessage()}\n");
    exit(1);
}

fclose($handle);
fwrite(STDOUT, "Imported {$imported} cross-references from " . CROSS_REFERENCE_DATASET . ".\n");
fwrite(STDOUT, "Skipped {$skipped} unrecognized rows.\n");

<?php

declare(strict_types=1);

function parse_osis_scripture_reference(string $value, array $bookAliases): ?array
{
    $normalized = trim($value);

    if (!preg_match(
        '/^([1-3]?[A-Za-z]+)\.(\d+)\.(\d+)(?:-([1-3]?[A-Za-z]+)\.(\d+)\.(\d+))?$/',
        $normalized,
        $matches
    )) {
        return null;
    }

    $startBookKey = normalize_book_alias((string) $matches[1]);
    $endBookKey = isset($matches[4]) ? normalize_book_alias((string) $matches[4]) : $startBookKey;

    if (!isset($bookAliases[$startBookKey], $bookAliases[$endBookKey])) {
        return null;
    }

    $reference = [
        'start_book_id' => (int) $bookAliases[$startBookKey],
        'start_chapter' => (int) $matches[2],
        'start_verse' => (int) $matches[3],
        'end_book_id' => (int) $bookAliases[$endBookKey],
        'end_chapter' => isset($matches[5]) ? (int) $matches[5] : (int) $matches[2],
        'end_verse' => isset($matches[6]) ? (int) $matches[6] : (int) $matches[3],
    ];
    $startOrdinal = ($reference['start_book_id'] * 1000000)
        + ($reference['start_chapter'] * 1000)
        + $reference['start_verse'];
    $endOrdinal = ($reference['end_book_id'] * 1000000)
        + ($reference['end_chapter'] * 1000)
        + $reference['end_verse'];

    return $reference['start_chapter'] > 0
        && $reference['start_verse'] > 0
        && $reference['end_chapter'] > 0
        && $reference['end_verse'] > 0
        && $endOrdinal >= $startOrdinal
        ? $reference
        : null;
}

function find_or_create_canonical_scripture_reference(
    PDO $pdo,
    PDOStatement $statement,
    array &$referenceIds,
    array $reference
): int {
    $cacheKey = implode(':', [
        $reference['start_book_id'],
        $reference['start_chapter'],
        $reference['start_verse'],
        $reference['end_book_id'],
        $reference['end_chapter'],
        $reference['end_verse'],
    ]);

    if (isset($referenceIds[$cacheKey])) {
        return $referenceIds[$cacheKey];
    }

    $statement->execute($reference);
    $referenceId = (int) $pdo->lastInsertId();

    if ($referenceId < 1) {
        throw new RuntimeException('Could not resolve canonical reference ' . $cacheKey . '.');
    }

    $referenceIds[$cacheKey] = $referenceId;

    return $referenceId;
}

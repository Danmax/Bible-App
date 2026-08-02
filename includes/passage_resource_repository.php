<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bible_repository.php';

function fetch_passage_cross_references(
    int $bookId,
    int $chapterNumber,
    int $startVerse,
    int $endVerse,
    string $translation,
    int $limit = 8
): array {
    if ($bookId < 1 || $chapterNumber < 1 || $startVerse < 1) {
        return [];
    }

    $endVerse = max($startVerse, $endVerse);
    $limit = max(1, min(25, $limit));
    $statement = db()->prepare(
        'SELECT
            target_ref.start_book_id AS book_id,
            target_ref.start_chapter AS chapter_number,
            target_ref.start_verse,
            target_ref.end_book_id,
            target_ref.end_chapter,
            target_ref.end_verse,
            start_book.name AS book_name,
            start_book.abbreviation,
            end_book.name AS end_book_name,
            end_book.abbreviation AS end_abbreviation,
            MAX(cross_references.rank_score) AS rank_score,
            COUNT(*) AS match_count,
            SUBSTRING_INDEX(
                GROUP_CONCAT(cross_references.relationship_type ORDER BY cross_references.rank_score DESC),
                ",",
                1
            ) AS relationship_type
        FROM cross_references
        INNER JOIN scripture_references AS source_ref
            ON source_ref.id = cross_references.source_reference_id
        INNER JOIN scripture_references AS target_ref
            ON target_ref.id = cross_references.target_reference_id
        INNER JOIN books AS start_book ON start_book.id = target_ref.start_book_id
        INNER JOIN books AS end_book ON end_book.id = target_ref.end_book_id
        WHERE source_ref.start_book_id = :book_id
            AND source_ref.start_chapter = :chapter_number
            AND source_ref.start_verse <= :end_verse
            AND source_ref.end_book_id = :book_id
            AND source_ref.end_chapter = :chapter_number
            AND source_ref.end_verse >= :start_verse
        GROUP BY
            target_ref.id,
            target_ref.start_book_id,
            target_ref.start_chapter,
            target_ref.start_verse,
            target_ref.end_book_id,
            target_ref.end_chapter,
            target_ref.end_verse,
            start_book.name,
            start_book.abbreviation,
            end_book.name,
            end_book.abbreviation
        ORDER BY MAX(cross_references.rank_score) DESC, target_ref.start_book_id ASC, target_ref.start_chapter ASC, target_ref.start_verse ASC
        LIMIT ' . $limit
    );
    $statement->execute([
        'book_id' => $bookId,
        'chapter_number' => $chapterNumber,
        'start_verse' => $startVerse,
        'end_verse' => $endVerse,
    ]);

    $references = $statement->fetchAll();
    $chapterCache = [];

    foreach ($references as &$reference) {
        $targetBookId = (int) $reference['book_id'];
        $targetChapter = (int) $reference['chapter_number'];
        $targetEndBookId = (int) $reference['end_book_id'];
        $targetEndChapter = (int) $reference['end_chapter'];
        $targetStart = (int) $reference['start_verse'];
        $targetEnd = (int) $reference['end_verse'];
        $previewVerses = passage_resource_boundary_verses(
            $targetBookId,
            $targetChapter,
            $targetStart,
            $targetEndBookId,
            $targetEndChapter,
            $targetEnd,
            $translation,
            $chapterCache
        );
        $previewParts = [];

        foreach ($previewVerses as $verse) {
            $previewParts[] = trim(sprintf(
                '%d %s',
                (int) ($verse['verse_number'] ?? 0),
                trim((string) ($verse['verse_text'] ?? ''))
            ));
        }

        $reference['reference_label'] = passage_resource_reference_label($reference);
        $reference['verse_text'] = trim(implode(' ', $previewParts));
        $reference['translation'] = strtoupper($translation);
    }
    unset($reference);

    return $references;
}

function passage_resource_reference_label(array $reference): string
{
    $label = sprintf(
        '%s %d:%d',
        (string) ($reference['book_name'] ?? 'Scripture'),
        (int) ($reference['chapter_number'] ?? 0),
        (int) ($reference['start_verse'] ?? 0)
    );
    $startBookId = (int) ($reference['book_id'] ?? 0);
    $startChapter = (int) ($reference['chapter_number'] ?? 0);
    $endBookId = (int) ($reference['end_book_id'] ?? $startBookId);
    $endChapter = (int) ($reference['end_chapter'] ?? $startChapter);
    $endVerse = (int) ($reference['end_verse'] ?? 0);

    if ($endBookId !== $startBookId) {
        $label .= sprintf(
            '-%s %d:%d',
            (string) ($reference['end_book_name'] ?? 'Scripture'),
            $endChapter,
            $endVerse
        );
    } elseif ($endChapter !== $startChapter) {
        $label .= sprintf('-%d:%d', $endChapter, $endVerse);
    } elseif ($endVerse > (int) ($reference['start_verse'] ?? 0)) {
        $label .= '-' . $endVerse;
    }

    return $label;
}

function passage_resource_boundary_verses(
    int $startBookId,
    int $startChapter,
    int $startVerse,
    int $endBookId,
    int $endChapter,
    int $endVerse,
    string $translation,
    array &$chapterCache
): array {
    $loadChapter = static function (int $bookId, int $chapter) use ($translation, &$chapterCache): array {
        $cacheKey = $bookId . ':' . $chapter . ':' . strtoupper($translation);

        if (!isset($chapterCache[$cacheKey])) {
            $chapterCache[$cacheKey] = fetch_chapter_verses($bookId, $chapter, $translation);
        }

        return $chapterCache[$cacheKey];
    };
    $startChapterVerses = $loadChapter($startBookId, $startChapter);

    if ($startBookId === $endBookId && $startChapter === $endChapter) {
        return array_values(array_filter(
            $startChapterVerses,
            static fn(array $verse): bool => (int) ($verse['verse_number'] ?? 0) >= $startVerse
                && (int) ($verse['verse_number'] ?? 0) <= $endVerse
        ));
    }

    $startBoundary = array_slice(array_values(array_filter(
        $startChapterVerses,
        static fn(array $verse): bool => (int) ($verse['verse_number'] ?? 0) >= $startVerse
    )), 0, 3);
    $endBoundary = array_slice(array_values(array_filter(
        $loadChapter($endBookId, $endChapter),
        static fn(array $verse): bool => (int) ($verse['verse_number'] ?? 0) <= $endVerse
    )), -3);

    return array_merge($startBoundary, $endBoundary);
}

function passage_resource_relationship_label(string $relationshipType): string
{
    return match (strtolower(trim($relationshipType))) {
        'parallel' => 'Parallel passage',
        'quotation' => 'Quotation',
        'prophecy' => 'Prophecy and fulfillment',
        'theme' => 'Shared theme',
        default => 'Related passage',
    };
}

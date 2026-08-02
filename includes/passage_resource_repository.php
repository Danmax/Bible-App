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
            AND source_ref.end_book_id = :end_book_id
            AND source_ref.end_chapter = :end_chapter_number
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
        'end_book_id' => $bookId,
        'end_chapter_number' => $chapterNumber,
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

function fetch_passage_commentaries(
    int $bookId,
    int $chapterNumber,
    int $startVerse,
    int $endVerse,
    int $limit = 6
): array {
    if ($bookId < 1 || $chapterNumber < 1 || $startVerse < 1) {
        return [];
    }

    $endVerse = max($startVerse, $endVerse);
    $limit = max(1, min(20, $limit));
    $statement = db()->prepare(
        'SELECT
            commentary_entries.id,
            commentary_entries.section_title,
            commentary_entries.body_text,
            commentary_entries.source_url AS entry_source_url,
            commentary_entries.sort_order,
            commentary_resources.slug AS resource_slug,
            commentary_resources.title AS resource_title,
            commentary_resources.author,
            commentary_resources.tradition_label,
            commentary_resources.study_level,
            commentary_resources.license_name,
            commentary_resources.license_url,
            commentary_resources.source_url AS resource_source_url,
            scripture_references.start_book_id AS book_id,
            scripture_references.start_chapter AS chapter_number,
            scripture_references.start_verse,
            scripture_references.end_book_id,
            scripture_references.end_chapter,
            scripture_references.end_verse,
            start_book.name AS book_name,
            end_book.name AS end_book_name
        FROM commentary_entries
        INNER JOIN commentary_resources
            ON commentary_resources.id = commentary_entries.resource_id
        INNER JOIN scripture_references
            ON scripture_references.id = commentary_entries.scripture_reference_id
        INNER JOIN books AS start_book ON start_book.id = scripture_references.start_book_id
        INNER JOIN books AS end_book ON end_book.id = scripture_references.end_book_id
        WHERE commentary_resources.is_active = 1
            AND (
                scripture_references.start_book_id * 1000000
                + scripture_references.start_chapter * 1000
                + scripture_references.start_verse
            ) <= :passage_end
            AND (
                scripture_references.end_book_id * 1000000
                + scripture_references.end_chapter * 1000
                + scripture_references.end_verse
            ) >= :passage_start
        ORDER BY
            commentary_resources.priority DESC,
            commentary_entries.sort_order ASC,
            commentary_resources.title ASC,
            commentary_entries.id ASC
        LIMIT ' . $limit
    );
    $passageBase = ($bookId * 1000000) + ($chapterNumber * 1000);
    $statement->execute([
        'passage_start' => $passageBase + $startVerse,
        'passage_end' => $passageBase + $endVerse,
    ]);
    $entries = $statement->fetchAll();

    foreach ($entries as &$entry) {
        $entry['reference_label'] = passage_resource_reference_label($entry);
        $entry['source_url'] = trim((string) ($entry['entry_source_url'] ?? '')) !== ''
            ? (string) $entry['entry_source_url']
            : (string) ($entry['resource_source_url'] ?? '');
    }
    unset($entry);

    return $entries;
}

function commentary_study_level_label(string $studyLevel): string
{
    return match (strtolower(trim($studyLevel))) {
        'technical' => 'Technical',
        'pastoral' => 'Pastoral',
        default => 'Devotional',
    };
}

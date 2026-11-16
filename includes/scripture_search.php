<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/scripts/import_translation_helpers.php';

/** Resolve aliases against installed books, without assuming their database IDs. */
function scripture_book_aliases(array $books): array
{
    $definitions = canonical_book_definitions();
    $canonicalAliases = canonical_book_alias_map($definitions);
    $additional = ['jn' => 43, 'mk' => 41, 'lk' => 42, 'mt' => 40, 'revelations' => 66];
    $aliases = [];
    foreach ($books as $book) {
        $name = normalize_book_key((string) $book['name']);
        $aliases[$name] = $book;
        $aliases[normalize_book_key((string) $book['abbreviation'])] = $book;
        foreach ($definitions as $canonicalId => $definition) {
            if ($name !== normalize_book_key($definition['name'])) {
                continue;
            }
            foreach ($canonicalAliases + $additional as $alias => $id) {
                if ($id === $canonicalId) {
                    $aliases[normalize_book_key((string) $alias)] = $book;
                }
            }
        }
    }
    return $aliases;
}

function parse_reference_query(string $query, array $books): ?array
{
    $query = str_replace(["\u{2013}", "\u{2014}", "\u{2212}", "\u{00A0}"], ['-', '-', '-', ' '], trim($query));
    $query = preg_replace('/\s+/u', ' ', $query) ?? $query;
    if (!preg_match('/^(.+?)\s*(\d+)(?:\s*[:.]\s*(\d+))?(?:\s*-\s*(?:(\d+)\s*[:.]\s*)?(\d+))?$/u', $query, $matches)) {
        return null;
    }
    $book = scripture_book_aliases($books)[normalize_book_key($matches[1])] ?? null;
    if ($book === null) {
        return null;
    }
    $chapter = (int) $matches[2];
    $start = ($matches[3] ?? '') !== '' ? (int) $matches[3] : null;
    $endChapter = ($matches[4] ?? '') !== '' ? (int) $matches[4] : $chapter;
    $end = ($matches[5] ?? '') !== '' ? (int) $matches[5] : null;
    $chapterCounts = [50,40,27,36,34,24,21,4,31,24,22,25,29,36,10,13,10,42,150,31,12,8,66,52,5,48,12,14,3,9,1,4,7,3,3,3,2,14,4,28,16,24,21,28,16,16,13,6,6,4,4,5,3,6,4,3,1,13,5,5,3,5,1,1,1,22];
    $maximumChapter = null;
    foreach (canonical_book_definitions() as $id => $definition) {
        if (normalize_book_key($definition['name']) === normalize_book_key((string) $book['name'])) {
            $maximumChapter = $chapterCounts[$id - 1];
            break;
        }
    }
    if ($chapter < 1 || $endChapter < $chapter || ($maximumChapter !== null && max($chapter, $endChapter) > $maximumChapter)) {
        throw new InvalidArgumentException('Choose a valid chapter for ' . $book['name'] . ($maximumChapter !== null ? ' (1–' . $maximumChapter . ').' : '.'));
    }
    if (($start !== null && $start < 1) || ($end !== null && ($start === null || $end < 1 || ($endChapter === $chapter && $end < $start)))) {
        throw new InvalidArgumentException('Enter a verse range in reading order, such as John 3:16–18.');
    }
    return ['book_id' => (int) $book['id'], 'book_name' => (string) $book['name'], 'chapter' => $chapter, 'start_verse' => $start, 'end_verse' => $end, 'end_chapter' => $endChapter];
}

function scripture_search_options(array $options): array
{
    return [
        'page' => max(1, min(50000, (int) ($options['page'] ?? 1))),
        'match' => in_array($options['match'] ?? '', ['all', 'any', 'phrase', 'related'], true) ? $options['match'] : 'all',
        'scope' => in_array($options['scope'] ?? '', ['old', 'new'], true) ? $options['scope'] : 'all',
        'book_id' => max(0, (int) ($options['book_id'] ?? 0)),
    ];
}

function scripture_search_text(string $text): string
{
    $text = mb_strtolower(str_replace(["’", "‘"], "'", $text));
    return trim(preg_replace('/[^\p{L}\p{N}\x{0027}]+/u', ' ', $text) ?? $text);
}

function scripture_search_contains(string $text, string $phrase): bool
{
    return $phrase !== '' && str_contains(' ' . scripture_search_text($text) . ' ', ' ' . scripture_search_text($phrase) . ' ');
}

function scripture_search_query_parts(string $query, string $translation = 'KJV'): array
{
    $phrases = [];
    $remaining = preg_replace_callback('/["“]([^"”]+)["”]/u', static function (array $match) use (&$phrases): string {
        $phrases[] = scripture_search_text($match[1]);
        return ' ';
    }, $query) ?? $query;
    $stopWords = strtoupper($translation) === 'RVR' ? [] : array_fill_keys(['a', 'an', 'the', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with', 'is', 'are', 'was', 'were', 'that', 'this', 'it'], true);
    $words = array_values(array_filter(explode(' ', scripture_search_text($remaining)), static fn(string $word): bool => $word !== '' && !isset($stopWords[$word])));
    $parts = array_values(array_unique(array_filter(array_merge($phrases, $words))));
    return $parts !== [] ? $parts : [scripture_search_text($query)];
}

function scripture_search_match(array $verse, string $query, string $translation, string $mode): ?array
{
    $text = (string) $verse['verse_text'];
    $parts = $mode === 'phrase' ? [scripture_search_text(trim($query, '"“” '))] : scripture_search_query_parts($query, $translation);
    $matched = array_values(array_filter($parts, static fn(string $part): bool => scripture_search_contains($text, $part)));
    $exact = scripture_search_contains($text, scripture_search_text(trim($query, '"“” ')));
    if ($mode === 'all' || $mode === 'phrase') {
        if (count($matched) !== count($parts)) {
            return null;
        }
    } elseif ($matched === [] && $mode !== 'related') {
        return null;
    }
    if ($mode === 'related' && $matched === []) {
        $related = strtoupper($translation) === 'RVR' ? [] : scripture_search_expanded_terms(scripture_search_terms($query));
        if (!array_filter($related, static fn(string $part): bool => scripture_search_contains($text, $part))) {
            return null;
        }
        return ['score' => 1, 'reason' => 'Related wording'];
    }
    return ['score' => ($exact ? 1000 : 0) + count($matched) * 100, 'reason' => $exact ? 'Exact phrase' : (count($matched) === count($parts) ? 'All search words' : 'Some search words')];
}

function scripture_search_like(string $value): string
{
    return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value) . '%';
}

function scripture_highlight_matches(string $text, string $query): string
{
    $terms = array_values(array_unique(array_filter(explode(' ', scripture_search_text($query)), static fn(string $term): bool => $term !== '')));
    if ($terms === []) {
        return e($text);
    }
    $pattern = '/(?<![\p{L}\p{N}])(' . implode('|', array_map(static fn(string $term): string => preg_quote($term, '/'), $terms)) . ')(?![\p{L}\p{N}])/iu';
    $segments = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($segments === false) {
        return e($text);
    }
    return implode('', array_map(static fn(string $segment, int $index): string => $index % 2 === 1 ? '<mark>' . e($segment) . '</mark>' : e($segment), $segments, array_keys($segments)));
}

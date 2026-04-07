<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

function bible_reader_url(array $params = []): string
{
    $query = http_build_query(array_filter(
        $params,
        static fn($value) => $value !== null && $value !== ''
    ));

    return app_url('bible.php' . ($query === '' ? '' : '?' . $query));
}

function bible_normalize_reader_mode(?string $mode): string
{
    $normalizedMode = strtolower(trim((string) $mode));

    return in_array($normalizedMode, ['verse', 'paragraph'], true) ? $normalizedMode : 'verse';
}

function push_recent_bible_search(string $query, string $translation): void
{
    $normalizedQuery = trim($query);
    $normalizedTranslation = strtoupper(trim($translation));

    if ($normalizedQuery === '') {
        return;
    }

    $recentSearches = $_SESSION['recent_bible_searches'] ?? [];
    $recentSearches = array_values(array_filter(
        is_array($recentSearches) ? $recentSearches : [],
        static fn($item): bool => is_array($item)
            && (($item['query'] ?? '') !== $normalizedQuery || ($item['translation'] ?? '') !== $normalizedTranslation)
    ));

    array_unshift($recentSearches, [
        'query' => $normalizedQuery,
        'translation' => $normalizedTranslation,
    ]);

    $_SESSION['recent_bible_searches'] = array_slice($recentSearches, 0, 6);
}

function recent_bible_searches(): array
{
    $recentSearches = $_SESSION['recent_bible_searches'] ?? [];

    if (!is_array($recentSearches)) {
        return [];
    }

    return array_values(array_filter(
        $recentSearches,
        static fn($item): bool => is_array($item)
            && trim((string) ($item['query'] ?? '')) !== ''
            && trim((string) ($item['translation'] ?? '')) !== ''
    ));
}

function bible_analysis_stop_words(): array
{
    return array_fill_keys([
        'the', 'and', 'for', 'that', 'with', 'from', 'into', 'your', 'you', 'are', 'was', 'were',
        'have', 'has', 'had', 'not', 'but', 'all', 'any', 'can', 'his', 'her', 'him', 'our', 'out',
        'who', 'what', 'when', 'where', 'why', 'how', 'let', 'there', 'their', 'them', 'then', 'than',
        'this', 'these', 'those', 'will', 'shall', 'would', 'could', 'should', 'about', 'over', 'under',
        'through', 'after', 'before', 'because', 'been', 'being', 'also', 'unto', 'upon', 'they', 'she',
        'himself', 'herself', 'themselves', 'ourselves', 'which', 'whom', 'whose', 'said', 'says', 'say',
        'did', 'does', 'doing', 'very', 'more', 'most', 'much', 'many', 'each', 'every', 'some', 'such',
        'just', 'like', 'make', 'made', 'than', 'them', 'its', 'itself', 'again', 'still', 'here', 'there',
    ], true);
}

function bible_extract_analysis_tokens(string $text): array
{
    $matched = preg_match_all("/[\p{L}][\p{L}'-]*/u", $text, $matches);

    if ($matched === false) {
        return [];
    }

    $tokens = [];

    foreach ($matches[0] ?? [] as $token) {
        $normalized = trim(mb_strtolower((string) $token), "'- ");

        if ($normalized !== '') {
            $tokens[] = $normalized;
        }
    }

    return $tokens;
}

function bible_build_scripture_analysis(array $verses, string $query = '', int $limit = 8): ?array
{
    if ($verses === []) {
        return null;
    }

    $combinedText = trim(implode(' ', array_map(
        static fn(array $verse): string => trim((string) ($verse['verse_text'] ?? '')),
        $verses
    )));
    $allTokens = bible_extract_analysis_tokens($combinedText);
    $stopWords = bible_analysis_stop_words();
    $termCounts = [];

    foreach ($allTokens as $token) {
        if (mb_strlen($token) < 3 || isset($stopWords[$token])) {
            continue;
        }

        $termCounts[$token] = ($termCounts[$token] ?? 0) + 1;
    }

    arsort($termCounts);

    $topTerms = [];

    foreach (array_slice($termCounts, 0, $limit, true) as $term => $count) {
        $verseMatches = 0;

        foreach ($verses as $verse) {
            $verseText = mb_strtolower((string) ($verse['verse_text'] ?? ''));
            if ($verseText !== '' && mb_strpos($verseText, $term) !== false) {
                $verseMatches++;
            }
        }

        $topTerms[] = [
            'term' => $term,
            'count' => (int) $count,
            'verse_matches' => $verseMatches,
        ];
    }

    $relatedItems = [];
    $relatedTerms = [];

    foreach (bible_extract_analysis_tokens($query) as $queryTerm) {
        if (mb_strlen($queryTerm) < 3 || isset($stopWords[$queryTerm])) {
            continue;
        }

        $relatedTerms[] = $queryTerm;
    }

    foreach (array_keys($termCounts) as $term) {
        $relatedTerms[] = $term;
    }

    foreach (array_slice(array_values(array_unique($relatedTerms)), 0, 4) as $term) {
        $matchCount = 0;

        foreach ($verses as $verse) {
            $verseText = mb_strtolower((string) ($verse['verse_text'] ?? ''));
            if ($verseText !== '' && mb_strpos($verseText, $term) !== false) {
                $matchCount++;
            }
        }

        $relatedItems[] = [
            'term' => $term,
            'label' => mb_convert_case(str_replace('-', ' ', $term), MB_CASE_TITLE, 'UTF-8'),
            'match_count' => $matchCount,
        ];
    }

    return [
        'verse_count' => count($verses),
        'word_count' => count($allTokens),
        'focus_term_count' => count($termCounts),
        'top_terms' => $topTerms,
        'related_items' => $relatedItems,
    ];
}

function bible_share_reference(array $verses, string $translation): string
{
    if ($verses === []) {
        return 'Scripture';
    }

    $firstVerse = $verses[0];
    $lastVerse = $verses[count($verses) - 1];
    $bookName = (string) ($firstVerse['book_name'] ?? 'Scripture');
    $chapterNumber = (int) ($firstVerse['chapter_number'] ?? 0);
    $firstVerseNumber = (int) ($firstVerse['verse_number'] ?? 0);
    $lastVerseNumber = (int) ($lastVerse['verse_number'] ?? $firstVerseNumber);

    if ($firstVerseNumber > 0 && $lastVerseNumber > 0) {
        if ($firstVerseNumber === $lastVerseNumber) {
            return sprintf('%s %d:%d (%s)', $bookName, $chapterNumber, $firstVerseNumber, $translation);
        }

        return sprintf('%s %d:%d-%d (%s)', $bookName, $chapterNumber, $firstVerseNumber, $lastVerseNumber, $translation);
    }

    return sprintf('%s %d (%s)', $bookName, $chapterNumber, $translation);
}

function bible_share_text(array $verses): string
{
    if ($verses === []) {
        return '';
    }

    if (count($verses) === 1) {
        return trim((string) ($verses[0]['verse_text'] ?? ''));
    }

    $parts = [];

    foreach ($verses as $verse) {
        $parts[] = trim(sprintf(
            '%d %s',
            (int) ($verse['verse_number'] ?? 0),
            trim((string) ($verse['verse_text'] ?? ''))
        ));
    }

    return trim(implode(' ', array_filter($parts, static fn($part): bool => $part !== '')));
}

function bible_clamp_offset(int $offset, int $length): int
{
    return max(0, min($length, $offset));
}

function bible_save_multi_verse_highlight(
    int $userId,
    int $startVerseId,
    int $endVerseId,
    int $startOffset,
    int $endOffset,
    string $tag,
    string $note,
    string $highlightColor
): int {
    $startVerse = fetch_verse_by_id($startVerseId);
    $endVerse = fetch_verse_by_id($endVerseId);

    if ($startVerse === null || $endVerse === null) {
        throw new RuntimeException('The selected verses could not be found.');
    }

    if (
        (int) $startVerse['book_id'] !== (int) $endVerse['book_id']
        || (int) $startVerse['chapter_number'] !== (int) $endVerse['chapter_number']
        || (string) $startVerse['translation'] !== (string) $endVerse['translation']
    ) {
        throw new RuntimeException('Highlights can only span verses within the same chapter.');
    }

    $chapterVerses = fetch_chapter_verses(
        (int) $startVerse['book_id'],
        (int) $startVerse['chapter_number'],
        (string) $startVerse['translation']
    );

    $positions = [];

    foreach ($chapterVerses as $index => $verse) {
        $positions[(int) $verse['id']] = $index;
    }

    if (!isset($positions[$startVerseId], $positions[$endVerseId])) {
        throw new RuntimeException('The selected highlight range is unavailable in this chapter.');
    }

    $startIndex = (int) $positions[$startVerseId];
    $endIndex = (int) $positions[$endVerseId];

    if ($startIndex > $endIndex) {
        [$startIndex, $endIndex] = [$endIndex, $startIndex];
        [$startVerseId, $endVerseId] = [$endVerseId, $startVerseId];
        [$startOffset, $endOffset] = [$endOffset, $startOffset];
    }

    if ($startIndex === $endIndex) {
        $verseText = (string) $chapterVerses[$startIndex]['verse_text'];
        $length = mb_strlen($verseText);
        $normalizedStart = bible_clamp_offset($startOffset, $length);
        $normalizedEnd = bible_clamp_offset($endOffset, $length);

        if ($normalizedEnd <= $normalizedStart) {
            throw new RuntimeException('Select part of a verse before saving a highlight.');
        }

        save_bookmark_record(
            $userId,
            (int) $chapterVerses[$startIndex]['id'],
            $tag,
            $note,
            mb_substr($verseText, $normalizedStart, $normalizedEnd - $normalizedStart),
            $highlightColor,
            $normalizedStart,
            $normalizedEnd
        );

        return 1;
    }

    $pdo = db();
    $savedCount = 0;

    $pdo->beginTransaction();

    try {
        for ($index = $startIndex; $index <= $endIndex; $index++) {
            $verse = $chapterVerses[$index];
            $verseId = (int) $verse['id'];
            $verseText = (string) $verse['verse_text'];
            $verseLength = mb_strlen($verseText);

            if ($index === $startIndex) {
                $normalizedStart = bible_clamp_offset($startOffset, $verseLength);
                $normalizedEnd = $verseLength;

                if ($normalizedEnd <= $normalizedStart) {
                    continue;
                }

                save_bookmark_record(
                    $userId,
                    $verseId,
                    $tag,
                    $note,
                    mb_substr($verseText, $normalizedStart, $normalizedEnd - $normalizedStart),
                    $highlightColor,
                    $normalizedStart,
                    $normalizedEnd
                );
                $savedCount++;
                continue;
            }

            if ($index === $endIndex) {
                $normalizedStart = 0;
                $normalizedEnd = bible_clamp_offset($endOffset, $verseLength);

                if ($normalizedEnd <= $normalizedStart) {
                    continue;
                }

                save_bookmark_record(
                    $userId,
                    $verseId,
                    $tag,
                    $note,
                    mb_substr($verseText, $normalizedStart, $normalizedEnd - $normalizedStart),
                    $highlightColor,
                    $normalizedStart,
                    $normalizedEnd
                );
                $savedCount++;
                continue;
            }

            save_bookmark_record(
                $userId,
                $verseId,
                $tag,
                $note,
                null,
                $highlightColor,
                null,
                null
            );
            $savedCount++;
        }

        if ($savedCount === 0) {
            throw new RuntimeException('Select part of a verse before saving a highlight.');
        }

        $pdo->commit();

        return $savedCount;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

$pageTitle = 'Bible Reader';
$activePage = 'bible';
$user = is_logged_in() ? refresh_current_user() : null;
$pageError = null;
$searchMessage = null;
$query = trim($_GET['q'] ?? '');
$selectedTranslation = trim($_GET['translation'] ?? APP_DEFAULT_TRANSLATION);
$selectedBookId = (int) ($_GET['book_id'] ?? 0);
$selectedChapter = (int) ($_GET['chapter'] ?? 0);
$selectedVerseNumber = (int) ($_GET['verse'] ?? 0);
$readerMode = bible_normalize_reader_mode($_GET['reader_mode'] ?? 'verse');
$translations = supported_translations();
$searchResults = [];
$searchHeading = 'Bible Reader';
$selectedTranslationHasData = true;
$bookCatalog = [];
$selectedBook = null;
$bookChapters = [];
$chapterVerseSet = [];
$browseVerses = [];
$chapterBookmarks = [];
$translationAvailability = [];
$displayMode = 'catalog';
$referenceQuery = null;
$themedSeries = [];
$previousChapterUrl = null;
$nextChapterUrl = null;
$previousVerseUrl = null;
$nextVerseUrl = null;
$wholeChapterUrl = null;
$bookOverviewUrl = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!is_logged_in()) {
        set_flash('Sign in to save bookmarks and highlights.', 'warning');
        redirect('login.php');
    }

    try {
        $action = trim($_POST['action'] ?? 'save-bookmark');
        $tag = trim($_POST['tag'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $selectedText = trim($_POST['selected_text'] ?? '');
        $highlightColor = trim($_POST['highlight_color'] ?? '');
        $selectionStart = trim($_POST['selection_start'] ?? '');
        $selectionEnd = trim($_POST['selection_end'] ?? '');
        $rangeStartVerseId = (int) ($_POST['range_start_verse_id'] ?? 0);
        $rangeEndVerseId = (int) ($_POST['range_end_verse_id'] ?? 0);
        $rangeStartOffset = trim($_POST['range_start_offset'] ?? '');
        $rangeEndOffset = trim($_POST['range_end_offset'] ?? '');

        if ($action === 'save-section' && $selectedText === '') {
            set_flash('Select part of a verse before saving a highlight.', 'warning');
        } elseif (
            $action === 'save-section'
            && $rangeStartVerseId > 0
            && $rangeEndVerseId > 0
            && $rangeStartVerseId !== $rangeEndVerseId
        ) {
            $savedCount = bible_save_multi_verse_highlight(
                (int) $user['id'],
                $rangeStartVerseId,
                $rangeEndVerseId,
                $rangeStartOffset === '' ? 0 : (int) $rangeStartOffset,
                $rangeEndOffset === '' ? 0 : (int) $rangeEndOffset,
                $tag,
                $note,
                $highlightColor === '' ? 'neon-yellow' : $highlightColor
            );

            set_flash(
                $savedCount > 1
                    ? 'Highlight saved across ' . $savedCount . ' verses.'
                    : 'Highlight saved.',
                'success'
            );
        } else {
            save_bookmark_record(
                (int) $user['id'],
                (int) ($_POST['verse_id'] ?? 0),
                $tag,
                $note,
                $selectedText === '' ? null : $selectedText,
                $highlightColor === '' ? null : $highlightColor,
                $selectionStart === '' ? null : (int) $selectionStart,
                $selectionEnd === '' ? null : (int) $selectionEnd
            );

            set_flash($action === 'save-section' ? 'Highlight saved.' : 'Verse saved.', 'success');
        }

        $redirectQuery = http_build_query(array_filter([
            'q' => trim($_POST['return_query'] ?? ''),
            'translation' => trim($_POST['return_translation'] ?? APP_DEFAULT_TRANSLATION),
            'book_id' => (int) ($_POST['return_book_id'] ?? 0) ?: null,
            'chapter' => (int) ($_POST['return_chapter'] ?? 0) ?: null,
            'verse' => (int) ($_POST['return_verse'] ?? 0) ?: null,
            'reader_mode' => bible_normalize_reader_mode($_POST['return_reader_mode'] ?? $readerMode),
        ], static fn($value) => $value !== null && $value !== ''));

        redirect('bible.php' . ($redirectQuery === '' ? '' : '?' . $redirectQuery));
    } catch (Throwable $exception) {
        $pageError = 'That bookmark or highlight could not be saved because the database is unavailable.';
    }
}

try {
    $translations = fetch_available_translations();

    if (!in_array($selectedTranslation, $translations, true)) {
        $selectedTranslation = in_array(APP_DEFAULT_TRANSLATION, $translations, true)
            ? APP_DEFAULT_TRANSLATION
            : ($translations[0] ?? APP_DEFAULT_TRANSLATION);
    }

    foreach ($translations as $translation) {
        $translationAvailability[$translation] = uses_external_translation($translation)
            ? external_translation_available($translation)
            : count_records(
                'SELECT COUNT(*) FROM verses WHERE translation = :translation',
                ['translation' => $translation]
            ) > 0;
    }

    $bookCatalog = fetch_book_catalog($selectedTranslation);
    $selectedTranslationHasData = $translationAvailability[$selectedTranslation] ?? false;
    $referenceQuery = $query !== '' ? parse_reference_query($query, fetch_books()) : null;
    $themedSeries = fetch_thematic_scripture_series($selectedTranslation);
    if ($query !== '') {
        push_recent_bible_search($query, $selectedTranslation);
    }

    if ($referenceQuery !== null) {
        $selectedBookId = (int) $referenceQuery['book_id'];
        $selectedChapter = (int) $referenceQuery['chapter'];
        $selectedVerseNumber = (int) ($referenceQuery['start_verse'] ?? 0);
    }

    if ($selectedBookId > 0) {
        $selectedBook = fetch_book_by_id($selectedBookId);
        if ($selectedBook) {
            $bookChapters = fetch_book_chapters($selectedBookId, $selectedTranslation);
        }
    }

    if ($referenceQuery !== null && $selectedBook && $selectedChapter > 0) {
        $chapterVerseSet = fetch_chapter_verses($selectedBookId, $selectedChapter, $selectedTranslation);
        $browseVerses = $chapterVerseSet;
        $hasReferenceVerse = ($referenceQuery['start_verse'] ?? null) !== null;

        if ($hasReferenceVerse) {
            $startVerse = (int) $referenceQuery['start_verse'];
            $endVerse = isset($referenceQuery['end_verse']) && $referenceQuery['end_verse'] !== null
                ? (int) $referenceQuery['end_verse']
                : $startVerse;
            $browseVerses = array_values(array_filter(
                $chapterVerseSet,
                static fn(array $verse): bool => (int) $verse['verse_number'] >= $startVerse
                    && (int) $verse['verse_number'] <= $endVerse
            ));
        }

        $displayMode = $hasReferenceVerse ? 'passage' : 'chapter';
        $searchHeading = build_reference_heading($referenceQuery, $selectedTranslation);

        if ($chapterVerseSet === []) {
            $searchMessage = 'This chapter has no verses loaded for ' . $selectedTranslation . '.';
        } elseif ($browseVerses === []) {
            $searchMessage = 'Those verses were not found in this chapter.';
        } elseif (is_logged_in()) {
            $chapterBookmarks = fetch_bookmarks_for_verses(
                (int) $user['id'],
                array_map(static fn(array $verse): int => (int) $verse['id'], $chapterVerseSet)
            );
        }
    } elseif ($query !== '') {
        $search = search_scripture($query, $selectedTranslation);
        $searchResults = $search['results'];
        $searchHeading = $search['heading'];
        $displayMode = 'search';

        if ($searchResults === []) {
            $searchMessage = $selectedTranslationHasData
                ? 'No verses matched that search. Try John 3:16, Ruth 2, or a keyword like grace.'
                : 'No verses are loaded yet for this translation.';
        }
    } elseif ($selectedBook && $selectedChapter > 0) {
        $chapterVerseSet = fetch_chapter_verses($selectedBookId, $selectedChapter, $selectedTranslation);
        $browseVerses = $chapterVerseSet;
        $searchHeading = sprintf('%s %d (%s)', $selectedBook['name'], $selectedChapter, $selectedTranslation);
        $displayMode = 'chapter';

        if ($chapterVerseSet === []) {
            $searchMessage = 'This chapter has no verses loaded for ' . $selectedTranslation . '.';
        } else {
            if ($selectedVerseNumber > 0) {
                $matchingVerse = array_values(
                    array_filter(
                        $chapterVerseSet,
                        static fn(array $verse): bool => (int) $verse['verse_number'] === $selectedVerseNumber
                    )
                );

                if ($matchingVerse !== []) {
                    $browseVerses = $matchingVerse;
                    $searchHeading = sprintf('%s %d:%d (%s)', $selectedBook['name'], $selectedChapter, $selectedVerseNumber, $selectedTranslation);
                    $displayMode = 'verse';
                }
            }

            if (is_logged_in()) {
            $chapterBookmarks = fetch_bookmarks_for_verses(
                (int) $user['id'],
                array_map(static fn(array $verse): int => (int) $verse['id'], $chapterVerseSet)
            );
            }
        }
    } elseif ($selectedBook) {
        $displayMode = 'book';
        $searchHeading = $selectedBook['name'] . ' (' . $selectedTranslation . ')';
        $searchMessage = $bookChapters === []
            ? 'This book has no chapter data loaded yet.'
            : 'Choose a chapter or a specific verse from the dropdowns above.';
    } else {
        $displayMode = 'catalog';
        $searchHeading = 'Bible Reader';
        $searchMessage = $bookCatalog === []
            ? 'No books are loaded yet.'
            : 'Choose a book, chapter, and optional verse from the dropdowns above.';
    }

} catch (Throwable $exception) {
    $pageError = 'Scripture content could not be loaded because the database is unavailable.';
}

if ($selectedBook && $bookChapters !== [] && $selectedChapter > 0) {
    $chapterNumbers = array_map(
        static fn(array $chapter): int => (int) $chapter['chapter_number'],
        $bookChapters
    );
    $currentChapterIndex = array_search($selectedChapter, $chapterNumbers, true);

    if ($currentChapterIndex !== false) {
        $previousChapterNumber = $chapterNumbers[$currentChapterIndex - 1] ?? null;
        $nextChapterNumber = $chapterNumbers[$currentChapterIndex + 1] ?? null;

        if ($previousChapterNumber !== null) {
            $previousChapterUrl = bible_reader_url([
                'translation' => $selectedTranslation,
                'book_id' => $selectedBookId,
                'chapter' => $previousChapterNumber,
                'reader_mode' => $readerMode,
            ]);
        }

        if ($nextChapterNumber !== null) {
            $nextChapterUrl = bible_reader_url([
                'translation' => $selectedTranslation,
                'book_id' => $selectedBookId,
                'chapter' => $nextChapterNumber,
                'reader_mode' => $readerMode,
            ]);
        }
    }
}

if ($selectedBook) {
    $bookOverviewUrl = bible_reader_url([
        'translation' => $selectedTranslation,
        'book_id' => $selectedBookId,
        'reader_mode' => $readerMode,
    ]);
}

if ($selectedBook && $selectedChapter > 0 && $selectedVerseNumber > 0 && $chapterVerseSet !== []) {
    $verseNumbers = array_map(
        static fn(array $verse): int => (int) $verse['verse_number'],
        $chapterVerseSet
    );
    $currentVerseIndex = array_search($selectedVerseNumber, $verseNumbers, true);

    if ($currentVerseIndex !== false) {
        $previousVerseNumber = $verseNumbers[$currentVerseIndex - 1] ?? null;
        $nextVerseNumber = $verseNumbers[$currentVerseIndex + 1] ?? null;

        if ($previousVerseNumber !== null) {
            $previousVerseUrl = bible_reader_url([
                'translation' => $selectedTranslation,
                'book_id' => $selectedBookId,
                'chapter' => $selectedChapter,
                'verse' => $previousVerseNumber,
                'reader_mode' => $readerMode,
            ]) . '#verse-' . $previousVerseNumber;
        }

        if ($nextVerseNumber !== null) {
            $nextVerseUrl = bible_reader_url([
                'translation' => $selectedTranslation,
                'book_id' => $selectedBookId,
                'chapter' => $selectedChapter,
                'verse' => $nextVerseNumber,
                'reader_mode' => $readerMode,
            ]) . '#verse-' . $nextVerseNumber;
        }
    }

    $wholeChapterUrl = bible_reader_url([
        'translation' => $selectedTranslation,
        'book_id' => $selectedBookId,
        'chapter' => $selectedChapter,
        'reader_mode' => $readerMode,
    ]) . '#verse-' . $selectedVerseNumber;
}

$verseOptions = [];
if ($chapterVerseSet !== []) {
    foreach ($chapterVerseSet as $verse) {
        $verseOptions[] = [
            'number' => (int) $verse['verse_number'],
            'text' => truncate_text((string) $verse['verse_text'], 54),
        ];
    }
}

$analysisVerses = [];

if ($displayMode === 'search' && $searchResults !== []) {
    $analysisVerses = $searchResults;
} elseif (($displayMode === 'chapter' || $displayMode === 'verse' || $displayMode === 'passage') && $browseVerses !== []) {
    $analysisVerses = $browseVerses;
}

$scriptureAnalysis = bible_build_scripture_analysis($analysisVerses, $query);
$analysisTitle = $displayMode === 'search' ? 'Search Concordance' : 'Passage Concordance';
$analysisIntro = $displayMode === 'search'
    ? 'Study repeated words and related search paths drawn from the verses returned above.'
    : 'Study repeated words and related search paths drawn from the passage above.';

$sharePayloadJson = null;

if (($displayMode === 'chapter' || $displayMode === 'verse' || $displayMode === 'passage') && $browseVerses !== []) {
    $shareParams = array_filter([
        'q' => $query !== '' ? $query : null,
        'translation' => $selectedTranslation,
        'book_id' => $selectedBookId ?: null,
        'chapter' => $selectedChapter ?: null,
        'verse' => $selectedVerseNumber ?: null,
        'reader_mode' => $readerMode,
    ], static fn($value) => $value !== null && $value !== '');
    $sharePath = 'bible.php';

    if ($shareParams !== []) {
        $sharePath .= '?' . http_build_query($shareParams);
    }

    $sharePayload = [
        'reference' => bible_share_reference($browseVerses, $selectedTranslation),
        'translation' => $selectedTranslation,
        'displayMode' => $displayMode,
        'text' => bible_share_text($browseVerses),
        'url' => app_url($sharePath, true),
        'verses' => array_map(
            static fn(array $verse): array => [
                'number' => (int) ($verse['verse_number'] ?? 0),
                'text' => trim((string) ($verse['verse_text'] ?? '')),
            ],
            $browseVerses
        ),
    ];
    $encodedSharePayload = json_encode(
        $sharePayload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
    $sharePayloadJson = is_string($encodedSharePayload) ? $encodedSharePayload : null;
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <?php if ($displayMode === 'catalog' || $displayMode === 'book'): ?>
            <div class="section-heading section-heading-rich">
                <div>
                    <p class="eyebrow">Bible Reader</p>
                    <h1>Sword of the Spirit</h1>
                    <p>Search by reference like John 3:28 or jump through a chapter with quick next and previous controls.</p>
                </div>

                <div class="quick-stat-row">
                    <div class="quick-stat">
                        <strong><?= e(strtoupper($selectedTranslation)); ?></strong>
                        <span>active translation</span>
                    </div>
                    <div class="quick-stat">
                        <strong><?= e((string) count($bookCatalog)); ?></strong>
                        <span>books loaded</span>
                    </div>
                    <div class="quick-stat">
                        <strong><?= e($selectedBook ? (string) $selectedChapter : 'Browse'); ?></strong>
                        <span>current chapter</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="panel scripture-panel top-gap">
            <div class="bible-search-shell">
                <form class="form-stack bible-search-form" method="get" data-translation-switch-form>
                    <div class="search-row search-row-compact search-row-scripture" data-voice-search>
                        <input type="search" name="q" value="<?= e($query); ?>" placeholder="Search Scripture: John 3:16 or grace" data-voice-search-input>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="book_id" value="<?= e($selectedBookId > 0 ? (string) $selectedBookId : ''); ?>">
                        <input type="hidden" name="chapter" value="<?= e($selectedChapter > 0 ? (string) $selectedChapter : ''); ?>">
                        <input type="hidden" name="verse" value="<?= e($selectedVerseNumber > 0 ? (string) $selectedVerseNumber : ''); ?>">
                        <input type="hidden" name="reader_mode" value="<?= e($readerMode); ?>">
                        <select name="translation" aria-label="Translation" data-translation-switch>
                            <?php foreach ($translations as $translation): ?>
                                <option value="<?= e($translation); ?>" <?= $selectedTranslation === $translation ? 'selected' : ''; ?>>
                                    <?= e($translation); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button button-secondary voice-search-button" type="button" data-voice-search-start aria-label="Speak your Bible search">
                            <svg class="voice-search-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M12 4a3 3 0 0 1 3 3v5a3 3 0 0 1-6 0V7a3 3 0 0 1 3-3Z" />
                                <path d="M19 11a7 7 0 0 1-14 0" />
                                <path d="M12 18v3" />
                                <path d="M9 21h6" />
                            </svg>
                        </button>
                        <button class="button button-primary" type="submit">Search</button>
                    </div>
                    <p class="muted-copy" data-voice-search-status>Try saying a verse, passage, or keyword and we will help you find it.</p>
                </form>

                <details class="bible-advanced-search top-gap-sm" <?= ($displayMode === 'catalog' || $selectedBookId > 0 || $query === '') ? 'open' : ''; ?>>
                    <summary>
                        <span>Advanced search</span>
                        <span class="muted-copy">Browse books, chapters, and verses</span>
                    </summary>

                    <div class="bible-advanced-search-body">
                        <form class="form-stack" method="get" data-reader-nav>
                            <div class="reader-select-row reader-select-row-compact">
                                <input type="hidden" name="translation" value="<?= e($selectedTranslation); ?>">
                                <input type="hidden" name="reader_mode" value="<?= e($readerMode); ?>">

                                <label class="reader-compact-label">
                                    <select name="book_id" data-reader-select="book">
                                        <option value="">Select book</option>
                                        <?php foreach ($bookCatalog as $book): ?>
                                            <option value="<?= e((string) $book['id']); ?>" <?= $selectedBookId === (int) $book['id'] ? 'selected' : ''; ?>>
                                                <?= e((string) $book['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label class="reader-compact-label">
                                    <select name="chapter" data-reader-select="chapter">
                                        <option value="">Select chapter</option>
                                        <?php foreach ($bookChapters as $chapter): ?>
                                            <option value="<?= e((string) $chapter['chapter_number']); ?>" <?= $selectedChapter === (int) $chapter['chapter_number'] ? 'selected' : ''; ?>>
                                                <?= e((string) $chapter['chapter_number']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label class="reader-compact-label">
                                    <select name="verse" data-reader-select="verse">
                                        <option value="">Whole chapter</option>
                                        <?php foreach ($verseOptions as $verseOption): ?>
                                            <option value="<?= e((string) $verseOption['number']); ?>" <?= $selectedVerseNumber === $verseOption['number'] ? 'selected' : ''; ?>>
                                                <?= e((string) $verseOption['number']); ?> - <?= e($verseOption['text']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <button class="button button-secondary reader-go" type="submit">Go</button>
                            </div>
                        </form>
                    </div>
                </details>
            </div>

            <div class="scripture-heading">
                <div>
                    <p class="eyebrow"><?= e(strtoupper($selectedTranslation)); ?></p>
                    <h2><?= e($searchHeading); ?></h2>
                </div>
                <div class="showcase-actions">
                    <?php if ($selectedChapter > 0): ?>
                        <div class="reader-mode-switch reader-mode-switch-compact">
                            <a class="mini-card <?= $readerMode === 'verse' ? 'is-active' : ''; ?>" href="<?= e(bible_reader_url([
                                'q' => $query !== '' ? $query : null,
                                'translation' => $selectedTranslation,
                                'book_id' => $selectedBookId ?: null,
                                'chapter' => $selectedChapter ?: null,
                                'verse' => $selectedVerseNumber ?: null,
                                'reader_mode' => 'verse',
                            ])); ?>">Verse</a>
                            <a class="mini-card <?= $readerMode === 'paragraph' ? 'is-active' : ''; ?>" href="<?= e(bible_reader_url([
                                'q' => $query !== '' ? $query : null,
                                'translation' => $selectedTranslation,
                                'book_id' => $selectedBookId ?: null,
                                'chapter' => $selectedChapter ?: null,
                                'verse' => $selectedVerseNumber ?: null,
                                'reader_mode' => 'paragraph',
                            ])); ?>">Paragraph</a>
                        </div>
                    <?php else: ?>
                        <span class="mini-card"><?= e(match ($displayMode) {
                            'search' => 'Search Results',
                            default => 'Reader',
                        }); ?></span>
                    <?php endif; ?>
                    <?php if ($selectedBook): ?>
                        <span class="mini-card"><?= e((string) $selectedBook['abbreviation']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (($displayMode === 'chapter' || $displayMode === 'verse' || $displayMode === 'passage') && count($translations) > 1): ?>
                <div class="translation-strip">
                    <?php foreach ($translations as $t): ?>
                        <?php if ($translationAvailability[$t] ?? false): ?>
                            <a
                                class="translation-chip <?= $selectedTranslation === $t ? 'is-active' : ''; ?>"
                                href="<?= e(bible_reader_url([
                                    'q' => $query !== '' ? $query : null,
                                    'translation' => $t,
                                    'book_id' => $selectedBookId ?: null,
                                    'chapter' => $selectedChapter ?: null,
                                    'verse' => $selectedVerseNumber ?: null,
                                    'reader_mode' => $readerMode,
                                ])); ?>"
                            ><?= e($t); ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($searchMessage): ?>
                <p class="empty-state top-gap-sm"><?= e($searchMessage); ?></p>
            <?php endif; ?>

            <?php if ($displayMode === 'catalog' && $themedSeries !== []): ?>
                <div class="top-gap">
                    <div class="panel-heading">
                        <div>
                            <h3>Today&apos;s Scripture Series</h3>
                            <p class="muted-copy">Jump in quickly with a few themed passages from the <?= e(strtoupper($selectedTranslation)); ?> text.</p>
                        </div>
                    </div>

                    <div class="card-grid card-grid-2 top-gap-sm">
                        <?php foreach ($themedSeries as $seriesItem): ?>
                            <?php $seriesVerse = $seriesItem['verse']; ?>
                            <article class="scripture-series-card">
                                <span class="pill"><?= e((string) $seriesItem['theme']); ?></span>
                                <strong><?= e(format_verse_reference($seriesVerse)); ?></strong>
                                <p class="scripture-text"><?= e(truncate_text((string) $seriesVerse['verse_text'], 180)); ?></p>
                                <div class="resource-action-row">
                                    <a class="button button-primary" href="<?= e(bible_reader_url([
                                        'q' => (string) $seriesItem['query'],
                                        'translation' => (string) $seriesVerse['translation'],
                                        'reader_mode' => $readerMode,
                                    ])); ?>">Open Passage</a>
                                    <a class="button button-secondary" href="<?= e(bible_reader_url([
                                        'translation' => (string) $seriesVerse['translation'],
                                        'book_id' => (int) $seriesVerse['book_id'],
                                        'chapter' => (int) $seriesVerse['chapter_number'],
                                        'reader_mode' => $readerMode,
                                    ])); ?>">Open Chapter</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($displayMode === 'search' && $searchResults !== []): ?>
                <div class="stack-list top-gap-sm">
                    <?php foreach ($searchResults as $verse): ?>
                        <?php include __DIR__ . '/includes/verse-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php elseif (($displayMode === 'chapter' || $displayMode === 'verse' || $displayMode === 'passage') && $browseVerses !== []) : ?>
                <article class="chapter-reader <?= $readerMode === 'paragraph' ? 'is-paragraph' : ''; ?> top-gap-sm" data-chapter-reader>
                    <?php foreach ($browseVerses as $verse): ?>
                        <?php
                        $verseBookmarkSet = $chapterBookmarks[(int) $verse['id']] ?? [];
                        $readerClasses = ['reader-verse'];
                        if ($displayMode !== 'verse' && $selectedVerseNumber === (int) $verse['verse_number']) {
                            $readerClasses[] = 'is-target';
                        }
                        $verseFocusUrl = bible_reader_url([
                            'translation' => $selectedTranslation,
                            'book_id' => $selectedBookId,
                            'chapter' => $selectedChapter,
                            'verse' => (int) $verse['verse_number'],
                            'reader_mode' => $readerMode,
                        ]) . '#verse-' . (int) $verse['verse_number'];
                        ?>
                        <div
                            class="<?= e(implode(' ', $readerClasses)); ?>"
                            id="verse-<?= e((string) $verse['verse_number']); ?>"
                            data-verse-card
                            data-verse-id="<?= e((string) $verse['id']); ?>"
                            data-verse-number="<?= e((string) $verse['verse_number']); ?>"
                            data-verse-reference="<?= e(format_verse_reference($verse)); ?>"
                            data-verse-text="<?= e((string) $verse['verse_text']); ?>"
                        >
                            <p class="reader-verse-copy">
                                <sup>
                                    <?php if ($readerMode === 'verse'): ?>
                                        <a class="reader-verse-number-link" href="<?= e($verseFocusUrl); ?>"><?= e((string) $verse['verse_number']); ?></a>
                                    <?php else: ?>
                                        <?= e((string) $verse['verse_number']); ?>
                                    <?php endif; ?>
                                </sup>
                                <span
                                    class="reader-verse-text"
                                    data-verse-id="<?= e((string) $verse['id']); ?>"
                                    data-verse-number="<?= e((string) $verse['verse_number']); ?>"
                                ><?= render_verse_text_with_highlights((string) $verse['verse_text'], $verseBookmarkSet); ?></span>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </article>

                <?php if ($selectedBook && $bookChapters !== []): ?>
                    <?php
                    $useVerseNav = $selectedVerseNumber > 0;
                    $prevNavUrl = $useVerseNav ? $previousVerseUrl : $previousChapterUrl;
                    $nextNavUrl = $useVerseNav ? $nextVerseUrl : $nextChapterUrl;
                    $prevNavLabel = $useVerseNav ? 'Previous verse' : 'Previous chapter';
                    $nextNavLabel = $useVerseNav ? 'Next verse' : 'Next chapter';
                    ?>
                    <div class="scripture-nav top-gap-sm">
                        <?php if ($prevNavUrl): ?>
                            <a class="button button-secondary scripture-nav-arrow" href="<?= e($prevNavUrl); ?>" aria-label="<?= e($prevNavLabel); ?>">&#8249;</a>
                        <?php else: ?>
                            <span class="scripture-nav-arrow-placeholder"></span>
                        <?php endif; ?>

                        <div class="scripture-nav-label">
                            <span><?= e((string) $selectedBook['name']); ?></span>
                            <strong>Chapter <?= e((string) $selectedChapter); ?><?= $useVerseNav ? ' : ' . e((string) $selectedVerseNumber) : ''; ?></strong>
                        </div>

                        <?php if ($nextNavUrl): ?>
                            <a class="button button-secondary scripture-nav-arrow" href="<?= e($nextNavUrl); ?>" aria-label="<?= e($nextNavLabel); ?>">&#8250;</a>
                        <?php else: ?>
                            <span class="scripture-nav-arrow-placeholder"></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="bookmark-popup" data-bookmark-popup hidden>
                    <div class="bookmark-popup-card">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow" data-popup-mode-label>Save Verse</p>
                                <h3 data-popup-reference>Select a verse</h3>
                                <p class="muted-copy" data-popup-preview>Click any verse or highlight text inside a verse.</p>
                            </div>
                            <button class="popup-close" type="button" data-popup-close aria-label="Close bookmark popup">Close</button>
                        </div>

                        <?php if (is_logged_in()): ?>
                            <form class="form-stack top-gap-sm" method="post" data-bookmark-popup-form>
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="save-bookmark">
                                <input type="hidden" name="verse_id" value="">
                                <input type="hidden" name="selected_text" value="">
                                <input type="hidden" name="selection_start" value="">
                                <input type="hidden" name="selection_end" value="">
                                <input type="hidden" name="range_start_verse_id" value="">
                                <input type="hidden" name="range_end_verse_id" value="">
                                <input type="hidden" name="range_start_offset" value="">
                                <input type="hidden" name="range_end_offset" value="">
                                <input type="hidden" name="return_query" value="<?= e($query); ?>">
                                <input type="hidden" name="return_translation" value="<?= e($selectedTranslation); ?>">
                                <input type="hidden" name="return_book_id" value="<?= e((string) $selectedBookId); ?>">
                                <input type="hidden" name="return_chapter" value="<?= e((string) $selectedChapter); ?>">
                                <input type="hidden" name="return_verse" value="<?= e((string) $selectedVerseNumber); ?>">
                                <input type="hidden" name="return_reader_mode" value="<?= e($readerMode); ?>">
                                <input type="hidden" name="highlight_color" value="neon-yellow">

                                <div class="color-picker-row" data-color-picker>
                                    <button class="color-swatch neon-yellow is-active" type="button" data-color="neon-yellow" aria-label="Neon yellow"></button>
                                    <button class="color-swatch neon-green" type="button" data-color="neon-green" aria-label="Neon green"></button>
                                    <button class="color-swatch neon-blue" type="button" data-color="neon-blue" aria-label="Neon blue"></button>
                                    <button class="color-swatch neon-orange" type="button" data-color="neon-orange" aria-label="Neon orange"></button>
                                    <button class="color-swatch neon-pink" type="button" data-color="neon-pink" aria-label="Neon pink"></button>
                                </div>

                                <label>
                                    <span>Bookmark tag</span>
                                    <input type="text" name="tag" placeholder="Prayer, promise, memory verse">
                                </label>

                                <label>
                                    <span>Note</span>
                                    <div class="inline-actions top-gap-sm" data-voice-compose>
                                        <button class="button button-secondary voice-search-button" type="button" data-voice-compose-start aria-label="Speak your bookmark note">
                                            <svg class="voice-search-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <path d="M12 4a3 3 0 0 1 3 3v5a3 3 0 0 1-6 0V7a3 3 0 0 1 3-3Z" />
                                                <path d="M19 11a7 7 0 0 1-14 0" />
                                                <path d="M12 18v3" />
                                                <path d="M9 21h6" />
                                            </svg>
                                        </button>
                                        <button class="button button-secondary" type="button" data-voice-compose-stop hidden>Stop</button>
                                        <span class="muted-copy" data-voice-compose-status>Share what this verse is speaking to your heart.</span>
                                    </div>
                                    <textarea name="note" rows="3" placeholder="Why are you saving this?"></textarea>
                                </label>

                                <div class="inline-actions">
                                    <button class="button button-primary" type="submit">Save Bookmark</button>
                                    <button class="button button-secondary" type="button" data-popup-clear>Clear</button>
                                    <a class="button button-secondary" href="#" data-popup-note-link>Add Note</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="form-stack top-gap-sm">
                                <p class="muted-copy">Sign in to save bookmarks and highlights from the Bible reader.</p>
                                <div class="inline-actions">
                                    <a class="button button-primary" href="<?= e(app_url('login.php')); ?>">Sign In</a>
                                    <a class="button button-secondary" href="<?= e(app_url('register.php')); ?>">Create Account</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($scriptureAnalysis !== null): ?>
                <section class="scripture-analysis-panel top-gap">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Study Tools</p>
                            <h3><?= e($analysisTitle); ?></h3>
                            <p class="muted-copy"><?= e($analysisIntro); ?></p>
                        </div>
                    </div>

                    <div class="study-summary-bar">
                        <span class="mini-card study-summary-pill"><?= e((string) $scriptureAnalysis['verse_count']); ?> verses</span>
                        <span class="mini-card study-summary-pill"><?= e((string) $scriptureAnalysis['word_count']); ?> words</span>
                        <span class="mini-card study-summary-pill"><?= e((string) $scriptureAnalysis['focus_term_count']); ?> focus terms</span>
                    </div>

                    <div class="card-grid card-grid-2 scripture-analysis-grid">
                        <article class="dashboard-card scripture-analysis-card">
                            <div>
                                <h3>Concordance Focus</h3>
                                <p class="muted-copy">Open repeated words from this result set as fresh Bible searches.</p>
                            </div>

                            <?php if ($scriptureAnalysis['top_terms'] !== []): ?>
                                <div class="bible-chip-row">
                                    <?php foreach ($scriptureAnalysis['top_terms'] as $term): ?>
                                        <a class="filter-chip" href="<?= e(bible_reader_url([
                                            'q' => (string) $term['term'],
                                            'translation' => $selectedTranslation,
                                            'reader_mode' => $readerMode,
                                        ])); ?>">
                                            <?= e(mb_convert_case((string) $term['term'], MB_CASE_TITLE, 'UTF-8')); ?> · <?= e((string) $term['count']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="muted-copy">No strong concordance terms were detected in this view yet.</p>
                            <?php endif; ?>
                        </article>

                        <article class="dashboard-card scripture-analysis-card">
                            <div>
                                <h3>Related Study Items</h3>
                                <p class="muted-copy">Use these next-step searches to explore the same theme in more places.</p>
                            </div>

                            <div class="analysis-link-list">
                                <?php foreach ($scriptureAnalysis['related_items'] as $item): ?>
                                    <a class="analysis-link-card" href="<?= e(bible_reader_url([
                                        'q' => (string) $item['term'],
                                        'translation' => $selectedTranslation,
                                        'reader_mode' => $readerMode,
                                    ])); ?>">
                                        <strong><?= e((string) $item['label']); ?></strong>
                                        <span class="muted-copy"><?= e((string) $item['match_count']); ?> verses in this view mention it</span>
                                    </a>
                                <?php endforeach; ?>

                                <?php if ($wholeChapterUrl): ?>
                                    <a class="analysis-link-card" href="<?= e($wholeChapterUrl); ?>">
                                        <strong>Chapter Context</strong>
                                        <span class="muted-copy">Step back into the full chapter around this verse.</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($sharePayloadJson !== null): ?>
                <div class="scripture-support-stack top-gap">
                    <?php if ($sharePayloadJson !== null): ?>
                        <div class="scripture-bottom-actions">
                            <button class="share-composer-fab" type="button" data-share-composer-toggle aria-expanded="false" aria-controls="share-composer-panel" aria-label="Open share composer">
                                <span class="share-composer-fab-copy">
                                    <span class="share-composer-fab-label">Share Post</span>
                                    <span class="share-composer-fab-meta">Create a verse or passage post</span>
                                </span>
                                <span class="share-composer-fab-icon" aria-hidden="true">&#10138;</span>
                            </button>
                        </div>

                        <div class="panel share-composer-panel" id="share-composer-panel" data-share-composer hidden>
                            <script type="application/json" data-share-payload><?= $sharePayloadJson; ?></script>

                            <div class="panel-heading">
                                <div>
                                    <p class="eyebrow">Share Composer</p>
                                    <h3>Public post templates for Scripture</h3>
                                    <p class="muted-copy">Tune the layout, style, and caption before posting the Good News.</p>
                                </div>
                                <button class="button button-secondary" type="button" data-share-composer-close>Close</button>
                            </div>

                            <div class="share-composer-grid top-gap-sm">
                                <form class="form-stack share-composer-form" data-share-composer-form>
                                    <div class="two-column">
                                        <label>
                                            <span>Template</span>
                                            <select name="template" data-share-template>
                                                <option value="story">Vertical Phone Story</option>
                                                <option value="square">Square 1:1 Post</option>
                                            </select>
                                        </label>

                                        <label>
                                            <span>Theme</span>
                                            <select name="theme" data-share-theme>
                                                <option value="good-news-bible" selected>Good News Bible</option>
                                                <option value="slate-glow">Slate Glow</option>
                                                <option value="earth-canvas">Earth Canvas</option>
                                                <option value="light-sermon">Light Sermon</option>
                                                <option value="midnight-gospel">Midnight Gospel</option>
                                            </select>
                                        </label>
                                    </div>

                                    <div class="two-column">
                                        <label>
                                            <span>Font</span>
                                            <select name="font" data-share-font>
                                                <option value="editorial">Editorial</option>
                                                <option value="modern">Modern</option>
                                                <option value="classic">Classic Serif</option>
                                            </select>
                                        </label>

                                        <label>
                                            <span>Branding</span>
                                            <select name="branding" data-share-branding>
                                                <option value="good-news">Good News Bible</option>
                                                <option value="none">No branding</option>
                                            </select>
                                        </label>
                                    </div>

                                    <label>
                                        <span>Headline</span>
                                        <input type="text" name="headline" value="Share the Good News" data-share-headline>
                                    </label>

                                    <label>
                                        <span>Footer note</span>
                                        <input type="text" name="footer" value="Faith for today" data-share-footer>
                                    </label>

                                    <label>
                                        <span>Caption</span>
                                        <textarea name="caption" rows="5" data-share-caption></textarea>
                                    </label>

                                    <div class="inline-actions">
                                        <button class="button button-primary" type="button" data-share-download>Download PNG</button>
                                        <button class="button button-secondary" type="button" data-share-native>Share</button>
                                        <button class="button button-secondary" type="button" data-share-copy>Copy Caption</button>
                                        <button class="button button-secondary" type="button" data-share-randomize>New Background</button>
                                    </div>

                                    <p class="muted-copy" data-share-status>Portrait and square templates are ready for public posting.</p>
                                </form>

                                <div class="share-preview-column">
                                    <div class="share-preview-shell" data-share-preview-shell data-template="story">
                                        <div class="share-preview-card share-theme-good-news-bible share-font-editorial" data-share-preview-card>
                                            <div class="share-preview-overlay"></div>
                                            <div class="share-preview-inner">
                                                <p class="share-preview-kicker" data-share-preview-kicker>Share the Good News</p>
                                                <div class="share-preview-scripture">
                                                    <p class="share-preview-reference" data-share-preview-reference></p>
                                                    <blockquote class="share-preview-text" data-share-preview-text></blockquote>
                                                </div>
                                                <div class="share-preview-meta">
                                                    <span class="share-preview-footer" data-share-preview-footer>Faith for today</span>
                                                    <span class="share-preview-brand" data-share-preview-brand>Good News Bible</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <canvas class="share-render-canvas" data-share-canvas width="1080" height="1920" hidden></canvas>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

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

function bible_theme_queries(): array
{
    return [
        'Hope' => 'Romans 15:13',
        'Prayer' => 'Philippians 4:6-7',
        'Faith' => 'Hebrews 11:1',
        'Wisdom' => 'James 1:5',
        'Peace' => 'Isaiah 26:3',
        'Courage' => 'Joshua 1:9',
        'Forgiveness' => 'forgiveness',
        'Identity' => 'identity',
        'Resurrection' => 'resurrection',
    ];
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
$themeQueries = bible_theme_queries();
$recentSearches = recent_bible_searches();
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

        if ($action === 'save-section' && $selectedText === '') {
            set_flash('Select part of a verse before saving a highlight.', 'warning');
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
        $recentSearches = recent_bible_searches();
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

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
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

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="panel bible-control-panel">
            <form class="form-stack bible-search-form" method="get" data-translation-switch-form>
                <div class="search-row search-row-compact" data-voice-search>
                    <input type="search" name="q" value="<?= e($query); ?>" placeholder="Quick search: John 3:28 or grace" data-voice-search-input>
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
                    <button class="button button-secondary voice-search-button" type="button" data-voice-search-stop hidden>Stop</button>
                    <button class="button button-primary" type="submit">Search</button>
                </div>
                <p class="muted-copy" data-voice-search-status>Speak a reference or keyword search.</p>
            </form>

            <div class="bible-search-tools">
                <div class="bible-chip-row">
                    <?php foreach ($themeQueries as $themeLabel => $themeQuery): ?>
                        <a class="filter-chip" href="<?= e(bible_reader_url([
                            'q' => $themeQuery,
                            'translation' => $selectedTranslation,
                            'reader_mode' => $readerMode,
                        ])); ?>"><?= e($themeLabel); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <form class="form-stack top-gap" method="get" data-reader-nav>
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

        <div class="panel scripture-panel top-gap">
            <div class="scripture-heading">
                <div>
                    <p class="eyebrow"><?= e(strtoupper($selectedTranslation)); ?></p>
                    <h2><?= e($searchHeading); ?></h2>
                </div>
                <div class="showcase-actions">
                    <span class="mini-card"><?= e(match ($displayMode) {
                        'chapter', 'verse' => 'Chapter Reader',
                        'passage' => 'Passage View',
                        'search' => 'Search Results',
                        default => 'Reader',
                    }); ?></span>
                    <?php if ($selectedBook): ?>
                        <span class="mini-card"><?= e((string) $selectedBook['abbreviation']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selectedBook && $bookChapters !== []): ?>
                <div class="reader-nav-bar top-gap-sm">
                    <?php if (($displayMode === 'chapter' || $displayMode === 'verse' || $displayMode === 'passage') && $selectedChapter > 0): ?>
                        <div class="reader-mode-switch">
                            <a class="mini-card <?= $readerMode === 'verse' ? 'is-active' : ''; ?>" href="<?= e(bible_reader_url([
                                'q' => $query !== '' ? $query : null,
                                'translation' => $selectedTranslation,
                                'book_id' => $selectedBookId ?: null,
                                'chapter' => $selectedChapter ?: null,
                                'verse' => $selectedVerseNumber ?: null,
                                'reader_mode' => 'verse',
                            ])); ?>">Verse View</a>
                            <a class="mini-card <?= $readerMode === 'paragraph' ? 'is-active' : ''; ?>" href="<?= e(bible_reader_url([
                                'q' => $query !== '' ? $query : null,
                                'translation' => $selectedTranslation,
                                'book_id' => $selectedBookId ?: null,
                                'chapter' => $selectedChapter ?: null,
                                'verse' => $selectedVerseNumber ?: null,
                                'reader_mode' => 'paragraph',
                            ])); ?>">Paragraph View</a>
                        </div>
                    <?php endif; ?>

                    <div class="reader-nav-combined">
                        <?php if (($displayMode === 'chapter' || $displayMode === 'verse' || $displayMode === 'passage') && $selectedChapter > 0): ?>
                            <div class="reader-nav-actions">
                                <?php if ($previousVerseUrl): ?>
                                    <a class="button button-secondary reader-nav-button" href="<?= e($previousVerseUrl); ?>" aria-label="Previous verse">
                                        <span class="reader-nav-icon" aria-hidden="true">&#8249;</span>
                                        <span class="reader-nav-label">Verse</span>
                                    </a>
                                <?php endif; ?>
                                <?php if ($wholeChapterUrl): ?>
                                    <a class="button button-secondary reader-nav-button" href="<?= e($wholeChapterUrl); ?>" aria-label="Whole chapter">
                                        <span class="reader-nav-icon" aria-hidden="true">&#9638;</span>
                                        <span class="reader-nav-label">Chapter</span>
                                    </a>
                                <?php endif; ?>
                                <?php if ($nextVerseUrl): ?>
                                    <a class="button button-secondary reader-nav-button" href="<?= e($nextVerseUrl); ?>" aria-label="Next verse">
                                        <span class="reader-nav-label">Verse</span>
                                        <span class="reader-nav-icon" aria-hidden="true">&#8250;</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form class="chapter-jump-form" method="get">
                            <input type="hidden" name="translation" value="<?= e($selectedTranslation); ?>">
                            <input type="hidden" name="book_id" value="<?= e((string) $selectedBookId); ?>">
                            <input type="hidden" name="reader_mode" value="<?= e($readerMode); ?>">
                            <div class="chapter-jump-card">
                                <div class="chapter-jump-header">
                                    <strong class="chapter-jump-title"><?= e((string) $selectedBook['name']); ?></strong>
                                    <?php if ($bookOverviewUrl): ?>
                                        <a class="mini-card chapter-book-link" href="<?= e($bookOverviewUrl); ?>" aria-label="Open <?= e((string) $selectedBook['name']); ?> overview">Book</a>
                                    <?php endif; ?>
                                </div>
                                <div class="chapter-jump-controls">
                                    <?php if ($previousChapterUrl): ?>
                                        <a class="button button-secondary chapter-step-button" href="<?= e($previousChapterUrl); ?>" aria-label="Previous chapter">&#8249;</a>
                                    <?php endif; ?>
                                    <label class="chapter-jump-label">
                                        <select name="chapter">
                                            <?php foreach ($bookChapters as $chapter): ?>
                                                <option value="<?= e((string) $chapter['chapter_number']); ?>" <?= $selectedChapter === (int) $chapter['chapter_number'] ? 'selected' : ''; ?>>
                                                    Chapter <?= e((string) $chapter['chapter_number']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label class="chapter-jump-label">
                                        <select name="verse">
                                            <option value="">Whole</option>
                                            <?php foreach ($verseOptions as $verseOption): ?>
                                                <option value="<?= e((string) $verseOption['number']); ?>" <?= $selectedVerseNumber === $verseOption['number'] ? 'selected' : ''; ?>>
                                                    <?= e((string) $verseOption['number']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <button class="button button-secondary chapter-step-button chapter-go-button" type="submit" aria-label="Go to selected chapter and verse">&#10148;</button>
                                    <?php if ($nextChapterUrl): ?>
                                        <a class="button button-secondary chapter-step-button" href="<?= e($nextChapterUrl); ?>" aria-label="Next chapter">&#8250;</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>

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
                        if ($selectedVerseNumber === (int) $verse['verse_number']) {
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

                <?php if (is_logged_in()): ?>
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

                            <form class="form-stack top-gap-sm" method="post" data-bookmark-popup-form>
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="save-bookmark">
                                <input type="hidden" name="verse_id" value="">
                                <input type="hidden" name="selected_text" value="">
                                <input type="hidden" name="selection_start" value="">
                                <input type="hidden" name="selection_end" value="">
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
                                        <span class="muted-copy" data-voice-compose-status>Speak to add a bookmark note.</span>
                                    </div>
                                    <textarea name="note" rows="3" placeholder="Why are you saving this?"></textarea>
                                </label>

                                <div class="inline-actions">
                                    <button class="button button-primary" type="submit">Save Bookmark</button>
                                    <button class="button button-secondary" type="button" data-popup-clear>Clear</button>
                                    <a class="button button-secondary" href="#" data-popup-note-link>Add Note</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

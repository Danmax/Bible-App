<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Bible Reader';
$activePage = 'bible';
$user = is_logged_in() ? refresh_current_user() : null;
$pageError = null;
$searchMessage = null;
$query = trim($_GET['q'] ?? '');
$selectedTranslation = trim($_GET['translation'] ?? 'KJV');
$selectedBookId = (int) ($_GET['book_id'] ?? 0);
$selectedChapter = (int) ($_GET['chapter'] ?? 0);
$selectedVerseNumber = (int) ($_GET['verse'] ?? 0);
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
$favoriteBookmarks = [];
$translationAvailability = [];
$displayMode = 'catalog';

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
            'translation' => trim($_POST['return_translation'] ?? 'KJV'),
            'book_id' => (int) ($_POST['return_book_id'] ?? 0) ?: null,
            'chapter' => (int) ($_POST['return_chapter'] ?? 0) ?: null,
            'verse' => (int) ($_POST['return_verse'] ?? 0) ?: null,
        ], static fn($value) => $value !== null && $value !== ''));

        redirect('bible.php' . ($redirectQuery === '' ? '' : '?' . $redirectQuery));
    } catch (Throwable $exception) {
        $pageError = 'That bookmark or highlight could not be saved because the database is unavailable.';
    }
}

try {
    $translations = fetch_available_translations();

    if (!in_array($selectedTranslation, $translations, true)) {
        $selectedTranslation = 'KJV';
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

    if ($selectedBookId > 0) {
        $selectedBook = fetch_book_by_id($selectedBookId);
        if ($selectedBook) {
            $bookChapters = fetch_book_chapters($selectedBookId, $selectedTranslation);
        }
    }

    if ($query !== '') {
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
                $browseVerses = array_values(
                    array_filter(
                        $chapterVerseSet,
                        static fn(array $verse): bool => (int) $verse['verse_number'] === $selectedVerseNumber
                    )
                );

                if ($browseVerses !== []) {
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

    if (is_logged_in()) {
        $favoriteBookmarks = fetch_favorite_bookmarks((int) $user['id']);
    }
} catch (Throwable $exception) {
    $pageError = 'Scripture content could not be loaded because the database is unavailable.';
}

$quickLinks = [
    ['label' => 'Genesis 1', 'query' => 'Genesis 1'],
    ['label' => 'Psalm 23', 'query' => 'Psalms 23'],
    ['label' => 'John 3:16', 'query' => 'John 3:16'],
];

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
        <div class="section-heading">
            <p class="eyebrow">Bible Reader</p>
            <h1>Read chapters, highlight text, and save favorites</h1>
            <p>Dropdowns load the reader automatically, and bookmarks appear right below the search tools.</p>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="panel bible-control-panel">
            <form class="form-stack" method="get">
                <div class="search-row">
                    <input type="search" name="q" value="<?= e($query); ?>" placeholder="Search by verse, keyword, or topic">
                    <select name="translation" aria-label="Translation">
                        <?php foreach ($translations as $translation): ?>
                            <option value="<?= e($translation); ?>" <?= $selectedTranslation === $translation ? 'selected' : ''; ?>>
                                <?= e($translation); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="button button-primary" type="submit">Search</button>
                </div>
            </form>

            <div class="inline-actions top-gap-sm">
                <?php foreach ($quickLinks as $quickLink): ?>
                    <a class="filter-chip" href="<?= e(app_url('bible.php?q=' . urlencode($quickLink['query']) . '&translation=' . urlencode($selectedTranslation))); ?>">
                        <?= e($quickLink['label']); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($query !== ''): ?>
                <div class="translation-switch-row top-gap-sm">
                    <strong>Versions</strong>
                    <div class="inline-actions">
                        <?php foreach ($translations as $translation): ?>
                            <?php
                            $switchClasses = ['filter-chip'];
                            if ($selectedTranslation === $translation) {
                                $switchClasses[] = 'is-active';
                            }
                            if (!($translationAvailability[$translation] ?? false)) {
                                $switchClasses[] = 'is-muted';
                            }
                            ?>
                            <a class="<?= e(implode(' ', $switchClasses)); ?>" href="<?= e(app_url('bible.php?q=' . urlencode($query) . '&translation=' . urlencode($translation))); ?>">
                                <?= e($translation); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!$selectedTranslationHasData): ?>
                        <p class="muted-copy">No verse data is loaded yet for <?= e($selectedTranslation); ?>. KJV is currently the only populated translation.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form class="form-stack top-gap" method="get" data-reader-nav>
                <div class="reader-select-row">
                    <input type="hidden" name="translation" value="<?= e($selectedTranslation); ?>">

                    <label>
                        <span>Book</span>
                        <select name="book_id" data-reader-select="book">
                            <option value="">Select book</option>
                            <?php foreach ($bookCatalog as $book): ?>
                                <option value="<?= e((string) $book['id']); ?>" <?= $selectedBookId === (int) $book['id'] ? 'selected' : ''; ?>>
                                    <?= e((string) $book['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Chapter</span>
                        <select name="chapter" data-reader-select="chapter">
                            <option value="">Select chapter</option>
                            <?php foreach ($bookChapters as $chapter): ?>
                                <option value="<?= e((string) $chapter['chapter_number']); ?>" <?= $selectedChapter === (int) $chapter['chapter_number'] ? 'selected' : ''; ?>>
                                    <?= e((string) $chapter['chapter_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Verse</span>
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

            <?php if (is_logged_in() && $favoriteBookmarks !== []): ?>
                <div class="favorites-strip top-gap-sm">
                    <strong>Favorites</strong>
                    <div class="inline-actions">
                        <?php foreach ($favoriteBookmarks as $favoriteBookmark): ?>
                            <a class="favorite-chip <?= e(highlight_class((string) ($favoriteBookmark['highlight_color'] ?? 'neon-yellow'))); ?>" href="<?= e(app_url('bible.php?translation=' . urlencode((string) $favoriteBookmark['translation']) . '&book_id=' . $favoriteBookmark['book_id'] . '&chapter=' . $favoriteBookmark['chapter_number'] . '&verse=' . $favoriteBookmark['verse_number'])); ?>">
                                <?= e(!empty($favoriteBookmark['selected_text']) ? truncate_text((string) $favoriteBookmark['selected_text'], 32) : format_verse_reference($favoriteBookmark)); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel scripture-panel top-gap">
            <div class="scripture-heading">
                <div>
                    <p class="eyebrow"><?= e(strtoupper($selectedTranslation)); ?></p>
                    <h2><?= e($searchHeading); ?></h2>
                </div>
                <div class="showcase-actions">
                    <span class="mini-card"><?= e(($displayMode === 'chapter' || $displayMode === 'verse') ? 'Chapter Reader' : ($displayMode === 'search' ? 'Search Results' : 'Reader')); ?></span>
                    <?php if ($selectedBook): ?>
                        <span class="mini-card"><?= e((string) $selectedBook['abbreviation']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selectedBook && $bookChapters !== []): ?>
                <div class="chapter-grid top-gap-sm">
                    <?php foreach ($bookChapters as $chapter): ?>
                        <a class="chapter-link <?= $selectedChapter === (int) $chapter['chapter_number'] ? 'is-active' : ''; ?>" href="<?= e(app_url('bible.php?translation=' . urlencode($selectedTranslation) . '&book_id=' . $selectedBookId . '&chapter=' . $chapter['chapter_number'])); ?>">
                            <?= e((string) $chapter['chapter_number']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($searchMessage): ?>
                <p class="empty-state top-gap-sm"><?= e($searchMessage); ?></p>
            <?php endif; ?>

            <?php if ($displayMode === 'search' && $searchResults !== []): ?>
                <div class="stack-list top-gap-sm">
                    <?php foreach ($searchResults as $verse): ?>
                        <?php include __DIR__ . '/includes/verse-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php elseif (($displayMode === 'chapter' || $displayMode === 'verse') && $browseVerses !== []) : ?>
                <article class="chapter-reader top-gap-sm" data-chapter-reader>
                    <?php foreach ($browseVerses as $verse): ?>
                        <?php
                        $verseBookmarkSet = $chapterBookmarks[(int) $verse['id']] ?? [];
                        $readerClasses = ['reader-verse'];
                        if ($selectedVerseNumber === (int) $verse['verse_number']) {
                            $readerClasses[] = 'is-target';
                        }
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
                                <sup><?= e((string) $verse['verse_number']); ?></sup>
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
                                <input type="hidden" name="return_translation" value="<?= e($selectedTranslation); ?>">
                                <input type="hidden" name="return_book_id" value="<?= e((string) $selectedBookId); ?>">
                                <input type="hidden" name="return_chapter" value="<?= e((string) $selectedChapter); ?>">
                                <input type="hidden" name="return_verse" value="<?= e((string) $selectedVerseNumber); ?>">
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

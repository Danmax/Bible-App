<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$pageTitle = 'Bible Reader';
$activePage = 'bible';
$user = is_logged_in() ? refresh_current_user() : null;
$pageError = null;
$searchMessage = null;
$query = trim($_GET['q'] ?? '');
$selectedTranslation = trim($_GET['translation'] ?? 'KJV');
$selectedBookId = (int) ($_GET['book_id'] ?? 0);
$selectedChapter = (int) ($_GET['chapter'] ?? 0);
$translations = supported_translations();
$searchResults = [];
$searchHeading = 'Bible Library';
$selectedTranslationHasData = true;
$bookCatalog = [];
$selectedBook = null;
$bookChapters = [];
$browseVerses = [];
$displayMode = 'catalog';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!is_logged_in()) {
        set_flash('Sign in to save bookmarks and notes.', 'warning');
        redirect('login.php');
    }

    try {
        save_bookmark_record(
            (int) $user['id'],
            (int) ($_POST['verse_id'] ?? 0),
            trim($_POST['tag'] ?? ''),
            trim($_POST['note'] ?? '')
        );
        set_flash('Verse saved to your bookmarks.', 'success');

        $redirectQuery = http_build_query(array_filter([
            'q' => trim($_POST['return_query'] ?? ''),
            'translation' => trim($_POST['return_translation'] ?? 'KJV'),
            'book_id' => (int) ($_POST['return_book_id'] ?? 0) ?: null,
            'chapter' => (int) ($_POST['return_chapter'] ?? 0) ?: null,
        ], static fn($value) => $value !== null && $value !== ''));

        redirect('bible.php' . ($redirectQuery === '' ? '' : '?' . $redirectQuery));
    } catch (Throwable $exception) {
        $pageError = 'That verse could not be saved because the database is unavailable.';
    }
}

try {
    $translations = fetch_available_translations();

    if (!in_array($selectedTranslation, $translations, true)) {
        $selectedTranslation = 'KJV';
    }

    $bookCatalog = fetch_book_catalog($selectedTranslation);
    $selectedTranslationHasData = count_records(
        'SELECT COUNT(*) FROM verses WHERE translation = :translation',
        ['translation' => $selectedTranslation]
    ) > 0;

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
                ? 'No verses matched that search. Try a reference like John 3:16 or a keyword like grace.'
                : 'No verses are loaded yet for this translation. Import Bible text for ' . $selectedTranslation . ' first.';
        }
    } elseif ($selectedBook && $selectedChapter > 0) {
        $browseVerses = fetch_chapter_verses($selectedBookId, $selectedChapter, $selectedTranslation);
        $searchHeading = sprintf('%s %d (%s)', $selectedBook['name'], $selectedChapter, $selectedTranslation);
        $displayMode = 'chapter';

        if ($browseVerses === []) {
            $searchMessage = 'This chapter has no verses loaded for ' . $selectedTranslation . '.';
        }
    } elseif ($selectedBook) {
        $displayMode = 'book';
        $searchHeading = $selectedBook['name'] . ' (' . $selectedTranslation . ')';
        $searchMessage = $bookChapters === []
            ? 'This book has no chapter data loaded yet.'
            : 'Select a chapter to begin reading.';
    } else {
        $displayMode = 'catalog';
        $searchHeading = 'Bible Library';
        $searchMessage = $bookCatalog === []
            ? 'No books are loaded yet. Import Bible data to enable search and navigation.'
            : 'Choose a book below or search by reference.';
    }
} catch (Throwable $exception) {
    $pageError = 'Scripture content could not be loaded because the database is unavailable.';
}

$quickLinks = [
    ['label' => 'Genesis 1', 'query' => 'Genesis 1'],
    ['label' => 'Psalm 23', 'query' => 'Psalms 23'],
    ['label' => 'John 3:16', 'query' => 'John 3:16'],
];

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Bible Reader</p>
            <h1>Read, search, and browse Scripture</h1>
            <p>Search by verse reference or keyword, then browse books and chapters directly from the database.</p>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="panel">
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
        </div>

        <div class="two-column top-gap">
            <aside class="panel bible-sidebar">
                <div class="panel-heading">
                    <div>
                        <h2>Books</h2>
                        <p class="muted-copy">Loaded for <?= e($selectedTranslation); ?></p>
                    </div>
                    <?php if ($selectedBook): ?>
                        <a class="button button-secondary" href="<?= e(app_url('bible.php?translation=' . urlencode($selectedTranslation))); ?>">All Books</a>
                    <?php endif; ?>
                </div>

                <?php if ($bookCatalog === []): ?>
                    <p class="empty-state">No book catalog exists yet for this database.</p>
                <?php else: ?>
                    <div class="book-grid">
                        <?php foreach ($bookCatalog as $book): ?>
                            <a class="book-link <?= $selectedBookId === (int) $book['id'] ? 'is-active' : ''; ?>" href="<?= e(app_url('bible.php?translation=' . urlencode($selectedTranslation) . '&book_id=' . $book['id'])); ?>">
                                <strong><?= e((string) $book['abbreviation']); ?></strong>
                                <span><?= e((string) $book['name']); ?></span>
                                <small><?= e((string) $book['chapter_count']); ?> chapters</small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>

            <div class="panel scripture-panel">
                <div class="scripture-heading">
                    <div>
                        <p class="eyebrow"><?= e(strtoupper($selectedTranslation)); ?></p>
                        <h2><?= e($searchHeading); ?></h2>
                    </div>
                    <div class="showcase-actions">
                        <span class="mini-card"><?= e($displayMode === 'search' ? 'Search Results' : 'Reader'); ?></span>
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
                            <?php include dirname(__DIR__) . '/includes/verse-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($displayMode === 'chapter' && $browseVerses !== []): ?>
                    <div class="stack-list top-gap-sm">
                        <?php foreach ($browseVerses as $verse): ?>
                            <?php include dirname(__DIR__) . '/includes/verse-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

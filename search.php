<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/search_topics.php';

$pageTitle = 'Search Scripture';
$pageDescription = 'Find Bible passages, remembered words, and subjects. Search Scripture without an account.';
$activePage = 'search';
$pageScripts = ['assets/js/search.js'];
$query = trim((string) ($_GET['q'] ?? ''));
$translation = strtoupper(trim((string) ($_GET['translation'] ?? APP_DEFAULT_TRANSLATION)));
$options = scripture_search_options($_GET);
$sort = ($_GET['sort'] ?? '') === 'canonical' ? 'canonical' : 'relevance';
$view = ($_GET['view'] ?? '') === 'topics' ? 'topics' : 'text';
$error = null;
$books = [];
$translations = [];
$result = ['results' => [], 'total' => 0, 'page' => 1, 'page_count' => 1];
$topics = $query === '' ? scripture_search_topic_guides() : scripture_matching_topic_guides($query);
$reference = null;

function scripture_search_url(array $params): string
{
    return app_url('search.php?' . http_build_query(array_filter($params, static fn($value): bool => $value !== null && $value !== '')));
}

$params = ['q' => $query, 'translation' => $translation, 'match' => $options['match'], 'scope' => $options['scope'], 'book_id' => $options['book_id'] ?: null, 'sort' => $sort, 'view' => $view];
try {
    $books = fetch_books();
    $translations = fetch_available_translations();
    if (!in_array($translation, $translations, true)) {
        $translation = in_array(APP_DEFAULT_TRANSLATION, $translations, true) ? APP_DEFAULT_TRANSLATION : ($translations[0] ?? APP_DEFAULT_TRANSLATION);
        $params['translation'] = $translation;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        require_login();
        if (($_POST['action'] ?? '') !== 'save-verse' || fetch_verse_by_id((int) ($_POST['verse_id'] ?? 0)) === null) {
            throw new InvalidArgumentException('Choose an available verse to save.');
        }
        if (fetch_full_verse_bookmark((int) current_user()['id'], (int) $_POST['verse_id']) === null) {
            save_bookmark_record((int) current_user()['id'], (int) $_POST['verse_id'], '', '');
        }
        set_flash('Verse saved to your Library.', 'success');
        header('Location: ' . scripture_search_url($params + ['page' => $options['page']]));
        exit;
    }
    if ($query !== '') {
        if (mb_strlen($query) > 200) {
            throw new InvalidArgumentException('Keep your search to 200 characters or fewer.');
        }
        $reference = parse_reference_query($query, $books);
        if ($reference !== null && (int) $reference['end_chapter'] === (int) $reference['chapter']) {
            redirect('bible.php?' . http_build_query(['q' => $query, 'translation' => $translation]));
        }
        if ($view === 'text') {
            $result = search_scripture($query, $translation, $sort, $options);
        }
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    error_log('Scripture search unavailable: ' . get_class($exception));
    $error = 'Search could not load right now. Your words are still here; please try again.';
}
$external = uses_external_translation($translation);
require_once __DIR__ . '/includes/header.php';
?>
<section class="section search-page <?= $query !== '' ? 'has-query' : ''; ?>" data-scripture-search>
    <div class="container search-container">
        <div class="section-heading">
            <p class="eyebrow">The Word, within reach</p>
            <h1><?= $query !== '' ? 'Search Scripture' : 'What are you looking for?'; ?></h1>
            <p>Open a passage, find words you remember, or explore a subject. No account needed.</p>
        </div>
        <form class="panel search-workspace" method="get" role="search">
            <label for="scripture-query">Passage, words, or a subject</label>
            <div class="word-search-row">
                <input id="scripture-query" type="search" name="q" value="<?= e($query); ?>" placeholder="Jn 3:16, “be still”, or worry" maxlength="200" list="scripture-suggestions" autocomplete="off" required data-search-input>
                <button class="button button-primary" type="submit">Search</button>
            </div>
            <datalist id="scripture-suggestions">
                <?php foreach ($books as $book): ?><option value="<?= e((string) $book['name'] . ' 1'); ?>"><?php endforeach; ?>
                <?php foreach (scripture_search_topic_guides() as $topic): ?><option value="<?= e($topic['aliases'][0]); ?>"><?php endforeach; ?>
            </datalist>
            <div class="search-primary-options">
                <label>Translation<select name="translation"><?php foreach ($translations ?: [$translation] as $edition): ?><option value="<?= e($edition); ?>" <?= $edition === $translation ? 'selected' : ''; ?>><?= e($edition); ?></option><?php endforeach; ?></select></label>
                <label>Show<select name="view"><option value="text" <?= $view === 'text' ? 'selected' : ''; ?>>Text matches</option><option value="topics" <?= $view === 'topics' ? 'selected' : ''; ?>>Topic guides</option></select></label>
                <a href="<?= e(app_url('bible.php?translation=' . urlencode($translation))); ?>">Browse Bible books →</a>
            </div>
            <?php if (!$external): ?>
                <details class="search-filter-panel" <?= $options['scope'] !== 'all' || $options['book_id'] > 0 || $options['match'] !== 'all' || $sort !== 'relevance' ? 'open' : ''; ?>>
                    <summary>Search filters · <?= e(match ($options['match']) { 'any' => 'Any word', 'phrase' => 'Exact phrase', 'related' => 'Related wording', default => 'All words' }); ?> · <?= e($options['scope'] === 'all' ? 'All Bible' : ($options['scope'] === 'old' ? 'Old Testament' : 'New Testament')); ?><?= $options['book_id'] > 0 ? ' · Book selected' : ''; ?></summary>
                    <div class="search-filter-grid">
                        <label>Match<select name="match"><?php foreach (['all' => 'All words', 'any' => 'Any word', 'phrase' => 'Exact phrase', 'related' => 'Include related wording'] as $key => $label): ?><option value="<?= e($key); ?>" <?= $options['match'] === $key ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></label>
                        <label>Where<select name="scope"><?php foreach (['all' => 'All Bible', 'old' => 'Old Testament', 'new' => 'New Testament'] as $key => $label): ?><option value="<?= e($key); ?>" <?= $options['scope'] === $key ? 'selected' : ''; ?>><?= e($label); ?></option><?php endforeach; ?></select></label>
                        <label>Book<select name="book_id"><option value="">All books</option><?php foreach ($books as $book): ?><option value="<?= e((string) $book['id']); ?>" <?= $options['book_id'] === (int) $book['id'] ? 'selected' : ''; ?>><?= e((string) $book['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Sort<select name="sort"><option value="relevance" <?= $sort === 'relevance' ? 'selected' : ''; ?>>Most relevant</option><option value="canonical" <?= $sort === 'canonical' ? 'selected' : ''; ?>>Bible order</option></select></label>
                    </div>
                    <div class="inline-actions top-gap-sm"><button type="submit" class="button button-secondary">Apply filters</button><a href="<?= e(scripture_search_url(['q' => $query, 'translation' => $translation, 'view' => $view])); ?>">Reset filters</a></div>
                </details>
            <?php else: ?><p class="muted-copy">This edition uses its provider’s search results and result limit. Local filters and complete pagination are unavailable.</p><?php endif; ?>
            <p class="muted-copy">Use quotation marks for a phrase within one verse. For related ideas, try Topic guides.</p>
        </form>

        <?php if ($error !== null): ?><div class="flash flash-warning top-gap" role="alert"><?= e($error); ?></div><?php endif; ?>
        <?php if ($query === ''): ?>
            <div class="search-recent top-gap" data-recent-searches hidden><div class="panel-heading"><h2>Recent searches</h2><button type="button" class="button button-secondary" data-clear-searches>Clear history</button></div><div class="word-suggestions" data-recent-search-list></div></div>
        <?php endif; ?>
        <?php if (($view === 'topics' || $query === '') && $error === null): ?>
            <section class="top-gap" aria-labelledby="topic-heading"><h2 id="topic-heading"><?= $query === '' ? 'A place to begin' : 'Topic guides'; ?></h2><p class="muted-copy">Selected starting passages for reflection. Read each passage in context.</p>
                <?php if ($topics === []): ?><p class="empty-state">There isn’t a guide for that subject yet. <a href="<?= e(scripture_search_url(array_replace($params, ['view' => 'text']))); ?>">Search these words in Scripture</a>, or try worry, forgiveness, or prayer.</p><?php endif; ?>
                <div class="word-tool-grid top-gap-sm"><?php foreach ($topics as $topic): ?><article class="panel search-topic"><p class="eyebrow">Curated topic guide</p><h3><?= e($topic['title']); ?></h3><p><?= e($topic['description']); ?></p><div class="search-topic-passages"><?php foreach ($topic['passages'] as $passage): ?><a href="<?= e(app_url('bible.php?' . http_build_query(['q' => $passage, 'translation' => $translation]))); ?>"><?= e($passage); ?> →</a><?php endforeach; ?></div></article><?php endforeach; ?></div>
            </section>
        <?php elseif ($query !== '' && $error === null): ?>
            <section class="top-gap" aria-labelledby="result-heading" data-search-results>
                <div class="panel-heading"><div><p class="eyebrow"><?= e($translation); ?></p><h2 id="result-heading"><?= $reference !== null ? e((string) $result['heading']) : 'Results for “' . e($query) . '”'; ?></h2><p class="muted-copy"><?= isset($result['total']) ? e((string) $result['total']) . ' matching verses' : e((string) count($result['results'])) . ' verses returned'; ?><?= !$external && $reference === null ? ' · Page ' . e((string) $result['page']) . ' of ' . e((string) $result['page_count']) : ''; ?></p></div></div>
                <?php if (!empty($result['suggested_query'])): ?><p class="search-correction">Did you mean <a href="<?= e(scripture_search_url(array_replace($params, ['q' => $result['suggested_query']]))); ?>"><?= e((string) $result['suggested_query']); ?></a>? Your original search is shown here.</p><?php endif; ?>
                <?php if ($topics !== [] && strtoupper($translation) !== 'RVR'): ?><p class="search-topic-suggestion">Looking for an idea? <a href="<?= e(scripture_search_url(array_replace($params, ['view' => 'topics']))); ?>">Explore <?= e(implode(', ', array_column(array_slice($topics, 0, 2), 'title'))); ?> →</a></p><?php endif; ?>
                <?php if ($result['results'] === []): ?><div class="panel top-gap-sm"><h3>No verses matched this search</h3><p>Try fewer words, another translation, or a topic guide. Your query and filters are kept above.</p><a href="<?= e(scripture_search_url(['q' => $query, 'translation' => $translation, 'match' => 'any'])); ?>">Try any word without filters →</a></div><?php endif; ?>
                <div class="stack-list top-gap-sm"><?php foreach ($result['results'] as $verse): ?>
                    <?php $context = 'bible.php?' . http_build_query(['translation' => $translation, 'book_id' => (int) $verse['book_id'], 'chapter' => (int) $verse['chapter_number']]) . '#verse-' . (int) $verse['verse_number']; ?>
                    <article class="panel search-result" id="result-<?= e((string) $verse['id']); ?>"><div class="search-result-heading"><h3><a href="<?= e(app_url($context)); ?>"><?= e(format_verse_reference($verse)); ?></a></h3><?php if (isset($verse['match_reason'])): ?><span class="pill"><?= e($verse['match_reason']); ?></span><?php endif; ?></div><p class="scripture-text"><?= scripture_highlight_matches((string) $verse['verse_text'], $query); ?></p><div class="inline-actions"><a class="button button-primary" href="<?= e(app_url($context)); ?>">Read in context</a>
                        <?php if (is_logged_in() && !empty($verse['id'])): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="save-verse"><input type="hidden" name="verse_id" value="<?= e((string) $verse['id']); ?>"><button type="submit" class="button button-secondary">Save verse</button></form><a href="<?= e(app_url('library.php?view=notes&verse_id=' . (int) $verse['id'])); ?>">Add note</a>
                        <?php elseif (!is_logged_in()): ?><a class="button button-secondary" href="<?= e(app_url('login.php?next=' . rawurlencode('search.php?' . http_build_query($params + ['page' => $result['page'] ?? 1])))); ?>">Sign in to save</a><?php endif; ?>
                    </div></article>
                <?php endforeach; ?></div>
                <?php if (($result['page_count'] ?? 1) > 1): ?><nav class="search-pagination top-gap" aria-label="Search result pages"><?php if ($result['page'] > 1): ?><a class="button button-secondary" href="<?= e(scripture_search_url($params + ['page' => $result['page'] - 1])); ?>">← Previous</a><?php endif; ?><span>Page <?= e((string) $result['page']); ?> of <?= e((string) $result['page_count']); ?></span><?php if ($result['has_more']): ?><a class="button button-primary" href="<?= e(scripture_search_url($params + ['page' => $result['page'] + 1])); ?>">Next results →</a><?php endif; ?></nav><?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

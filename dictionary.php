<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dictionary_repository.php';

$pageTitle = 'Bible Dictionary';
$pageDescription = 'Search concise Bible dictionary definitions with related Scripture references.';
$activePage = 'dictionary';

$query = trim((string) ($_GET['q'] ?? ''));
$selectedTerm = trim((string) ($_GET['term'] ?? ''));
$selectedCategoryKey = bible_dictionary_normalize((string) ($_GET['category'] ?? ''));
$selectedEntry = bible_dictionary_find($selectedTerm);
$results = bible_dictionary_search($query, 18);
$featuredTerms = bible_dictionary_featured_terms(10);
$referenceCategories = bible_reference_categories();
$selectedCategory = bible_reference_category($selectedCategoryKey);

if ($selectedEntry === null && $query !== '') {
    $selectedEntry = bible_dictionary_find($query);

    if ($selectedEntry === null && count($results) === 1) {
        $selectedEntry = $results[0];
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Bible Dictionary</p>
                <h1>Define the Word</h1>
                <p>Look up biblical words, themes, and doctrine terms, then open connected passages in the Bible reader.</p>
            </div>

            <div class="quick-stat-row">
                <div class="quick-stat">
                    <strong><?= e((string) count(bible_dictionary_entries())); ?></strong>
                    <span>terms</span>
                </div>
                <div class="quick-stat">
                    <strong><?= e((string) bible_reference_category_count()); ?></strong>
                    <span>reference items</span>
                </div>
                <div class="quick-stat">
                    <strong>Local</strong>
                    <span>dictionary</span>
                </div>
            </div>
        </div>

        <div class="panel scripture-panel bible-dictionary-shell top-gap">
            <form class="form-stack" method="get">
                <div class="search-row search-row-compact">
                    <input type="search" name="q" value="<?= e($query); ?>" placeholder="Search grace, covenant, atonement, kingdom">
                    <button class="button button-primary" type="submit">Search</button>
                </div>
            </form>

            <div class="bible-chip-row">
                <?php foreach ($featuredTerms as $entry): ?>
                    <a class="pill" href="<?= e(app_url('dictionary.php?term=' . urlencode((string) $entry['slug']))); ?>">
                        <?= e((string) $entry['term']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <section class="top-gap" id="reference-lists">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Reference Lists</p>
                    <h2><?= $selectedCategory !== null ? e((string) $selectedCategory['label']) : 'Bible People, Places, Objects, and More'; ?></h2>
                    <p class="muted-copy">
                        <?= $selectedCategory !== null
                            ? e((string) $selectedCategory['description'])
                            : 'Browse quick lists for characters, places, locations, animals, artifacts, tools, measurements, and foods.'; ?>
                    </p>
                </div>
            </div>

            <div class="bible-chip-row top-gap-sm">
                <?php foreach ($referenceCategories as $categoryKey => $category): ?>
                    <a
                        class="filter-chip <?= $selectedCategoryKey === (string) $categoryKey ? 'is-active' : ''; ?>"
                        href="<?= e(app_url('dictionary.php?category=' . urlencode((string) $categoryKey) . '#reference-lists')); ?>"
                    >
                        <?= e((string) $category['label']); ?>
                    </a>
                <?php endforeach; ?>
                <?php if ($selectedCategory !== null): ?>
                    <a class="filter-chip" href="<?= e(app_url('dictionary.php#reference-lists')); ?>">All</a>
                <?php endif; ?>
            </div>

            <?php $visibleCategories = $selectedCategory !== null ? [$selectedCategoryKey => $selectedCategory] : $referenceCategories; ?>
            <div class="dictionary-category-grid top-gap-sm">
                <?php foreach ($visibleCategories as $categoryKey => $category): ?>
                    <article class="panel dictionary-category-card">
                        <div>
                            <p class="eyebrow"><?= e((string) count((array) ($category['items'] ?? []))); ?> items</p>
                            <h3><?= e((string) $category['label']); ?></h3>
                            <p class="muted-copy"><?= e((string) $category['description']); ?></p>
                        </div>

                        <div class="dictionary-list">
                            <?php foreach ((array) ($category['items'] ?? []) as $item): ?>
                                <?php $reference = trim((string) ($item['reference'] ?? '')); ?>
                                <a class="dictionary-list-item" href="<?= e(app_url('bible.php?q=' . urlencode($reference !== '' ? $reference : (string) ($item['name'] ?? '')))); ?>">
                                    <span><?= e((string) ($item['name'] ?? '')); ?></span>
                                    <?php if ($reference !== ''): ?>
                                        <small><?= e($reference); ?></small>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($selectedEntry !== null): ?>
            <article class="panel bible-dictionary-entry top-gap">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Dictionary Entry</p>
                        <h2><?= e((string) $selectedEntry['term']); ?></h2>
                    </div>
                    <a class="button button-secondary" href="<?= e(app_url('dictionary.php?q=' . urlencode((string) $selectedEntry['term']))); ?>">Search Term</a>
                </div>

                <p class="scripture-text"><?= e((string) $selectedEntry['summary']); ?></p>
                <p><?= e((string) $selectedEntry['details']); ?></p>

                <div class="dictionary-reference-grid top-gap-sm">
                    <section>
                        <h3>Key Scriptures</h3>
                        <div class="bible-chip-row">
                            <?php foreach ((array) $selectedEntry['references'] as $reference): ?>
                                <a class="mini-card" href="<?= e(app_url('bible.php?q=' . urlencode((string) $reference))); ?>">
                                    <?= e((string) $reference); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section>
                        <h3>Related Terms</h3>
                        <div class="bible-chip-row">
                            <?php foreach ((array) $selectedEntry['related'] as $relatedTerm): ?>
                                <?php $relatedEntry = bible_dictionary_find((string) $relatedTerm); ?>
                                <?php if ($relatedEntry !== null): ?>
                                    <a class="mini-card" href="<?= e(app_url('dictionary.php?term=' . urlencode((string) $relatedEntry['slug']))); ?>">
                                        <?= e((string) $relatedEntry['term']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="mini-card"><?= e((string) $relatedTerm); ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
            </article>
        <?php endif; ?>

        <section class="top-gap">
            <div class="panel-heading">
                <div>
                    <h2><?= $query !== '' ? 'Dictionary Results' : 'Browse Terms'; ?></h2>
                    <p class="muted-copy"><?= $query !== '' ? e((string) count($results)) . ' matching term' . (count($results) === 1 ? '' : 's') : 'Start with these core Bible study terms.'; ?></p>
                </div>
            </div>

            <?php if ($results === []): ?>
                <p class="empty-state top-gap-sm">No dictionary terms matched that search yet.</p>
            <?php else: ?>
                <div class="card-grid card-grid-3 top-gap-sm">
                    <?php foreach ($results as $entry): ?>
                        <?php
                        $entryReferences = (array) $entry['references'];
                        $firstReference = (string) ($entryReferences[0] ?? '');
                        ?>
                        <article class="feature-card feature-card-new dictionary-result-card">
                            <p class="eyebrow">Dictionary</p>
                            <h3><?= e((string) $entry['term']); ?></h3>
                            <p><?= e((string) $entry['summary']); ?></p>
                            <div class="resource-action-row">
                                <a class="button button-secondary" href="<?= e(app_url('dictionary.php?term=' . urlencode((string) $entry['slug']))); ?>">Open</a>
                                <?php if ($firstReference !== ''): ?>
                                    <a class="button button-secondary" href="<?= e(app_url('bible.php?q=' . urlencode($firstReference))); ?>">Read</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

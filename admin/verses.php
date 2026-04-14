<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

if (!current_user_has_role(['admin'])) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    $activePage = '';
    require_once dirname(__DIR__) . '/includes/header.php';
    ?>
    <section class="section">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">Restricted</p>
                <h1>Admin access required</h1>
                <p>You do not have permission to manage verses.</p>
            </div>
        </div>
    </section>
    <?php
    require_once dirname(__DIR__) . '/includes/footer.php';
    exit;
}

require_once dirname(__DIR__) . '/includes/repository.php';

$pageTitle = 'Admin Verses';
$activePage = 'admin';
$pageError = null;

$q = trim((string) ($_GET['q'] ?? ''));
$selectedTranslation = trim((string) ($_GET['translation'] ?? ''));

$bookCount = 0;
$verseCounts = [];
$availableTranslations = [];
$searchResults = [];

try {
    $bookCount = count_records('SELECT COUNT(*) FROM books');
} catch (Throwable $exception) {
    $pageError = 'Stats could not be loaded because the database is unavailable.';
}

try {
    $statement = db()->query('SELECT translation, COUNT(*) as verse_count FROM verses GROUP BY translation ORDER BY translation ASC');
    $verseCounts = $statement->fetchAll();
} catch (Throwable $exception) {
    // Verses table may be empty or unavailable; gracefully show nothing.
}

try {
    $availableTranslations = fetch_available_translations();
} catch (Throwable $exception) {
    $availableTranslations = [];
}

if ($selectedTranslation === '' || !in_array($selectedTranslation, $availableTranslations, true)) {
    $selectedTranslation = in_array('MSB', $availableTranslations, true) ? 'MSB' : ($availableTranslations[0] ?? 'MSB');
}

if ($q !== '') {
    try {
        $raw = search_scripture($q, $selectedTranslation);
        $searchResults = $raw['results'] ?? (isset($raw[0]) ? $raw : []);
    } catch (Throwable $exception) {
        $pageError = 'Scripture search is unavailable.';
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Admin</p>
                <h1>Verses</h1>
                <p>Overview of imported Bible text and verse search.</p>
            </div>

            <div class="hero-actions">
                <a class="button button-secondary" href="<?= e(app_url('admin/index.php')); ?>">Back to Admin</a>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="card-grid card-grid-3 top-gap-sm">
            <article class="panel">
                <p class="eyebrow">Books</p>
                <p style="font-size:2rem;font-weight:700;margin:0"><?= e((string) $bookCount); ?></p>
                <p class="muted-copy">Bible books loaded</p>
            </article>

            <?php foreach ($verseCounts as $row): ?>
                <article class="panel">
                    <p class="eyebrow"><?= e((string) $row['translation']); ?></p>
                    <p style="font-size:2rem;font-weight:700;margin:0"><?= e(number_format((int) $row['verse_count'])); ?></p>
                    <p class="muted-copy">Verses imported</p>
                </article>
            <?php endforeach; ?>

            <?php if ($verseCounts === []): ?>
                <article class="panel">
                    <p class="muted-copy">No verses have been imported yet.</p>
                </article>
            <?php endif; ?>
        </div>

        <article class="panel top-gap-sm">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Search</p>
                    <h2>Find verses</h2>
                </div>
            </div>

            <form class="form-stack top-gap-sm" method="get" action="<?= e(app_url('admin/verses.php')); ?>">
                <div class="two-column">
                    <label>
                        <span>Search query</span>
                        <input type="text" name="q" value="<?= e($q); ?>" placeholder="John 3:16 or faith hope love&hellip;">
                    </label>

                    <label>
                        <span>Translation</span>
                        <select name="translation">
                            <?php foreach ($availableTranslations as $translation): ?>
                                <option value="<?= e($translation); ?>" <?= $selectedTranslation === $translation ? 'selected' : ''; ?>>
                                    <?= e($translation); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($availableTranslations === []): ?>
                                <option value="MSB" selected>MSB</option>
                            <?php endif; ?>
                        </select>
                    </label>
                </div>

                <div class="inline-actions">
                    <button class="button button-primary" type="submit">Search</button>
                    <?php if ($q !== ''): ?>
                        <a class="button button-secondary" href="<?= e(app_url('admin/verses.php')); ?>">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </article>

        <?php if ($q !== ''): ?>
            <div class="top-gap-sm">
                <p class="muted-copy">
                    <?= e((string) count($searchResults)); ?> result<?= count($searchResults) !== 1 ? 's' : ''; ?>
                    for <strong><?= e($q); ?></strong> in <?= e($selectedTranslation); ?>.
                </p>

                <?php if ($searchResults === []): ?>
                    <p class="empty-state">No verses matched your search.</p>
                <?php else: ?>
                    <div class="stack-list">
                        <?php foreach ($searchResults as $verse): ?>
                            <?php
                            $bookName = (string) ($verse['book_name'] ?? '');
                            $chapter = (int) ($verse['chapter_number'] ?? 0);
                            $verseNum = (int) ($verse['verse_number'] ?? 0);
                            $translation = (string) ($verse['translation'] ?? $selectedTranslation);
                            $verseText = (string) ($verse['verse_text'] ?? '');
                            $reference = $bookName . ' ' . $chapter . ':' . $verseNum;
                            $bibleUrl = app_url('bible.php?book=' . urlencode($bookName) . '&chapter=' . $chapter . '&translation=' . urlencode($translation));
                            ?>
                            <div class="list-card">
                                <div>
                                    <strong><?= e($reference); ?></strong>
                                    <span class="pill" style="margin-left:.5rem"><?= e($translation); ?></span>
                                    <p class="muted-copy"><?= e(truncate_text($verseText, 180)); ?></p>
                                </div>
                                <div>
                                    <a class="button button-secondary" href="<?= e($bibleUrl); ?>">View</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

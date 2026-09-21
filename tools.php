<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Scripture study tools';
$pageDescription = 'Explore Bible words, cross references, commentary, and reading plans without signing in.';
$activePage = 'tools';
require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Study the Word</p>
            <h1>A little context.<br>A deeper understanding.</h1>
            <p>Start with Scripture, then explore the words and connections around it. No account needed.</p>
        </div>
        <form class="word-search panel" method="get" action="<?= e(app_url('bible.php')); ?>" role="search">
            <label for="tools-passage">Which passage are you studying?</label>
            <div class="word-search-row">
                <input id="tools-passage" type="search" name="q" placeholder="For example, John 1:1–18 or grace" required>
                <button class="button button-primary" type="submit">Open Scripture</button>
            </div>
            <p class="muted-copy">In the reader, open “Resources for this passage” for cross references and available commentary.</p>
        </form>
        <div class="word-tool-grid top-gap">
            <?php foreach ([
                ['Words & themes', 'Bible dictionary', 'Look up biblical terms and follow related Scripture references.', 'dictionary.php', 'Open dictionary'],
                ['Passage connections', 'Cross references & commentary', 'Open a passage, then explore its resources. Availability depends on the passage.', 'bible.php?q=John+1', 'Study John 1'],
                ['Background', 'Book context', 'Choose a book to explore its background before reading a chapter.', 'bible.php', 'Browse Bible books'],
                ['Word study', 'Concordance', 'Search a word to find matching verses and explore repeated words in a passage.', 'bible.php?q=grace', 'Explore “grace”'],
                ['Daily reading', 'Bible plans', 'Browse plans and Scripture previews. Sign in to join and track your progress.', 'studies.php', 'Browse plans'],
                ['The good news', 'Explore the Gospel', 'Walk through the message of the Gospel with Scripture.', 'good-news.php', 'Explore the Gospel'],
            ] as [$category, $title, $description, $path, $action]): ?>
                <a class="word-tool-card" href="<?= e(app_url($path)); ?>">
                    <span class="eyebrow"><?= e($category); ?></span><h2><?= e($title); ?></h2><p><?= e($description); ?></p><span><?= e($action); ?> →</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

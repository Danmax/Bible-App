<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/home_scripture.php';

$pageTitle = 'Read the Word';
$pageDescription = 'Read the Bible, search Scripture, and explore study tools. No account needed.';
$activePage = 'home';
$dailyVerse = home_daily_verse_payload(APP_DEFAULT_TRANSLATION, home_daily_verse_index());
require_once __DIR__ . '/includes/header.php';
?>
<section class="section word-home">
    <div class="container">
        <div class="word-home-grid">
            <div class="word-welcome">
                <p class="eyebrow">The Word, within reach</p>
                <h1>Make room<br>for Scripture.</h1>
                <p class="hero-copy">Open your Bible. Follow a passage. Go deeper with tools that help you understand what you read.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="<?= e(app_url('bible.php?q=John+1')); ?>">Read the Bible</a>
                    <a class="button button-secondary" href="<?= e(app_url('bible.php')); ?>">Browse books</a>
                </div>
                <p class="muted-copy">Read and explore freely. No account needed.</p>
                <a class="button button-secondary" data-resume-reading hidden href="<?= e(app_url('bible.php')); ?>">Continue reading</a>
            </div>
            <aside class="word-daily panel" aria-labelledby="daily-scripture-title">
                <p class="eyebrow" id="daily-scripture-title">Today's Scripture · <?= e((string) $dailyVerse['translation']); ?></p>
                <blockquote><?= e((string) $dailyVerse['text']); ?></blockquote>
                <p class="word-reference"><?= e((string) $dailyVerse['reference']); ?></p>
                <a class="button button-secondary" href="<?= e(app_url('bible.php?q=' . urlencode((string) $dailyVerse['query']) . '&translation=' . urlencode((string) $dailyVerse['translation']))); ?>">Read in context <span aria-hidden="true">→</span></a>
            </aside>
        </div>
        <form class="word-search panel" method="get" action="<?= e(app_url('bible.php')); ?>" role="search">
            <label for="home-scripture-search">Find a passage or explore a word</label>
            <div class="word-search-row">
                <input id="home-scripture-search" type="search" name="q" placeholder="John 3:16, Psalm 23, or hope" required>
                <button class="button button-primary" type="submit">Search Scripture</button>
            </div>
            <nav class="word-suggestions" aria-label="Suggested passages">
                <span>Start with</span>
                <a href="<?= e(app_url('bible.php?q=John+1')); ?>">John 1</a>
                <a href="<?= e(app_url('bible.php?q=Psalm+23')); ?>">Psalm 23</a>
                <a href="<?= e(app_url('bible.php?q=Romans+8')); ?>">Romans 8</a>
            </nav>
        </form>
        <div class="section-heading word-section-heading">
            <p class="eyebrow">Go deeper</p>
            <h2>Tools for time in the Word</h2>
            <p>Move from reading to understanding, at your own pace.</p>
        </div>
        <div class="word-tool-grid">
            <a class="word-tool-card" href="<?= e(app_url('dictionary.php')); ?>"><span class="eyebrow">01 · Understand</span><h3>Bible dictionary</h3><p>Explore biblical words and themes with related passages.</p><span>Look up a word →</span></a>
            <a class="word-tool-card" href="<?= e(app_url('tools.php')); ?>"><span class="eyebrow">02 · Explore</span><h3>Scripture study tools</h3><p>Find cross references, commentary, and passage context.</p><span>Explore tools →</span></a>
            <a class="word-tool-card" href="<?= e(app_url('studies.php')); ?>"><span class="eyebrow">03 · Build a rhythm</span><h3>Bible reading plans</h3><p>Browse guided readings and discover your next passage.</p><span>Browse plans →</span></a>
        </div>
        <div class="word-account-note">
            <p><strong>A place for your reflections.</strong> Sign in when you want to save verses, highlights, and personal notes.</p>
            <a href="<?= e(app_url(is_logged_in() ? 'library.php' : 'login.php')); ?>"><?= is_logged_in() ? 'Open your library' : 'Sign in to save'; ?> <span aria-hidden="true">→</span></a>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

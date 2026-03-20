<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$pageTitle = 'Bible Reader';
$activePage = 'bible';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Bible Reader</p>
            <h1>Read and celebrate the Word</h1>
            <p>Search, save, and study passages once the Bible text is loaded into MySQL.</p>
        </div>

        <div class="panel">
            <form class="search-row" action="#" method="get">
                <input type="search" placeholder="Search by verse, keyword, or topic">
                <button class="button button-primary" type="submit">Search</button>
            </form>
        </div>

        <div class="card-grid card-grid-3">
            <article class="feature-card">
                <span class="feature-icon">GN</span>
                <h3>Genesis</h3>
                <p>Beginnings, covenant, and promise.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon">PS</span>
                <h3>Psalms</h3>
                <p>Prayer, worship, lament, and praise.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon">JN</span>
                <h3>John</h3>
                <p>Christ, truth, love, and belief.</p>
            </article>
        </div>

        <div class="panel scripture-panel">
            <div class="scripture-heading">
                <div>
                    <p class="eyebrow">Featured Passage</p>
                    <h2>Psalm 23:1-3</h2>
                </div>
                <div class="showcase-actions">
                    <span class="mini-card">Bookmark</span>
                    <span class="mini-card">Share</span>
                    <span class="mini-card">Add Note</span>
                </div>
            </div>

            <p class="scripture-text">
                The Lord is my shepherd; I shall not want. He maketh me to lie down in green pastures: he leadeth me beside the still waters.
                He restoreth my soul: he leadeth me in the paths of righteousness for his name's sake.
            </p>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

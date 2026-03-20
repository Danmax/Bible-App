<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

$pageTitle = 'Saved Verses';
$activePage = 'bookmarks';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Bookmarks</p>
            <h1>Your saved passages</h1>
            <p>Group verses by topic, season, or prayer need.</p>
        </div>

        <div class="card-grid card-grid-3">
            <article class="bookmark-card">
                <span class="pill">Strength</span>
                <h3>Isaiah 41:10</h3>
                <p>Fear thou not; for I am with thee...</p>
            </article>
            <article class="bookmark-card">
                <span class="pill">Wisdom</span>
                <h3>Proverbs 3:5-6</h3>
                <p>Trust in the Lord with all thine heart...</p>
            </article>
            <article class="bookmark-card">
                <span class="pill">Peace</span>
                <h3>John 14:27</h3>
                <p>Peace I leave with you, my peace I give unto you...</p>
            </article>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

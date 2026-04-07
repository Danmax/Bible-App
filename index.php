<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Home';
$activePage = 'home';

require_once __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="container hero-grid">
        <div>
            <p class="eyebrow">Warm, bold, mobile-first Bible app</p>
            <h1>Study the Word. Save the verses. Strengthen the community.</h1>
            <p class="hero-copy">
                Good News Bible blends Scripture study, personal planning, and church-centered events into one simple app built for phones first.
            </p>
            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(app_url('register.php')); ?>">Start Free</a>
                <a class="button button-secondary" href="<?= e(app_url('community.php')); ?>">See Community</a>
            </div>
            <div class="hero-stat-row">
                <div class="stat-card">
                    <strong>Daily Study</strong>
                    <span>Read, save, and reflect on verses.</span>
                </div>
                <div class="stat-card">
                    <strong>Shared Events</strong>
                    <span>Services, meetups, Zoom calls, and celebrations.</span>
                </div>
            </div>
        </div>

        <?php
        $landscapeImages = [
            // Mountain peaks at golden hour
            ['id' => '1506905925346-21bda4d32df4', 'position' => 'center 55%'],
            ['id' => '1464822759023-fed622ff2c3b', 'position' => 'center 40%'],
            // Misty forest with sunlight rays
            ['id' => '1448375240586-882707db888b', 'position' => 'center 30%'],
            ['id' => '1511497584788-876760111969', 'position' => 'center 50%'],
            // Rolling green valley
            ['id' => '1500534314209-a25ddb2bd429', 'position' => 'center 45%'],
            ['id' => '1501854140801-50d01698950b', 'position' => 'center 40%'],
            // Ocean at sunrise
            ['id' => '1507525428034-b723cf961d3e', 'position' => 'center 60%'],
            ['id' => '1519125323398-675f0ddb6308', 'position' => 'center 50%'],
            // Desert cliffs / biblical landscapes
            ['id' => '1469474968028-56623f02e42e', 'position' => 'center 45%'],
            ['id' => '1499343162213-848e3c5e1fef', 'position' => 'center 50%'],
        ];
        $landscape = $landscapeImages[array_rand($landscapeImages)];
        $landscapeSrc = 'https://images.unsplash.com/photo-' . $landscape['id'] . '?w=900&q=80&auto=format&fit=crop';
        ?>
        <aside class="showcase-card showcase-card-landscape">
            <div class="showcase-landscape" aria-hidden="true">
                <img
                    class="showcase-landscape-img"
                    src="<?= e($landscapeSrc); ?>"
                    alt=""
                    loading="eager"
                    draggable="false"
                    style="object-position: <?= e($landscape['position']); ?>"
                >
                <div class="showcase-landscape-overlay"></div>
            </div>
            <div class="showcase-top">
                <span class="pill showcase-pill">Featured Verse</span>
                <span class="pill showcase-pill"><?= e(APP_DEFAULT_TRANSLATION); ?></span>
            </div>
            <blockquote class="showcase-quote">
                "Trust in the Lord with all thine heart; and lean not unto thine own understanding."
            </blockquote>
            <p class="showcase-reference">Proverbs 3:5</p>
            <div class="showcase-actions">
                <a class="mini-card showcase-action" href="<?= e(app_url('bible.php?q=' . urlencode('Proverbs 3:5-6') . '&translation=' . urlencode(APP_DEFAULT_TRANSLATION))); ?>">Open Verse</a>
                <a class="mini-card showcase-action" href="<?= e(app_url('bookmarks.php')); ?>">Bookmarks</a>
                <a class="mini-card showcase-action" href="<?= e(app_url('notes.php')); ?>">Study Note</a>
            </div>
        </aside>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Core Features</p>
            <h2>Designed for Scripture, structure, and shared life</h2>
        </div>

        <div class="card-grid card-grid-3">
            <article class="feature-card">
                <span class="feature-icon">BI</span>
                <h3>Bible Reader</h3>
                <p>Browse books, chapters, and verses with space to search, save, and reflect.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon">SV</span>
                <h3>Saved Verses</h3>
                <p>Bookmark passages, add tags, and build verse collections around prayer, family, and strength.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon">YP</span>
                <h3>Yearly Planner</h3>
                <p>Set reading goals, track progress, and keep spiritual routines visible every month.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon">CM</span>
                <h3>Community</h3>
                <p>Share services, dev meetups, meetings, pot lucks, education sessions, and celebrations.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon">NT</span>
                <h3>Study Notes</h3>
                <p>Write verse-based reflections, sermon notes, and topic studies tied back to Scripture.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon">PR</span>
                <h3>Prayer Rhythm</h3>
                <p>Organize requests, reminders, and planned community prayer times in one place.</p>
            </article>
        </div>
    </div>
</section>

<section class="section section-contrast">
    <div class="container two-column">
        <div>
            <p class="eyebrow">Community Module</p>
            <h2>More than a Bible reader</h2>
            <p>
                The app should help people gather, not just read. Community events can include dev meetups, ministry meetings,
                Zoom calls, Sunday services, education tracks, career nights, pot lucks, and celebrations.
            </p>
            <ul class="check-list">
                <li>Shared event feed with categories</li>
                <li>In-person and online event support</li>
                <li>RSVP and personal planner sync</li>
                <li>Leader-managed event publishing</li>
            </ul>
        </div>
        <div class="timeline-card">
            <div class="timeline-item">
                <strong>Sunday Service</strong>
                <span>Community worship and announcements</span>
            </div>
            <div class="timeline-item">
                <strong>Zoom Bible Study</strong>
                <span>Midweek remote gathering and notes</span>
            </div>
            <div class="timeline-item">
                <strong>Dev Meetup</strong>
                <span>Career and technology fellowship event</span>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

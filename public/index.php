<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$pageTitle = 'Home';
$activePage = 'home';

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="hero">
    <div class="container hero-grid">
        <div>
            <p class="eyebrow">Warm, bold, mobile-first Bible app</p>
            <h1>Study the Word. Save the verses. Strengthen the community.</h1>
            <p class="hero-copy">
                Word Trail blends Scripture study, personal planning, and church-centered events into one simple app built for phones first.
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

        <aside class="showcase-card">
            <div class="showcase-top">
                <span class="pill">Featured Verse</span>
                <span class="pill pill-dark">KJV</span>
            </div>
            <blockquote>
                "Trust in the Lord with all thine heart; and lean not unto thine own understanding."
            </blockquote>
            <p>Proverbs 3:5</p>
            <div class="showcase-actions">
                <a class="mini-card" href="<?= e(app_url('bible.php?q=' . urlencode('Proverbs 3:5-6') . '&translation=KJV')); ?>">Open Verse</a>
                <a class="mini-card" href="<?= e(app_url('bookmarks.php')); ?>">Bookmarks</a>
                <a class="mini-card" href="<?= e(app_url('notes.php')); ?>">Study Note</a>
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
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

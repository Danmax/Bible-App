<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$user = current_user();

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Personal Dashboard</p>
            <h1>Welcome back, <?= e($user['name'] ?? 'Member'); ?></h1>
            <p>Your study life, saved verses, plans, and community rhythm all in one place.</p>
        </div>

        <div class="card-grid card-grid-4">
            <article class="dashboard-card">
                <span class="feature-icon">DV</span>
                <h3>Daily Verse</h3>
                <p>Psalm 119:105</p>
            </article>
            <article class="dashboard-card">
                <span class="feature-icon">BM</span>
                <h3>Bookmarks</h3>
                <p>12 passages saved</p>
            </article>
            <article class="dashboard-card">
                <span class="feature-icon">PL</span>
                <h3>Planner</h3>
                <p>3 yearly goals active</p>
            </article>
            <article class="dashboard-card">
                <span class="feature-icon">EV</span>
                <h3>Events</h3>
                <p>2 upcoming community events</p>
            </article>
        </div>

        <div class="two-column">
            <div class="panel">
                <h2>Study Focus</h2>
                <div class="list-card">
                    <div>
                        <strong>Reading Plan</strong>
                        <span>Gospels in 90 days</span>
                    </div>
                    <span class="pill pill-dark">Day 14</span>
                </div>
                <div class="list-card">
                    <div>
                        <strong>Verse Memory</strong>
                        <span>Proverbs 3:5-6</span>
                    </div>
                    <span class="pill">Active</span>
                </div>
                <div class="list-card">
                    <div>
                        <strong>Prayer Goal</strong>
                        <span>Morning prayer routine</span>
                    </div>
                    <span class="pill">5 days</span>
                </div>
            </div>

            <div class="panel">
                <h2>Upcoming Community</h2>
                <div class="list-card">
                    <div>
                        <strong>Dev Meetup Night</strong>
                        <span>Career, coding, and fellowship</span>
                    </div>
                    <span class="pill pill-dark">Mar 28</span>
                </div>
                <div class="list-card">
                    <div>
                        <strong>Zoom Bible Study</strong>
                        <span>Romans chapter study</span>
                    </div>
                    <span class="pill pill-dark">Apr 02</span>
                </div>
                <a class="button button-secondary" href="<?= e(app_url('community.php')); ?>">Open Community Feed</a>
            </div>
        </div>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

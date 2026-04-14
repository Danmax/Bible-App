<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$user = refresh_current_user();
$pageError = null;
$stats = [
    'bookmarks' => 0,
    'notes' => 0,
    'goals' => 0,
    'events' => 0,
];
$recentBookmarks = [];
$recentNotes = [];
$upcomingEvents = [];

if ($user === null) {
    set_flash('Sign in again to continue.', 'warning');
    redirect('login.php');
}

try {
    $stats = fetch_dashboard_stats((int) $user['id']);
    $recentBookmarks = fetch_recent_bookmarks((int) $user['id']);
    $recentNotes = fetch_recent_notes((int) $user['id']);
    $upcomingEvents = fetch_upcoming_events();
} catch (Throwable $exception) {
    $pageError = 'The dashboard is live, but the database content could not be loaded right now.';
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Personal Dashboard</p>
                <h1>Welcome back, <?= e($user['name'] ?? 'Member'); ?></h1>
                <p>Your study life, saved verses, notes, and community rhythm all in one place.</p>
            </div>

            <div class="quick-stat-row">
                <div class="quick-stat">
                    <strong><?= e((string) $stats['bookmarks']); ?></strong>
                    <span>saved passages</span>
                </div>
                <div class="quick-stat">
                    <strong><?= e((string) $stats['notes']); ?></strong>
                    <span>study notes</span>
                </div>
                <div class="quick-stat">
                    <strong><?= e((string) $stats['events']); ?></strong>
                    <span>upcoming events</span>
                </div>
            </div>

            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(app_url('bible.php')); ?>">Open Bible</a>
                <a class="button button-secondary" href="<?= e(app_url('good-news.php')); ?>">Open Good News</a>
                <a class="button button-secondary" href="<?= e(app_url('planner.php')); ?>">Open Planner</a>
                <a class="button button-secondary" href="<?= e(app_url('community.php')); ?>">Open Community</a>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="card-grid card-grid-4 top-gap">
            <article class="dashboard-card">
                <span class="feature-icon">BM</span>
                <h3>Bookmarks</h3>
                <p><?= e((string) $stats['bookmarks']); ?> passages saved</p>
            </article>
            <article class="dashboard-card">
                <span class="feature-icon">NT</span>
                <h3>Notes</h3>
                <p><?= e((string) $stats['notes']); ?> study notes captured</p>
            </article>
            <article class="dashboard-card">
                <span class="feature-icon">PL</span>
                <h3>Planner</h3>
                <p><?= e((string) $stats['goals']); ?> active yearly goals</p>
            </article>
            <article class="dashboard-card">
                <span class="feature-icon">EV</span>
                <h3>Events</h3>
                <p><?= e((string) $stats['events']); ?> upcoming community events</p>
            </article>
        </div>

        <div class="two-column top-gap">
            <div class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>Recent saved verses</h2>
                        <p class="muted-copy">Quick access to the passages you saved most recently.</p>
                    </div>
                    <a class="button button-secondary" href="<?= e(app_url('bookmarks.php')); ?>">Open Saved</a>
                </div>

                <?php if ($recentBookmarks === []): ?>
                    <p class="empty-state">No bookmarks yet. Save verses from the Bible reader to see them here.</p>
                <?php else: ?>
                    <?php foreach ($recentBookmarks as $bookmark): ?>
                        <div class="list-card list-card-block">
                            <div>
                                <strong><?= e(format_verse_reference($bookmark)); ?></strong>
                                <span><?= e(truncate_text($bookmark['verse_text'], 110)); ?></span>
                            </div>
                            <?php if (!empty($bookmark['tag'])): ?>
                                <span class="pill"><?= e((string) $bookmark['tag']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>Recent notes</h2>
                        <p class="muted-copy">Continue the studies you were working on last.</p>
                    </div>
                    <a class="button button-secondary" href="<?= e(app_url('notes.php')); ?>">Open Notes</a>
                </div>

                <?php if ($recentNotes === []): ?>
                    <p class="empty-state">No study notes yet. Add one from a verse or create one directly.</p>
                <?php else: ?>
                    <?php foreach ($recentNotes as $note): ?>
                        <div class="list-card list-card-block">
                            <div>
                                <strong><?= e((string) $note['title']); ?></strong>
                                <span><?= e(truncate_text((string) $note['content'], 110)); ?></span>
                            </div>
                            <?php if (!empty($note['book_name'])): ?>
                                <span class="pill pill-dark"><?= e(format_verse_reference($note)); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel top-gap">
            <div class="panel-heading">
                <div>
                    <h2>Upcoming community</h2>
                    <p class="muted-copy">Events pulled from the shared community feed.</p>
                </div>
                <a class="button button-secondary" href="<?= e(app_url('community.php')); ?>">Open Community</a>
            </div>

            <?php if ($upcomingEvents === []): ?>
                <p class="empty-state">No published events are scheduled yet.</p>
            <?php else: ?>
                <div class="card-grid card-grid-3">
                    <?php foreach ($upcomingEvents as $event): ?>
                        <article class="event-card">
                            <span class="pill"><?= e((string) ($event['category_label'] ?? 'Community')); ?></span>
                            <h3><?= e((string) $event['title']); ?></h3>
                            <p><?= e(truncate_text((string) $event['description'], 120)); ?></p>
                            <div class="event-meta">
                                <span><?= e(format_event_date((string) $event['start_at'])); ?></span>
                                <span><?= e((string) ($event['location_name'] ?: $event['event_type'])); ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

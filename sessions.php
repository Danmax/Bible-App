<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Public Sessions';
$activePage = 'sessions';
$pageError = null;
$sessions = [];
$upcomingSessions = [];
$pastSessions = [];
$featuredCount = 0;

if (public_sessions_available()) {
    try {
        $sessions = fetch_public_sessions(false, 60);
    } catch (Throwable $exception) {
        $pageError = 'Public sessions could not be loaded because the database is unavailable.';
    }
} else {
    $pageError = 'Public sessions are not available yet. Run the public session migration to enable this page.';
}

$now = time();

foreach ($sessions as $session) {
    if ((int) ($session['is_featured'] ?? 0) === 1) {
        $featuredCount++;
    }

    $comparisonDate = (string) ($session['end_at'] ?: $session['start_at']);
    $comparisonTime = strtotime($comparisonDate);

    if ($comparisonTime !== false && $comparisonTime < $now) {
        $pastSessions[] = $session;
        continue;
    }

    $upcomingSessions[] = $session;
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Public Sessions</p>
                <h1>Open sessions managed by the admin team</h1>
                <p>Browse upcoming Bible studies, prayer gatherings, workshops, and public teaching sessions in one place.</p>
            </div>

            <div class="quick-stat-row">
                <div class="quick-stat">
                    <strong><?= e((string) count($upcomingSessions)); ?></strong>
                    <span>upcoming sessions</span>
                </div>
                <div class="quick-stat">
                    <strong><?= e((string) $featuredCount); ?></strong>
                    <span>featured</span>
                </div>
            </div>

            <div class="hero-actions">
                <?php if (current_user_has_role(['admin'])): ?>
                    <a class="button button-primary" href="<?= e(app_url('admin/sessions.php')); ?>">Manage Sessions</a>
                <?php endif; ?>
                <a class="button button-secondary" href="<?= e(app_url('community.php')); ?>">Open Community</a>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="top-gap">
            <div class="section-heading">
                <p class="eyebrow">Upcoming</p>
                <h2>Join what is coming next</h2>
            </div>

            <?php if ($upcomingSessions === []): ?>
                <article class="panel">
                    <h3>No public sessions scheduled</h3>
                    <p>The admin team has not published any upcoming sessions yet.</p>
                </article>
            <?php else: ?>
                <div class="card-grid card-grid-2">
                    <?php foreach ($upcomingSessions as $session): ?>
                        <article class="event-card" id="session-<?= e((string) $session['id']); ?>">
                            <div class="bookmark-header">
                                <div>
                                    <div class="inline-actions">
                                        <span class="pill"><?= e(ucwords(str_replace('-', ' ', (string) $session['session_type']))); ?></span>
                                        <?php if ((int) ($session['is_featured'] ?? 0) === 1): ?>
                                            <span class="pill pill-dark">Featured</span>
                                        <?php endif; ?>
                                    </div>
                                    <h3><?= e((string) $session['title']); ?></h3>
                                    <p><?= e((string) $session['summary']); ?></p>
                                </div>
                            </div>

                            <div class="stack-list">
                                <div class="list-card">
                                    <strong>When</strong>
                                    <span><?= e(format_event_datetime((string) $session['start_at'])); ?></span>
                                </div>
                                <?php if (!empty($session['end_at'])): ?>
                                    <div class="list-card">
                                        <strong>Ends</strong>
                                        <span><?= e(format_event_datetime((string) $session['end_at'])); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($session['host_name'])): ?>
                                    <div class="list-card">
                                        <strong>Host</strong>
                                        <span><?= e((string) $session['host_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($session['location_name'])): ?>
                                    <div class="list-card">
                                        <strong>Location</strong>
                                        <span><?= e((string) $session['location_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($session['capacity'])): ?>
                                    <div class="list-card">
                                        <strong>Capacity</strong>
                                        <span><?= e((string) $session['capacity']); ?> seats</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="inline-actions top-gap-sm">
                                <?php if (!empty($session['meeting_url'])): ?>
                                    <a class="button button-primary" href="<?= e((string) $session['meeting_url']); ?>" target="_blank" rel="noreferrer noopener">Join Session</a>
                                <?php endif; ?>
                                <a class="button button-secondary" href="<?= e(app_url('sessions.php#session-' . (int) $session['id'])); ?>">Share Link</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($pastSessions !== []): ?>
            <div class="top-gap">
                <div class="section-heading">
                    <p class="eyebrow">Archive</p>
                    <h2>Past public sessions</h2>
                </div>

                <div class="card-grid card-grid-2">
                    <?php foreach ($pastSessions as $session): ?>
                        <article class="panel">
                            <div class="bookmark-header">
                                <div>
                                    <span class="pill"><?= e(ucwords(str_replace('-', ' ', (string) $session['session_type']))); ?></span>
                                    <h3><?= e((string) $session['title']); ?></h3>
                                    <p><?= e((string) $session['summary']); ?></p>
                                </div>
                            </div>
                            <p class="muted-copy"><?= e(format_event_datetime((string) $session['start_at'])); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

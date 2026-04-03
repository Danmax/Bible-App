<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Divine Radio';
$activePage = 'good-news';
$pageError = null;
$station = null;
$playlistId = '';
$playlistEmbedUrl = '';
$listenUrl = '';
$streamUrl = '';

try {
    $station = fetch_public_radio_station_by_name('Divine Radio');

    if ($station !== null) {
        $playlistId = trim((string) ($station['youtube_playlist_id'] ?? ''));
        $playlistEmbedUrl = $playlistId !== ''
            ? 'https://www.youtube-nocookie.com/embed/videoseries?list=' . rawurlencode($playlistId)
            : '';
        $listenUrl = trim((string) ($station['listen_url'] ?? ''));
        $streamUrl = trim((string) ($station['stream_url'] ?? ''));
    }
} catch (Throwable $exception) {
    $pageError = 'Divine Radio could not be loaded right now.';
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Christian Radio</p>
                <h1>Divine Radio</h1>
                <p>A dedicated listening page for worship, gospel, and Christ-centered encouragement managed by the admin team.</p>
            </div>

            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(app_url('good-news.php#christian-radio')); ?>">Back to Good News Radio</a>
                <?php if ($listenUrl !== ''): ?>
                    <a class="button button-secondary" href="<?= e($listenUrl); ?>" target="_blank" rel="noreferrer noopener">Official Station Page</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <?php if ($station === null): ?>
            <article class="panel good-news-radio-panel">
                <div class="panel-heading">
                    <div>
                        <h2>Divine Radio is not published yet</h2>
                        <p class="muted-copy">Create or publish a station named <strong>Divine Radio</strong> from the admin radio manager to make this page live.</p>
                    </div>
                </div>

                <?php if (current_user_has_role(['admin'])): ?>
                    <div class="top-gap-sm">
                        <a class="button button-primary" href="<?= e(app_url('admin/radio.php')); ?>">Manage Radio Settings</a>
                    </div>
                <?php endif; ?>
            </article>
        <?php else: ?>
            <section class="panel good-news-radio-panel divine-radio-page">
                <div class="good-news-radio-player">
                    <div class="good-news-radio-now">
                        <span class="pill pill-dark"><?= e((string) ($station['kind'] ?? 'Radio')); ?></span>
                        <h2><?= e((string) ($station['name'] ?? 'Divine Radio')); ?></h2>
                        <p><?= e((string) ($station['tagline'] ?? 'Christian encouragement and worship.')); ?></p>

                        <?php if ($playlistEmbedUrl !== ''): ?>
                            <div class="good-news-radio-video">
                                <iframe
                                    class="good-news-radio-video-frame"
                                    src="<?= e($playlistEmbedUrl); ?>"
                                    title="Divine Radio playlist player"
                                    loading="lazy"
                                    referrerpolicy="strict-origin-when-cross-origin"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    allowfullscreen
                                ></iframe>
                            </div>
                        <?php elseif ($streamUrl !== ''): ?>
                            <audio class="good-news-radio-audio" controls preload="none" src="<?= e($streamUrl); ?>"></audio>
                        <?php endif; ?>

                        <div class="inline-actions">
                            <?php if ($listenUrl !== ''): ?>
                                <a class="button button-secondary" href="<?= e($listenUrl); ?>" target="_blank" rel="noreferrer noopener">Open official station page</a>
                            <?php endif; ?>
                            <a class="button button-secondary" href="<?= e(app_url('bible.php?q=' . urlencode('Psalm 95') . '&translation=' . urlencode(APP_DEFAULT_TRANSLATION))); ?>">Open a worship Psalm</a>
                        </div>
                    </div>

                    <div class="stack-list">
                        <article class="good-news-radio-station">
                            <span class="pill">Station Settings</span>
                            <strong>Managed by admin</strong>
                            <span>This page reflects the live stream URL, official listening link, playlist connection, status, and featured settings configured in the radio manager.</span>
                        </article>
                        <article class="good-news-radio-station">
                            <span class="pill">Study Companion</span>
                            <strong>Listen while you read</strong>
                            <span>Keep Divine Radio playing while you study Scripture, pray, or reflect on the Good News.</span>
                        </article>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

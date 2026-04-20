<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Bible Studies';
$pageDescription = 'Join curated Bible studies, devotionals, and group plans with daily Scripture, reflection questions, challenges, and completion badges.';
$activePage = 'studies';
$user = is_logged_in() ? refresh_current_user() : null;
$pageError = null;
$studies = [];

if (curated_studies_available()) {
    try {
        $studies = fetch_public_studies($user !== null ? (int) $user['id'] : null);
    } catch (Throwable $exception) {
        $pageError = 'Bible studies could not be loaded because the database is unavailable.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading-rich">
            <div>
                <p class="eyebrow">Bible Studies</p>
                <h1>Curated plans for daily growth</h1>
                <p>Join devotionals, group studies, and 30-day Scripture plans with reflection questions, daily challenges, locked teaching videos, and completion badges.</p>
            </div>
            <div class="quick-stat-row">
                <span class="quick-stat"><strong><?= e((string) count($studies)); ?></strong><span>Available studies</span></span>
                <span class="quick-stat"><strong>3</strong><span>Starter formats</span></span>
                <span class="quick-stat"><strong>1</strong><span>Daily rhythm</span></span>
            </div>
        </div>

        <?php if (!curated_studies_available()): ?>
            <div class="flash flash-warning">Curated Bible studies are not installed yet. Run <strong>sql/add_curated_bible_studies.sql</strong> to enable this feature.</div>
        <?php elseif ($pageError !== null): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php elseif ($studies === []): ?>
            <section class="panel top-gap">
                <h2>No studies published yet</h2>
                <p class="muted-copy">Published curated studies will appear here once an admin creates one.</p>
                <?php if (current_user_has_role(['admin'])): ?>
                    <div class="top-gap-sm">
                        <a class="button button-primary" href="<?= e(app_url('admin/studies.php')); ?>">Create Study</a>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <div class="card-grid card-grid-3 top-gap">
                <?php foreach ($studies as $study): ?>
                    <article class="dashboard-card study-card">
                        <div class="panel-heading">
                            <span class="pill"><?= e((string) ($study['duration_days'] ?? 0)); ?> days</span>
                            <?php if (!empty($study['current_user_enrollment_id'])): ?>
                                <span class="pill pill-dark"><?= !empty($study['current_user_completed_at']) ? 'Completed' : 'Joined'; ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h2><?= e((string) $study['title']); ?></h2>
                            <p><?= e((string) ($study['summary'] ?? '')); ?></p>
                        </div>
                        <p class="muted-copy"><?= e((string) ($study['step_count'] ?? 0)); ?> sections with Scripture, questions, and daily challenges.</p>
                        <div class="inline-actions">
                            <a class="button button-primary" href="<?= e(app_url('study.php?slug=' . urlencode((string) $study['slug']))); ?>">
                                <?= !empty($study['current_user_enrollment_id']) ? 'Continue Study' : 'View Study'; ?>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

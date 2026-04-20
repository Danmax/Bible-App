<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$activePage = 'studies';
$user = is_logged_in() ? refresh_current_user() : null;
$pageError = null;
$inviteLink = null;
$invite = null;

if (!curated_studies_available()) {
    $pageTitle = 'Bible Study';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="section">
        <div class="container">
            <div class="flash flash-warning">Curated Bible studies are not installed yet. Run <strong>sql/add_curated_bible_studies.sql</strong> to enable this feature.</div>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$inviteToken = trim((string) ($_GET['invite'] ?? ''));

if ($inviteToken !== '') {
    $invite = fetch_study_invite_by_token($inviteToken);

    if ($invite !== null) {
        $_GET['slug'] = (string) $invite['study_slug'];
    }
}

$slug = trim((string) ($_GET['slug'] ?? ''));

if ($slug === '') {
    set_flash('Choose a Bible study first.', 'warning');
    redirect('studies.php');
}

$study = fetch_study_by_slug($slug, $user !== null ? (int) $user['id'] : null, current_user_has_role(['admin']));

if ($study === null) {
    http_response_code(404);
    $pageTitle = 'Study Not Found';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <section class="section">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">Bible Studies</p>
                <h1>Study not found</h1>
                <p>This study may be unpublished or the link may be incorrect.</p>
            </div>
            <a class="button button-primary" href="<?= e(app_url('studies.php')); ?>">Browse Studies</a>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($user === null) {
        set_flash('Sign in first to join this Bible study.', 'warning');
        redirect('login.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    try {
        if ($action === 'join') {
            $enrollmentId = enroll_user_in_study((int) $study['id'], (int) $user['id']);

            if ($inviteToken !== '') {
                accept_study_invite($inviteToken, (int) $user['id']);
            }

            set_flash('Bible study added to your daily rhythm.', 'success');
            redirect('study-day.php?enrollment_id=' . $enrollmentId . '&day=1');
        }

        $enrollment = fetch_study_enrollment((int) $study['id'], (int) $user['id']);

        if ($enrollment === null) {
            throw new RuntimeException('Join the study before posting or inviting friends.');
        }

        if ($action === 'invite') {
            $recipientEmail = trim((string) ($_POST['recipient_email'] ?? ''));
            $createdInvite = create_study_invite((int) $study['id'], (int) $enrollment['id'], (int) $user['id'], $recipientEmail);
            $inviteLink = app_url('study.php?invite=' . urlencode((string) $createdInvite['invite_token']), true);
            set_flash('Study invite link created.', 'success');
        } elseif ($action === 'discussion') {
            create_study_discussion_message((int) $study['id'], (int) $enrollment['id'], (int) $user['id'], (string) ($_POST['message'] ?? ''));
            set_flash('Discussion message posted.', 'success');
            redirect('study.php?slug=' . urlencode((string) $study['slug']) . '#discussion');
        }
    } catch (Throwable $exception) {
        $pageError = $exception instanceof RuntimeException ? $exception->getMessage() : 'Study action could not be completed.';
    }
}

$study = fetch_study_by_slug($slug, $user !== null ? (int) $user['id'] : null, current_user_has_role(['admin']));
$steps = fetch_study_steps((int) $study['id']);
$enrollment = $user !== null ? fetch_study_enrollment((int) $study['id'], (int) $user['id']) : null;
$progressMap = $enrollment !== null ? fetch_enrollment_progress_map((int) $enrollment['id']) : [];
$messages = $enrollment !== null ? fetch_study_discussion_messages((int) $study['id']) : [];
$completedCount = 0;

foreach ($steps as $step) {
    if (!empty($progressMap[(int) $step['id']]['completed_at'])) {
        $completedCount++;
    }
}

$pageTitle = (string) $study['title'];
$pageDescription = (string) ($study['summary'] ?: 'Curated Bible study with Scripture, reflection questions, daily challenges, and completion tracking.');

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading-rich">
            <div>
                <p class="eyebrow">Bible Study</p>
                <h1><?= e((string) $study['title']); ?></h1>
                <p><?= e((string) ($study['summary'] ?? '')); ?></p>
            </div>
            <div class="quick-stat-row">
                <span class="quick-stat"><strong><?= e((string) ($study['duration_days'] ?? count($steps))); ?></strong><span>Days</span></span>
                <span class="quick-stat"><strong><?= e((string) count($steps)); ?></strong><span>Sections</span></span>
                <span class="quick-stat"><strong><?= e((string) $completedCount); ?></strong><span>Completed</span></span>
            </div>
            <div class="inline-actions">
                <?php if ($enrollment !== null): ?>
                    <a class="button button-primary" href="<?= e(app_url('study-day.php?enrollment_id=' . (int) $enrollment['id'] . '&day=1')); ?>">Continue Study</a>
                <?php elseif ($user !== null): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="join">
                        <button class="button button-primary" type="submit">Join Study</button>
                    </form>
                <?php else: ?>
                    <a class="button button-primary" href="<?= e(app_url('login.php')); ?>">Sign In To Join</a>
                <?php endif; ?>
                <a class="button button-secondary" href="<?= e(app_url('studies.php')); ?>">Browse More</a>
            </div>
        </div>

        <?php if ($pageError !== null): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <?php if ($invite !== null): ?>
            <div class="flash flash-info">You were invited to join <?= e((string) $invite['study_title']); ?>.</div>
        <?php endif; ?>

        <?php if ($inviteLink !== null): ?>
            <section class="panel top-gap">
                <h2>Invite link ready</h2>
                <p class="muted-copy">Share this link with a friend.</p>
                <input type="text" readonly value="<?= e($inviteLink); ?>">
            </section>
        <?php endif; ?>

        <div class="two-column top-gap">
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>Study Path</h2>
                        <p class="muted-copy">Each day includes Scripture, questions, a challenge, and optional teaching video.</p>
                    </div>
                </div>
                <div class="study-step-list">
                    <?php foreach ($steps as $step): ?>
                        <?php $progress = $progressMap[(int) $step['id']] ?? null; ?>
                        <article class="list-card list-card-block">
                            <div class="planner-item-body">
                                <div class="planner-item-header">
                                    <div>
                                        <strong>Day <?= e((string) $step['day_number']); ?>: <?= e((string) $step['title']); ?></strong>
                                        <p class="muted-copy"><?= e((string) ($step['section_title'] ?? 'Daily Study')); ?></p>
                                    </div>
                                    <span class="pill"><?= !empty($progress['completed_at']) ? 'Done' : 'Open'; ?></span>
                                </div>
                                <?php if ($enrollment !== null): ?>
                                    <div class="top-gap-sm">
                                        <a class="button button-secondary" href="<?= e(app_url('study-day.php?enrollment_id=' . (int) $enrollment['id'] . '&day=' . (int) $step['day_number'])); ?>">Open Day</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <aside class="stack-list">
                <section class="panel">
                    <h2>About this study</h2>
                    <p><?= nl2br(e((string) ($study['description'] ?? ''))); ?></p>
                    <?php if (!empty($enrollment['completed_at'])): ?>
                        <div class="flash flash-success">Completion badge earned.</div>
                    <?php endif; ?>
                </section>

                <?php if ($enrollment !== null): ?>
                    <section class="panel">
                        <h2>Invite friends</h2>
                        <p class="muted-copy">Create a share link for this study.</p>
                        <form class="form-stack" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="invite">
                            <label>
                                Friend email optional
                                <input type="email" name="recipient_email" placeholder="friend@example.com">
                            </label>
                            <button class="button button-primary" type="submit">Create Invite</button>
                        </form>
                    </section>
                <?php endif; ?>
            </aside>
        </div>

        <?php if ($enrollment !== null): ?>
            <section class="panel top-gap" id="discussion">
                <div class="panel-heading">
                    <div>
                        <h2>Discussion Board</h2>
                        <p class="muted-copy">Share what stood out, ask questions, and encourage everyone in the study.</p>
                    </div>
                </div>
                <form class="form-stack" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="discussion">
                    <label>
                        Message
                        <textarea name="message" rows="4" maxlength="2000" required></textarea>
                    </label>
                    <button class="button button-primary" type="submit">Post Message</button>
                </form>

                <div class="stack-list top-gap">
                    <?php if ($messages === []): ?>
                        <p class="empty-state">No discussion yet. Start the conversation.</p>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <article class="list-card list-card-block">
                                <div class="planner-item-header">
                                    <strong><?= e((string) ($message['sender_name'] ?? 'Member')); ?></strong>
                                    <span class="muted-copy"><?= e(date('M j, Y g:i A', strtotime((string) $message['created_at']))); ?></span>
                                </div>
                                <p><?= nl2br(e((string) $message['message_text'])); ?></p>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

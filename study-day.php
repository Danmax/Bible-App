<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Study Day';
$activePage = 'studies';
$user = refresh_current_user();
$pageError = null;

if ($user === null) {
    set_flash('Sign in again to continue.', 'warning');
    redirect('login.php');
}

if (!curated_studies_available()) {
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

$enrollmentId = (int) ($_GET['enrollment_id'] ?? $_POST['enrollment_id'] ?? 0);
$dayNumber = max(1, (int) ($_GET['day'] ?? $_POST['day'] ?? 1));
$enrollment = $enrollmentId > 0 ? fetch_study_enrollment_by_id($enrollmentId, (int) $user['id']) : null;

if ($enrollment === null) {
    set_flash('Join a study before opening a daily step.', 'warning');
    redirect('studies.php');
}

$study = fetch_study_by_id((int) $enrollment['study_id']);
$step = $study !== null ? fetch_study_step_by_day((int) $study['id'], $dayNumber) : null;

if ($study === null || $step === null) {
    set_flash('That study day could not be found.', 'warning');
    redirect('studies.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $action = trim((string) ($_POST['action'] ?? 'save'));
        $reflection = trim((string) ($_POST['reflection_response'] ?? ''));
        $challengeCompleted = trim((string) ($_POST['challenge_completed'] ?? '0')) === '1';
        $completeStep = $action === 'complete';
        $completedItemIds = array_map('intval', (array) ($_POST['completed_item_ids'] ?? []));

        if ($completeStep && $reflection === '') {
            throw new RuntimeException('Write a reflection before completing this day.');
        }

        if (study_items_available()) {
            sync_study_item_progress((int) $enrollment['id'], $completedItemIds);
        }

        upsert_step_progress(
            (int) $enrollment['id'],
            (int) $step['id'],
            $reflection,
            $challengeCompleted,
            $completeStep,
            (string) $step['video_unlock_rule']
        );

        refresh_study_completion((int) $enrollment['id'], (int) $study['id'], (int) $user['id']);
        set_flash($completeStep ? 'Study day completed.' : 'Study progress saved.', 'success');
        redirect('study-day.php?enrollment_id=' . (int) $enrollment['id'] . '&day=' . $dayNumber . ($completeStep ? '&completed=1' : ''));
    } catch (Throwable $exception) {
        $pageError = $exception instanceof RuntimeException ? $exception->getMessage() : 'Study progress could not be saved.';
    }
}

$progress = fetch_step_progress((int) $enrollment['id'], (int) $step['id']);
$itemProgressMap = fetch_enrollment_item_progress_map((int) $enrollment['id']);
$allSteps = fetch_study_steps((int) $study['id']);
$prevDay = $dayNumber > 1 ? $dayNumber - 1 : null;
$nextDay = $dayNumber < count($allSteps) ? $dayNumber + 1 : null;
$reflectionValue = (string) ($progress['reflection_response'] ?? '');
$challengeDone = !empty($progress['challenge_completed_at']);
$completeDone = !empty($progress['completed_at']);
$videoRule = (string) ($step['video_unlock_rule'] ?? 'after_step');
$videoUnlocked = !empty($progress['video_unlocked_at'])
    || study_step_video_should_unlock($videoRule, $reflectionValue, $challengeDone, $completeDone);
$showCompletionAnimation = $completeDone && (int) ($_GET['completed'] ?? 0) === 1;
$pageTitle = (string) $study['title'] . ' Day ' . $dayNumber;

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading-rich">
            <div>
                <p class="eyebrow">Day <?= e((string) $dayNumber); ?> of <?= e((string) count($allSteps)); ?></p>
                <h1><?= e((string) $step['title']); ?></h1>
                <p><?= e((string) $study['title']); ?></p>
            </div>
            <div class="inline-actions">
                <a class="button button-secondary" href="<?= e(app_url('study.php?slug=' . urlencode((string) $study['slug']))); ?>">Study Overview</a>
                <?php if ($prevDay !== null): ?>
                    <a class="button button-secondary" href="<?= e(app_url('study-day.php?enrollment_id=' . (int) $enrollment['id'] . '&day=' . $prevDay)); ?>">Previous Day</a>
                <?php endif; ?>
                <?php if ($nextDay !== null): ?>
                    <a class="button button-secondary" href="<?= e(app_url('study-day.php?enrollment_id=' . (int) $enrollment['id'] . '&day=' . $nextDay)); ?>">Next Day</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($pageError !== null): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <?php if ($showCompletionAnimation): ?>
            <div class="study-day-completion" role="status">
                <span class="study-complete-mark is-complete is-large" aria-hidden="true"></span>
                <strong>Day complete</strong>
            </div>
        <?php endif; ?>

        <div class="two-column top-gap">
            <section class="panel">
                <p class="eyebrow"><?= e((string) ($step['section_title'] ?? 'Daily Study')); ?></p>
                <h2>Scripture and study</h2>
                <p><?= nl2br(e((string) ($step['content'] ?? ''))); ?></p>

                <?php if (($step['items'] ?? []) !== []): ?>
                    <div class="study-item-path top-gap">
                        <?php $previousItemComplete = true; ?>
                        <?php foreach ($step['items'] as $item): ?>
                            <?php
                            $itemId = (int) $item['id'];
                            $itemDone = !empty($itemProgressMap[$itemId]['completed_at']) || $completeDone;
                            $locked = (string) ($item['unlock_rule'] ?? 'none') === 'after_previous' && !$previousItemComplete;
                            $previousItemComplete = $itemDone;
                            ?>
                            <article class="study-day-item <?= $locked ? 'is-locked' : ''; ?> <?= $itemDone ? 'is-complete' : ''; ?>">
                                <div class="planner-item-header">
                                    <div>
                                        <span class="pill"><?= e(ucwords(str_replace('_', ' ', (string) ($item['item_type'] ?? 'devotional')))); ?></span>
                                        <h3><?= e((string) ($item['title'] ?? 'Study item')); ?></h3>
                                    </div>
                                    <label class="study-item-check">
                                        <input type="checkbox" form="study-progress-form" name="completed_item_ids[]" value="<?= e((string) $itemId); ?>" <?= $itemDone ? 'checked' : ''; ?> <?= $locked ? 'disabled' : ''; ?>>
                                        <span class="study-complete-mark <?= $itemDone ? 'is-complete' : ''; ?>" aria-hidden="true"></span>
                                    </label>
                                </div>
                                <?php if ($locked): ?>
                                    <p class="muted-copy">Complete the previous item to unlock this one.</p>
                                <?php else: ?>
                                    <?php if ((string) ($item['item_type'] ?? '') === 'image' && trim((string) ($item['resource_url'] ?? '')) !== ''): ?>
                                        <img class="study-reflection-image" src="<?= e((string) $item['resource_url']); ?>" alt="">
                                    <?php elseif ((string) ($item['item_type'] ?? '') === 'video' && trim((string) ($item['resource_url'] ?? '')) !== ''): ?>
                                        <a class="button button-secondary button-with-icon" href="<?= e((string) $item['resource_url']); ?>" target="_blank" rel="noopener"><span aria-hidden="true">></span><span>Open Video</span></a>
                                    <?php endif; ?>
                                    <?php if (trim((string) ($item['bible_reference'] ?? '')) !== ''): ?>
                                        <a class="pill" href="<?= e(app_url('bible.php?q=' . urlencode((string) $item['bible_reference']))); ?>"><?= e((string) $item['bible_reference']); ?></a>
                                    <?php endif; ?>
                                    <?php if (trim((string) ($item['body'] ?? '')) !== ''): ?>
                                        <p><?= nl2br(e((string) $item['body'])); ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (($step['verses'] ?? []) !== []): ?>
                    <div class="top-gap">
                        <h3>Verses</h3>
                        <div class="inline-actions">
                            <?php foreach ($step['verses'] as $verse): ?>
                                <a class="pill" href="<?= e(app_url('bible.php?q=' . urlencode((string) $verse['reference_text']))); ?>"><?= e((string) $verse['reference_text']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (($step['questions'] ?? []) !== []): ?>
                    <div class="top-gap">
                        <h3>Questions to reflect</h3>
                        <ol class="check-list">
                            <?php foreach ($step['questions'] as $question): ?>
                                <li><?= e((string) $question['question_text']); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>

                <?php if (($step['challenges'] ?? []) !== []): ?>
                    <div class="top-gap">
                        <h3>Daily challenge</h3>
                        <ol class="check-list">
                            <?php foreach ($step['challenges'] as $challenge): ?>
                                <li><?= e((string) $challenge['challenge_text']); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>

                <form class="form-stack" method="post" id="study-progress-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="enrollment_id" value="<?= e((string) $enrollment['id']); ?>">
                    <input type="hidden" name="day" value="<?= e((string) $dayNumber); ?>">
                    <label>
                        Your reflection
                        <textarea name="reflection_response" rows="6" required><?= e($reflectionValue); ?></textarea>
                    </label>
                    <label class="sermon-checkbox-field">
                        <input type="checkbox" name="challenge_completed" value="1" <?= $challengeDone ? 'checked' : ''; ?>>
                        I completed today&apos;s challenge
                    </label>
                    <div class="inline-actions">
                        <button class="button button-secondary button-with-icon" type="submit" name="action" value="save"><span aria-hidden="true">+</span><span>Save</span></button>
                        <button class="button button-primary button-with-icon" type="submit" name="action" value="complete"><span class="study-complete-mark is-button" aria-hidden="true"></span><span>Complete Day</span></button>
                    </div>
                </form>
            </section>

            <aside class="stack-list">
                <section class="panel">
                    <h2>Status</h2>
                    <div class="quick-stat-row">
                        <span class="quick-stat"><strong><?= $completeDone ? 'Yes' : 'No'; ?></strong><span>Completed</span></span>
                        <span class="quick-stat"><strong><?= $challengeDone ? 'Yes' : 'No'; ?></strong><span>Challenge</span></span>
                    </div>
                    <?php if (!empty($enrollment['completed_at'])): ?>
                        <div class="flash flash-success">Study complete. Badge earned.</div>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <h2><?= e((string) ($step['video_title'] ?: 'Teaching Video')); ?></h2>
                    <?php if (trim((string) ($step['youtube_video_id'] ?? '')) === ''): ?>
                        <p class="muted-copy">No video has been added for this day.</p>
                    <?php elseif ($videoUnlocked): ?>
                        <div class="study-video-frame">
                            <iframe
                                src="https://www.youtube-nocookie.com/embed/<?= e((string) $step['youtube_video_id']); ?>"
                                title="<?= e((string) ($step['video_title'] ?: 'Teaching Video')); ?>"
                                loading="lazy"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen
                            ></iframe>
                        </div>
                    <?php else: ?>
                        <div class="locked-video">
                            <span class="pill">Locked</span>
                            <p>Complete the required step action to unlock this video.</p>
                            <p class="muted-copy">
                                <?php if ($videoRule === 'after_reflection'): ?>
                                    Write and save your reflection.
                                <?php elseif ($videoRule === 'after_challenge'): ?>
                                    Mark the daily challenge complete.
                                <?php else: ?>
                                    Complete the full day.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

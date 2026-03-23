<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/openai.php';

require_login();

$pageTitle = 'Prayer Requests';
$activePage = 'prayer';
$user = refresh_current_user();
$pageError = null;
$prayerError = null;
$prayerEntries = [];
$prayerForm = [
    'title' => '',
    'details' => '',
    'status' => 'active',
];
$prayerAiEnabled = openai_event_drafts_enabled();

if ($user === null) {
    set_flash('Sign in again to continue.', 'warning');
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = trim((string) ($_POST['action'] ?? ''));

    try {
        if ($action === 'create-prayer') {
            $prayerForm['title'] = trim((string) ($_POST['title'] ?? ''));
            $prayerForm['details'] = trim((string) ($_POST['details'] ?? ''));
            $prayerForm['status'] = trim((string) ($_POST['status'] ?? 'active'));

            if ($prayerForm['title'] === '') {
                throw new RuntimeException('Prayer request title is required.');
            }

            if (!in_array($prayerForm['status'], ['active', 'answered'], true)) {
                $prayerForm['status'] = 'active';
            }

            create_prayer_entry_record((int) $user['id'], $prayerForm['title'], $prayerForm['details'], $prayerForm['status']);
            set_flash('Prayer request saved.', 'success');
            redirect('prayer.php');
        }

        if ($action === 'mark-prayer-answered') {
            update_prayer_entry_status((int) ($_POST['entry_id'] ?? 0), (int) $user['id'], 'answered');
            set_flash('Prayer request marked as answered.', 'success');
            redirect('prayer.php');
        }

        if ($action === 'reopen-prayer') {
            update_prayer_entry_status((int) ($_POST['entry_id'] ?? 0), (int) $user['id'], 'active');
            set_flash('Prayer request moved back to active.', 'success');
            redirect('prayer.php');
        }

        if ($action === 'delete-prayer') {
            delete_prayer_entry_record((int) ($_POST['entry_id'] ?? 0), (int) $user['id']);
            set_flash('Prayer request deleted.', 'success');
            redirect('prayer.php');
        }
    } catch (Throwable $exception) {
        $prayerError = $exception->getMessage();
    }
}

try {
    $prayerEntries = fetch_prayer_entries_for_user((int) $user['id'], 12);
} catch (Throwable $exception) {
    $pageError = 'Prayer requests could not be loaded because the database is unavailable.';
}

$activePrayerCount = count(array_filter(
    $prayerEntries,
    static fn(array $entry): bool => (string) ($entry['status'] ?? 'active') === 'active'
));
$answeredPrayerCount = count(array_filter(
    $prayerEntries,
    static fn(array $entry): bool => (string) ($entry['status'] ?? '') === 'answered'
));

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Prayer Requests</p>
                <h1>Capture burdens, answers, and ongoing prayer needs</h1>
                <p>Speak a request, let the model shape it, and keep active and answered prayers in one place.</p>
            </div>

            <div class="quick-stat-row">
                <div class="quick-stat">
                    <strong><?= e((string) count($prayerEntries)); ?></strong>
                    <span>total requests</span>
                </div>
                <div class="quick-stat">
                    <strong><?= e((string) $activePrayerCount); ?></strong>
                    <span>active prayers</span>
                </div>
                <div class="quick-stat">
                    <strong><?= e((string) $answeredPrayerCount); ?></strong>
                    <span>answered prayers</span>
                </div>
            </div>

            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(app_url('good-news.php')); ?>">Open Good News</a>
                <a class="button button-secondary" href="<?= e(app_url('notes.php')); ?>">Open Notes</a>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="two-column top-gap">
            <div class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>New prayer request</h2>
                        <p class="muted-copy">Use voice and the model to draft a request, then save it for ongoing prayer.</p>
                    </div>
                    <span class="mini-card"><?= e($prayerAiEnabled ? strtoupper(openai_event_model()) : 'AI OFF'); ?></span>
                </div>

                <?php if ($prayerError): ?>
                    <div class="flash flash-warning top-gap-sm"><?= e($prayerError); ?></div>
                <?php endif; ?>

                <div
                    class="community-ai-panel planner-ai-panel top-gap-sm"
                    data-ai-event-builder
                    data-ai-endpoint="<?= e(app_url('prayer-ai-draft.php')); ?>"
                >
                    <div class="panel-heading">
                        <div>
                            <h3>Prayer draft</h3>
                            <p class="muted-copy">Speak or type a prayer need and let the model shape it into a request you can save.</p>
                        </div>
                    </div>

                    <label>
                        Prompt
                        <textarea rows="3" placeholder="Please pray for wisdom, healing, and peace for our family this week." data-ai-prompt></textarea>
                    </label>

                    <div class="inline-actions">
                        <button class="button button-secondary" type="button" data-ai-voice-start <?= $prayerAiEnabled ? '' : 'disabled'; ?>>Start Voice</button>
                        <button class="button button-secondary" type="button" data-ai-voice-stop hidden>Stop</button>
                        <button class="button button-primary" type="button" data-ai-generate <?= $prayerAiEnabled ? '' : 'disabled'; ?>>Create Draft</button>
                    </div>

                    <p class="muted-copy" data-ai-status>
                        <?= $prayerAiEnabled
                            ? 'Ready to draft a prayer request.'
                            : 'Add OPENAI_API_KEY to enable prayer drafting.'; ?>
                    </p>
                </div>

                <form class="form-stack compact-form" method="post" data-ai-event-form>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="create-prayer">

                    <label>
                        <span>Title</span>
                        <input type="text" name="title" value="<?= e($prayerForm['title']); ?>" placeholder="Healing and peace for our family" required data-ai-field="title">
                    </label>

                    <label>
                        <span>Details</span>
                        <textarea name="details" rows="6" placeholder="Share the burden, need, or praise report..." data-ai-field="details"><?= e($prayerForm['details']); ?></textarea>
                    </label>

                    <label>
                        <span>Status</span>
                        <select name="status" data-ai-field="status">
                            <option value="active" <?= $prayerForm['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="answered" <?= $prayerForm['status'] === 'answered' ? 'selected' : ''; ?>>Answered</option>
                        </select>
                    </label>

                    <div class="inline-actions">
                        <button class="button button-primary" type="submit">Save Prayer Request</button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>Your prayer list</h2>
                        <p class="muted-copy">Keep active requests visible and move them into answered testimony when the Lord provides.</p>
                    </div>
                </div>

                <div class="stack-list top-gap-sm">
                    <?php if ($prayerEntries === []): ?>
                        <article class="good-news-spotlight">
                            <span class="pill">Prayer</span>
                            <strong>Your saved prayer requests will show here</strong>
                            <p>Use the form to capture a prayer need, then keep it active or mark it answered later.</p>
                        </article>
                    <?php else: ?>
                        <?php foreach ($prayerEntries as $entry): ?>
                            <article class="list-card list-card-block">
                                <div>
                                    <span class="pill <?= (string) $entry['status'] === 'answered' ? 'pill-dark' : ''; ?>"><?= e(ucfirst((string) $entry['status'])); ?></span>
                                    <strong><?= e((string) $entry['title']); ?></strong>
                                    <?php if (!empty($entry['details'])): ?>
                                        <span><?= e((string) $entry['details']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="inline-actions top-gap-sm">
                                    <?php if ((string) $entry['status'] === 'active'): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="mark-prayer-answered">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $entry['id']); ?>">
                                            <button class="button button-secondary" type="submit">Mark Answered</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="reopen-prayer">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $entry['id']); ?>">
                                            <button class="button button-secondary" type="submit">Reopen</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="delete-prayer">
                                        <input type="hidden" name="entry_id" value="<?= e((string) $entry['id']); ?>">
                                        <button class="button button-secondary" type="submit">Delete</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

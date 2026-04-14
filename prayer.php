<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

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
                <p>Keep active and answered prayer requests in one place so you can return to them with clarity and faith.</p>
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
                        <p class="muted-copy">Write it out or tap the mic, then save it.</p>
                    </div>
                </div>

                <?php if ($prayerError): ?>
                    <div class="flash flash-warning top-gap-sm"><?= e($prayerError); ?></div>
                <?php endif; ?>

                <form class="form-stack compact-form top-gap-sm" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="create-prayer">

                    <label>
                        <span>Title</span>
                        <input type="text" name="title" value="<?= e($prayerForm['title']); ?>" placeholder="Healing and peace for our family" required>
                    </label>

                    <label>
                        <span>Details</span>
                        <textarea name="details" rows="6" placeholder="Share the burden, need, or praise report..."><?= e($prayerForm['details']); ?></textarea>
                        <div class="inline-actions top-gap-sm" data-voice-compose data-voice-compose-max-seconds="30">
                            <button class="button button-secondary voice-search-button" type="button" data-voice-compose-start aria-label="Speak your prayer request details">
                                <svg class="voice-search-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M12 4a3 3 0 0 1 3 3v5a3 3 0 0 1-6 0V7a3 3 0 0 1 3-3Z" />
                                    <path d="M19 11a7 7 0 0 1-14 0" />
                                    <path d="M12 18v3" />
                                    <path d="M9 21h6" />
                                </svg>
                            </button>
                            <span class="muted-copy" data-voice-compose-status>Tap mic. 30s max.</span>
                        </div>
                    </label>

                    <label>
                        <span>Status</span>
                        <select name="status">
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

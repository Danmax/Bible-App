<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Saved Verses';
$activePage = 'bookmarks';
$user = refresh_current_user();
$pageError = null;
$bookmarks = [];

if ($user === null) {
    set_flash('Sign in again to continue.', 'warning');
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'update') {
            update_bookmark_record(
                (int) ($_POST['bookmark_id'] ?? 0),
                (int) $user['id'],
                trim($_POST['tag'] ?? ''),
                trim($_POST['note'] ?? ''),
                trim($_POST['highlight_color'] ?? '')
            );
            set_flash('Bookmark updated.', 'success');
        } elseif ($action === 'delete') {
            delete_bookmark_record((int) ($_POST['bookmark_id'] ?? 0), (int) $user['id']);
            set_flash('Bookmark removed.', 'success');
        }

        redirect('bookmarks.php');
    } catch (Throwable $exception) {
        $pageError = 'Bookmark changes could not be saved because the database is unavailable.';
    }
}

try {
    $bookmarks = fetch_bookmarks((int) $user['id']);
} catch (Throwable $exception) {
    $pageError = 'Saved verses could not be loaded because the database is unavailable.';
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Bookmarks</p>
                <h1>Your saved passages</h1>
                <p>Keep important verses with your own note and tag so you can find them later.</p>
            </div>

            <div class="quick-stat-row">
                <div class="quick-stat">
                    <strong><?= e((string) count($bookmarks)); ?></strong>
                    <span>saved verses</span>
                </div>
            </div>

            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(app_url('bible.php')); ?>">Find Scripture</a>
                <a class="button button-secondary" href="<?= e(app_url('notes.php')); ?>">Open Notes</a>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="card-grid card-grid-2 top-gap">
            <?php if ($bookmarks === []): ?>
                <article class="panel">
                    <h2>No bookmarks yet</h2>
                    <p>Open the Bible page, search for a verse, and save it here.</p>
                </article>
            <?php else: ?>
                <?php foreach ($bookmarks as $bookmark): ?>
                    <article class="bookmark-card bookmark-card-full">
                        <div class="bookmark-header">
                            <div>
                                <h3><?= e(format_verse_reference($bookmark)); ?></h3>
                                <p>
                                    <?php if (!empty($bookmark['selected_text'])): ?>
                                        <mark class="verse-highlight <?= e(highlight_class((string) ($bookmark['highlight_color'] ?? 'neon-yellow'))); ?>">
                                            <?= e((string) $bookmark['selected_text']); ?>
                                        </mark>
                                        <span class="muted-copy">from <?= e((string) $bookmark['verse_text']); ?></span>
                                    <?php else: ?>
                                        <?= e((string) $bookmark['verse_text']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="inline-actions">
                                <?php if (!empty($bookmark['tag'])): ?>
                                    <span class="pill"><?= e((string) $bookmark['tag']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($bookmark['highlight_color'])): ?>
                                    <span class="pill <?= e(highlight_class((string) $bookmark['highlight_color'])); ?>"><?= e((string) $bookmark['highlight_color']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form class="form-stack compact-form" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="bookmark_id" value="<?= e((string) $bookmark['id']); ?>">

                            <label>
                                <span>Tag</span>
                                <input type="text" name="tag" value="<?= e((string) ($bookmark['tag'] ?? '')); ?>" placeholder="Strength, prayer, wisdom">
                            </label>

                            <label>
                                <span>Personal note</span>
                                <textarea name="note" rows="3" placeholder="Why did you save this verse?"><?= e((string) ($bookmark['note'] ?? '')); ?></textarea>
                            </label>

                            <label>
                                <span>Highlight color</span>
                                <select name="highlight_color">
                                    <option value="">No color</option>
                                    <?php foreach (['neon-yellow', 'neon-green', 'neon-pink', 'neon-blue'] as $color): ?>
                                        <option value="<?= e($color); ?>" <?= ($bookmark['highlight_color'] ?? '') === $color ? 'selected' : ''; ?>>
                                            <?= e($color); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <div class="inline-actions">
                                <button class="button button-primary" type="submit">Save Changes</button>
                                <a class="button button-secondary" href="<?= e(app_url('bible.php?translation=' . urlencode((string) $bookmark['translation']) . '&book_id=' . $bookmark['book_id'] . '&chapter=' . $bookmark['chapter_number'] . '&verse=' . $bookmark['verse_number'])); ?>">Open in Reader</a>
                                <a class="button button-secondary" href="<?= e(app_url('notes.php?verse_id=' . $bookmark['verse_id'])); ?>">Add Note</a>
                            </div>
                        </form>

                        <form class="top-gap-sm" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="bookmark_id" value="<?= e((string) $bookmark['id']); ?>">
                            <button class="button button-secondary" type="submit">Remove Bookmark</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

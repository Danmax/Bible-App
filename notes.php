<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Study Notes';
$activePage = 'notes';
$user = refresh_current_user();
$pageError = null;
$notes = [];
$noteableVerses = [];
$editingNote = null;
$selectedVerse = null;
$linkedVerseOptions = [];
$form = [
    'title' => '',
    'content' => '',
    'verse_id' => '',
];

if ($user === null) {
    set_flash('Sign in again to continue.', 'warning');
    redirect('login.php');
}

try {
    $noteableVerses = fetch_noteable_verses((int) $user['id']);

    $requestedVerseId = (int) ($_GET['verse_id'] ?? 0);
    if ($requestedVerseId > 0) {
        $selectedVerse = fetch_verse_by_id($requestedVerseId);
        if ($selectedVerse) {
            $form['verse_id'] = (string) $requestedVerseId;
            $form['title'] = sprintf('Reflection on %s', format_verse_reference($selectedVerse));
        }
    }

    $editId = (int) ($_GET['edit'] ?? 0);
    if ($editId > 0) {
        $editingNote = fetch_note($editId, (int) $user['id']);
        if ($editingNote) {
            $form['title'] = (string) $editingNote['title'];
            $form['content'] = (string) $editingNote['content'];
            $form['verse_id'] = $editingNote['verse_id'] ? (string) $editingNote['verse_id'] : '';

            if ($editingNote['verse_id']) {
                $selectedVerse = fetch_verse_by_id((int) $editingNote['verse_id']);
            }
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Notes data could not be loaded because the database is unavailable.';
}

$linkedVerseOptions = $noteableVerses;

if ($selectedVerse !== null) {
    $alreadyIncluded = false;

    foreach ($linkedVerseOptions as $verseOption) {
        if ((int) $verseOption['id'] === (int) $selectedVerse['id']) {
            $alreadyIncluded = true;
            break;
        }
    }

    if (!$alreadyIncluded) {
        array_unshift($linkedVerseOptions, $selectedVerse);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? 'create';
    $form['title'] = trim($_POST['title'] ?? '');
    $form['content'] = trim($_POST['content'] ?? '');
    $form['verse_id'] = trim($_POST['verse_id'] ?? '');

    $verseId = $form['verse_id'] === '' ? null : (int) $form['verse_id'];

    if ($action === 'delete') {
        try {
            delete_note_record((int) ($_POST['note_id'] ?? 0), (int) $user['id']);
            set_flash('Note deleted.', 'success');
            redirect('notes.php');
        } catch (Throwable $exception) {
            $pageError = 'The note could not be deleted because the database is unavailable.';
        }
    } elseif ($form['title'] === '' || $form['content'] === '') {
        $pageError = 'Enter a title and note content.';
    } else {
        try {
            if ($action === 'update') {
                update_note_record(
                    (int) ($_POST['note_id'] ?? 0),
                    (int) $user['id'],
                    $form['title'],
                    $form['content'],
                    $verseId
                );
                set_flash('Note updated.', 'success');
            } else {
                create_note_record((int) $user['id'], $form['title'], $form['content'], $verseId);
                set_flash('Note saved.', 'success');
            }

            redirect('notes.php');
        } catch (Throwable $exception) {
            $pageError = 'The note could not be saved because the database is unavailable.';
        }
    }
}

try {
    $notes = fetch_notes((int) $user['id']);
} catch (Throwable $exception) {
    $pageError = $pageError ?? 'Notes could not be loaded because the database is unavailable.';
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Study Notes</p>
                <h1>Capture what you are learning</h1>
                <p>Write sermon reflections, verse observations, and personal study notes.</p>
            </div>

            <div class="quick-stat-row">
                <div class="quick-stat">
                    <strong><?= e((string) count($notes)); ?></strong>
                    <span>notes written</span>
                </div>
                <div class="quick-stat">
                    <strong><?= e((string) count($noteableVerses)); ?></strong>
                    <span>linkable verses</span>
                </div>
            </div>

            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(app_url('bible.php')); ?>">Open Bible</a>
                <a class="button button-secondary" href="<?= e(app_url('bookmarks.php')); ?>">Open Saved Verses</a>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="two-column">
            <div class="panel">
                <div class="panel-heading">
                    <div>
                        <h2><?= $editingNote ? 'Edit note' : 'New note'; ?></h2>
                        <p class="muted-copy">Create a focused study note and optionally connect it to a saved verse.</p>
                    </div>
                </div>

                <?php if ($selectedVerse): ?>
                    <div class="inline-message">
                        <strong>Linked verse</strong>
                        <p><?= e(format_verse_reference($selectedVerse)); ?></p>
                    </div>
                <?php endif; ?>

                <form class="form-stack" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="<?= $editingNote ? 'update' : 'create'; ?>">
                    <?php if ($editingNote): ?>
                        <input type="hidden" name="note_id" value="<?= e((string) $editingNote['id']); ?>">
                    <?php endif; ?>

                    <label>
                        <span>Attach to saved verse</span>
                        <select name="verse_id">
                            <option value="">No linked verse</option>
                            <?php foreach ($linkedVerseOptions as $verse): ?>
                                <option value="<?= e((string) $verse['id']); ?>" <?= $form['verse_id'] === (string) $verse['id'] ? 'selected' : ''; ?>>
                                    <?= e(format_verse_reference($verse)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Title</span>
                        <input type="text" name="title" value="<?= e($form['title']); ?>" placeholder="Romans study, Sunday sermon, prayer insight" required>
                    </label>

                    <label>
                        <span>Note</span>
                        <textarea name="content" rows="8" placeholder="Write what you are learning..." required><?= e($form['content']); ?></textarea>
                    </label>

                    <div class="inline-actions">
                        <button class="button button-primary" type="submit"><?= $editingNote ? 'Update Note' : 'Save Note'; ?></button>
                        <a class="button button-secondary" href="<?= e(app_url('bible.php')); ?>">Open Bible</a>
                    </div>
                </form>
            </div>

            <div class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>Your notes</h2>
                        <p class="muted-copy">Return to your recent reflections and continue building out your study archive.</p>
                    </div>
                </div>

                <?php if ($notes === []): ?>
                    <p class="empty-state">No notes yet. Create one from the form or jump in from a saved verse.</p>
                <?php else: ?>
                    <div class="stack-list">
                        <?php foreach ($notes as $note): ?>
                            <article class="note-card">
                                <div class="bookmark-header">
                                    <div>
                                        <h3><?= e((string) $note['title']); ?></h3>
                                        <?php if (!empty($note['book_name'])): ?>
                                            <p class="muted-copy"><?= e(format_verse_reference($note)); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <p><?= nl2br(e((string) $note['content'])); ?></p>

                                <div class="inline-actions">
                                    <a class="button button-secondary" href="<?= e(app_url('notes.php?edit=' . $note['id'])); ?>">Edit</a>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="note_id" value="<?= e((string) $note['id']); ?>">
                                        <button class="button button-secondary" type="submit">Delete</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

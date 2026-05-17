<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Library';
$activePage = 'library';
$pageDescription = 'Your saved Scripture, notes, sermon documents, and prayer reflections in one Bible-centered library.';
$user = refresh_current_user();
$pageError = null;
$formError = null;
$bookmarks = [];
$notes = [];
$sermonNotes = [];
$prayerEntries = [];
$noteableVerses = [];
$editingNote = null;
$sermonNotesEnabled = sermon_notes_available();
$libraryViews = [
    'overview' => 'Overview',
    'saved' => 'Saved Scripture',
    'notes' => 'Notes',
    'sermons' => 'Sermon Docs',
    'prayer' => 'Prayer',
];
$activeView = (string) ($_GET['view'] ?? 'overview');
$activeView = array_key_exists($activeView, $libraryViews) ? $activeView : 'overview';
$noteForm = [
    'title' => '',
    'content' => '',
    'verse_id' => '',
];
$prayerForm = [
    'title' => '',
    'details' => '',
    'status' => 'active',
];

if ($user === null) {
    set_flash('Sign in again to continue.', 'warning');
    redirect('login.php');
}

try {
    $noteableVerses = fetch_noteable_verses((int) $user['id']);

    $editNoteId = (int) ($_GET['edit_note'] ?? 0);
    if ($editNoteId > 0) {
        $editingNote = fetch_note($editNoteId, (int) $user['id']);
        if ($editingNote !== null) {
            $activeView = 'notes';
            $noteForm['title'] = (string) $editingNote['title'];
            $noteForm['content'] = (string) $editingNote['content'];
            $noteForm['verse_id'] = $editingNote['verse_id'] ? (string) $editingNote['verse_id'] : '';
        }
    }

    $requestedVerseId = (int) ($_GET['verse_id'] ?? 0);
    if ($requestedVerseId > 0 && $editingNote === null) {
        $selectedVerse = fetch_verse_by_id($requestedVerseId);
        if ($selectedVerse !== null) {
            $activeView = 'notes';
            $noteForm['verse_id'] = (string) $requestedVerseId;
            $noteForm['title'] = sprintf('Reflection on %s', format_verse_reference($selectedVerse));
            array_unshift($noteableVerses, $selectedVerse);
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Your note options could not be loaded right now.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'update-bookmark') {
            update_bookmark_record(
                (int) ($_POST['bookmark_id'] ?? 0),
                (int) $user['id'],
                trim((string) ($_POST['tag'] ?? '')),
                trim((string) ($_POST['note'] ?? '')),
                trim((string) ($_POST['highlight_color'] ?? ''))
            );
            set_flash('Saved Scripture updated.', 'success');
            redirect('library.php?view=saved');
        }

        if ($action === 'delete-bookmark') {
            delete_bookmark_record((int) ($_POST['bookmark_id'] ?? 0), (int) $user['id']);
            set_flash('Saved Scripture removed.', 'success');
            redirect('library.php?view=saved');
        }

        if ($action === 'create-note' || $action === 'update-note') {
            $noteForm['title'] = trim((string) ($_POST['title'] ?? ''));
            $noteForm['content'] = trim((string) ($_POST['content'] ?? ''));
            $noteForm['verse_id'] = trim((string) ($_POST['verse_id'] ?? ''));
            $verseId = $noteForm['verse_id'] === '' ? null : (int) $noteForm['verse_id'];

            if ($noteForm['title'] === '' || $noteForm['content'] === '') {
                throw new RuntimeException('Enter a title and note content.');
            }

            if ($action === 'update-note') {
                update_note_record(
                    (int) ($_POST['note_id'] ?? 0),
                    (int) $user['id'],
                    $noteForm['title'],
                    $noteForm['content'],
                    $verseId
                );
                set_flash('Note updated.', 'success');
            } else {
                create_note_record((int) $user['id'], $noteForm['title'], $noteForm['content'], $verseId);
                set_flash('Note saved.', 'success');
            }

            redirect('library.php?view=notes');
        }

        if ($action === 'delete-note') {
            delete_note_record((int) ($_POST['note_id'] ?? 0), (int) $user['id']);
            set_flash('Note deleted.', 'success');
            redirect('library.php?view=notes');
        }

        if ($action === 'create-prayer') {
            $prayerForm['title'] = trim((string) ($_POST['title'] ?? ''));
            $prayerForm['details'] = trim((string) ($_POST['details'] ?? ''));
            $prayerForm['status'] = in_array((string) ($_POST['status'] ?? 'active'), ['active', 'answered'], true)
                ? (string) $_POST['status']
                : 'active';

            if ($prayerForm['title'] === '') {
                throw new RuntimeException('Enter a prayer title.');
            }

            create_prayer_entry_record((int) $user['id'], $prayerForm['title'], $prayerForm['details'], $prayerForm['status']);
            set_flash('Prayer request saved.', 'success');
            redirect('library.php?view=prayer');
        }

        if ($action === 'mark-prayer-answered' || $action === 'reopen-prayer') {
            update_prayer_entry_status(
                (int) ($_POST['entry_id'] ?? 0),
                (int) $user['id'],
                $action === 'mark-prayer-answered' ? 'answered' : 'active'
            );
            set_flash($action === 'mark-prayer-answered' ? 'Prayer marked answered.' : 'Prayer reopened.', 'success');
            redirect('library.php?view=prayer');
        }

        if ($action === 'delete-prayer') {
            delete_prayer_entry_record((int) ($_POST['entry_id'] ?? 0), (int) $user['id']);
            set_flash('Prayer request deleted.', 'success');
            redirect('library.php?view=prayer');
        }
    } catch (Throwable $exception) {
        $formError = $exception instanceof RuntimeException ? $exception->getMessage() : 'Your library update could not be saved.';
        $activeView = match ($action) {
            'update-bookmark', 'delete-bookmark' => 'saved',
            'create-note', 'update-note', 'delete-note' => 'notes',
            'create-prayer', 'mark-prayer-answered', 'reopen-prayer', 'delete-prayer' => 'prayer',
            default => $activeView,
        };
    }
}

try {
    $bookmarks = fetch_bookmarks((int) $user['id']);
    $notes = fetch_notes((int) $user['id']);
    $prayerEntries = fetch_prayer_entries_for_user((int) $user['id'], 50);

    if ($sermonNotesEnabled) {
        $sermonNotes = fetch_sermon_notes((int) $user['id']);
    }
} catch (Throwable $exception) {
    $pageError = $pageError ?? 'Your library is available, but some saved content could not be loaded right now.';
}

$recentBookmarks = array_slice($bookmarks, 0, 3);
$recentNotes = array_slice($notes, 0, 3);
$recentSermonNotes = array_slice($sermonNotes, 0, 3);
$activePrayerEntries = array_values(array_filter(
    $prayerEntries,
    static fn(array $entry): bool => (string) ($entry['status'] ?? 'active') === 'active'
));

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Library</p>
                <h1>Your Scripture workspace</h1>
                <p>Saved verses, highlights, notes, sermon documents, and prayer reflections gathered around the Bible.</p>
            </div>

            <div class="quick-stat-row">
                <div class="quick-stat">
                    <strong><?= e((string) count($bookmarks)); ?></strong>
                    <span>saved passages</span>
                </div>
                <div class="quick-stat">
                    <strong><?= e((string) count($notes)); ?></strong>
                    <span>study notes</span>
                </div>
                <div class="quick-stat">
                    <strong><?= e((string) count($sermonNotes)); ?></strong>
                    <span>sermon docs</span>
                </div>
                <div class="quick-stat">
                    <strong><?= e((string) count($activePrayerEntries)); ?></strong>
                    <span>active prayers</span>
                </div>
            </div>

            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(app_url('bible.php')); ?>">Open Bible</a>
                <a class="button button-secondary" href="<?= e(app_url('library.php?view=notes')); ?>">Write Note</a>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <?php if ($formError): ?>
            <div class="flash flash-warning"><?= e($formError); ?></div>
        <?php endif; ?>

        <nav class="filter-row top-gap" aria-label="Library views">
            <?php foreach ($libraryViews as $viewKey => $viewLabel): ?>
                <a class="filter-chip <?= $activeView === $viewKey ? 'is-active' : ''; ?>" href="<?= e(app_url('library.php?view=' . urlencode($viewKey))); ?>">
                    <?= e($viewLabel); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($activeView === 'overview'): ?>
            <div class="card-grid card-grid-4 top-gap">
                <?php foreach ([
                    ['view' => 'saved', 'title' => 'Saved Scripture', 'copy' => 'Highlights, bookmarks, tags, and short verse notes from the reader.'],
                    ['view' => 'notes', 'title' => 'Study Notes', 'copy' => 'Personal observations, reflections, and verse-linked writing.'],
                    ['view' => 'sermons', 'title' => 'Sermon Docs', 'copy' => 'Teaching notes with citations, reference groups, summaries, and share links.'],
                    ['view' => 'prayer', 'title' => 'Prayer', 'copy' => 'Active needs and answered prayers connected to your Scripture rhythm.'],
                ] as $card): ?>
                    <article class="feature-card feature-card-new">
                        <h2><?= e($card['title']); ?></h2>
                        <p><?= e($card['copy']); ?></p>
                        <a class="button button-secondary" href="<?= e(app_url('library.php?view=' . $card['view'])); ?>">Open</a>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="two-column top-gap">
                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Recent Scripture</p>
                            <h2>Saved passages</h2>
                        </div>
                        <a class="button button-secondary" href="<?= e(app_url('library.php?view=saved')); ?>">View All</a>
                    </div>

                    <div class="stack-list top-gap-sm">
                        <?php if ($recentBookmarks === []): ?>
                            <p class="empty-state">Save or highlight a verse in the Bible reader to start your library.</p>
                        <?php else: ?>
                            <?php foreach ($recentBookmarks as $bookmark): ?>
                                <article class="list-card list-card-block">
                                    <div>
                                        <strong><?= e(format_verse_reference($bookmark)); ?></strong>
                                        <span><?= e(truncate_text((string) $bookmark['verse_text'], 130)); ?></span>
                                    </div>
                                    <a class="button button-secondary" href="<?= e(library_reader_url($bookmark)); ?>">Open</a>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Recent Writing</p>
                            <h2>Notes and sermons</h2>
                        </div>
                        <a class="button button-secondary" href="<?= e(app_url('library.php?view=notes')); ?>">Write</a>
                    </div>

                    <div class="stack-list top-gap-sm">
                        <?php if ($recentNotes === [] && $recentSermonNotes === []): ?>
                            <p class="empty-state">Notes linked to Scripture will appear here.</p>
                        <?php endif; ?>

                        <?php foreach ($recentNotes as $note): ?>
                            <article class="list-card list-card-block">
                                <div>
                                    <span class="pill">Study Note</span>
                                    <strong><?= e((string) $note['title']); ?></strong>
                                    <span><?= e(truncate_text((string) $note['content'], 130)); ?></span>
                                </div>
                                <a class="button button-secondary" href="<?= e(app_url('library.php?view=notes&edit_note=' . (int) $note['id'])); ?>">Open</a>
                            </article>
                        <?php endforeach; ?>

                        <?php foreach ($recentSermonNotes as $note): ?>
                            <article class="list-card list-card-block">
                                <div>
                                    <span class="pill">Sermon Doc</span>
                                    <strong><?= e((string) $note['title']); ?></strong>
                                    <span><?= e(truncate_text((string) ($note['content_excerpt'] ?? ''), 130)); ?></span>
                                </div>
                                <a class="button button-secondary" href="<?= e(app_url('sermon-notes.php?note=' . (int) $note['id'])); ?>">Open</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        <?php elseif ($activeView === 'saved'): ?>
            <section class="panel top-gap">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Saved Scripture</p>
                        <h2>Highlights and bookmarks</h2>
                        <p class="muted-copy">Edit tags and notes here, or open the passage in the reader.</p>
                    </div>
                    <a class="button button-primary" href="<?= e(app_url('bible.php')); ?>">Find Scripture</a>
                </div>

                <div class="card-grid card-grid-2 top-gap-sm">
                    <?php if ($bookmarks === []): ?>
                        <article class="list-card list-card-block">
                            <strong>No saved Scripture yet</strong>
                            <span>Open the Bible reader, select a verse, and save or highlight it.</span>
                        </article>
                    <?php else: ?>
                        <?php foreach ($bookmarks as $bookmark): ?>
                            <article class="bookmark-card bookmark-card-full">
                                <div class="bookmark-verse">
                                    <h3><?= e(format_verse_reference($bookmark)); ?></h3>
                                    <p><?= e((string) ($bookmark['selected_text'] ?: $bookmark['verse_text'])); ?></p>
                                </div>
                                <form class="form-stack compact-form" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="update-bookmark">
                                    <input type="hidden" name="bookmark_id" value="<?= e((string) $bookmark['id']); ?>">
                                    <label>
                                        <span>Tag</span>
                                        <input type="text" name="tag" value="<?= e((string) ($bookmark['tag'] ?? '')); ?>" placeholder="Strength, prayer, wisdom">
                                    </label>
                                    <label>
                                        <span>Note</span>
                                        <textarea name="note" rows="3" placeholder="Why did you save this verse?"><?= e((string) ($bookmark['note'] ?? '')); ?></textarea>
                                    </label>
                                    <label>
                                        <span>Highlight</span>
                                        <select name="highlight_color">
                                            <option value="">No color</option>
                                            <?php foreach (['neon-yellow', 'neon-green', 'neon-pink', 'neon-blue', 'neon-orange'] as $color): ?>
                                                <option value="<?= e($color); ?>" <?= ($bookmark['highlight_color'] ?? '') === $color ? 'selected' : ''; ?>>
                                                    <?= e($color); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <div class="inline-actions">
                                        <button class="button button-primary" type="submit">Save</button>
                                        <a class="button button-secondary" href="<?= e(library_reader_url($bookmark)); ?>">Open</a>
                                        <a class="button button-secondary" href="<?= e(app_url('library.php?view=notes&verse_id=' . (int) $bookmark['verse_id'])); ?>">Note</a>
                                    </div>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete-bookmark">
                                    <input type="hidden" name="bookmark_id" value="<?= e((string) $bookmark['id']); ?>">
                                    <button class="button button-secondary" type="submit">Remove</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php elseif ($activeView === 'notes'): ?>
            <div class="two-column top-gap">
                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Study Notes</p>
                            <h2><?= $editingNote ? 'Edit note' : 'New note'; ?></h2>
                        </div>
                        <?php if ($editingNote): ?>
                            <a class="button button-secondary" href="<?= e(app_url('library.php?view=notes')); ?>">New Note</a>
                        <?php endif; ?>
                    </div>
                    <form class="form-stack" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="<?= $editingNote ? 'update-note' : 'create-note'; ?>">
                        <?php if ($editingNote): ?>
                            <input type="hidden" name="note_id" value="<?= e((string) $editingNote['id']); ?>">
                        <?php endif; ?>
                        <label>
                            <span>Attach to saved verse</span>
                            <select name="verse_id">
                                <option value="">No linked verse</option>
                                <?php foreach ($noteableVerses as $verse): ?>
                                    <option value="<?= e((string) $verse['id']); ?>" <?= $noteForm['verse_id'] === (string) $verse['id'] ? 'selected' : ''; ?>>
                                        <?= e(format_verse_reference($verse)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Title</span>
                            <input type="text" name="title" value="<?= e($noteForm['title']); ?>" required>
                        </label>
                        <label>
                            <span>Note</span>
                            <textarea name="content" rows="8" required><?= e($noteForm['content']); ?></textarea>
                        </label>
                        <button class="button button-primary" type="submit"><?= $editingNote ? 'Update Note' : 'Save Note'; ?></button>
                    </form>
                </section>

                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Archive</p>
                            <h2>Your notes</h2>
                        </div>
                    </div>
                    <div class="stack-list top-gap-sm">
                        <?php if ($notes === []): ?>
                            <p class="empty-state">No notes yet. Write one here or start from a saved verse.</p>
                        <?php else: ?>
                            <?php foreach ($notes as $note): ?>
                                <article class="note-card">
                                    <h3><?= e((string) $note['title']); ?></h3>
                                    <?php if (!empty($note['book_name'])): ?>
                                        <p class="muted-copy"><?= e(format_verse_reference($note)); ?></p>
                                    <?php endif; ?>
                                    <p><?= nl2br(e((string) $note['content'])); ?></p>
                                    <div class="inline-actions">
                                        <a class="button button-secondary" href="<?= e(app_url('library.php?view=notes&edit_note=' . (int) $note['id'])); ?>">Edit</a>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete-note">
                                            <input type="hidden" name="note_id" value="<?= e((string) $note['id']); ?>">
                                            <button class="button button-secondary" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        <?php elseif ($activeView === 'sermons'): ?>
            <section class="panel top-gap">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Sermon Docs</p>
                        <h2>Teaching notes</h2>
                        <p class="muted-copy">Sermon documents stay in the advanced editor because they include citations, AI tools, and share links.</p>
                    </div>
                    <a class="button button-primary" href="<?= e(app_url('sermon-notes.php?new=1')); ?>">New Sermon Doc</a>
                </div>
                <div class="card-grid card-grid-2 top-gap-sm">
                    <?php if (!$sermonNotesEnabled): ?>
                        <article class="list-card list-card-block">
                            <strong>Sermon docs are not installed yet</strong>
                            <span>Run the sermon notes migration to enable this feature.</span>
                        </article>
                    <?php elseif ($sermonNotes === []): ?>
                        <article class="list-card list-card-block">
                            <strong>No sermon documents yet</strong>
                            <span>Create one when a teaching, service, or study needs a deeper document.</span>
                        </article>
                    <?php else: ?>
                        <?php foreach ($sermonNotes as $note): ?>
                            <article class="list-card list-card-block">
                                <span class="pill"><?= e(ucfirst((string) ($note['status'] ?? 'draft'))); ?></span>
                                <strong><?= e((string) $note['title']); ?></strong>
                                <span><?= e(truncate_text((string) ($note['content_excerpt'] ?? ''), 160)); ?></span>
                                <a class="button button-secondary" href="<?= e(app_url('sermon-notes.php?note=' . (int) $note['id'])); ?>">Open Editor</a>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php elseif ($activeView === 'prayer'): ?>
            <div class="two-column top-gap">
                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Prayer</p>
                            <h2>New prayer request</h2>
                        </div>
                    </div>
                    <form class="form-stack compact-form" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="create-prayer">
                        <label>
                            <span>Title</span>
                            <input type="text" name="title" value="<?= e($prayerForm['title']); ?>" required>
                        </label>
                        <label>
                            <span>Details</span>
                            <textarea name="details" rows="6"><?= e($prayerForm['details']); ?></textarea>
                        </label>
                        <label>
                            <span>Status</span>
                            <select name="status">
                                <option value="active" <?= $prayerForm['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="answered" <?= $prayerForm['status'] === 'answered' ? 'selected' : ''; ?>>Answered</option>
                            </select>
                        </label>
                        <button class="button button-primary" type="submit">Save Prayer</button>
                    </form>
                </section>

                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Prayer List</p>
                            <h2>Active and answered</h2>
                        </div>
                    </div>
                    <div class="stack-list top-gap-sm">
                        <?php if ($prayerEntries === []): ?>
                            <p class="empty-state">No prayer requests yet.</p>
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
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="<?= (string) $entry['status'] === 'active' ? 'mark-prayer-answered' : 'reopen-prayer'; ?>">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $entry['id']); ?>">
                                            <button class="button button-secondary" type="submit"><?= (string) $entry['status'] === 'active' ? 'Mark Answered' : 'Reopen'; ?></button>
                                        </form>
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
                </section>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php

function library_reader_url(array $verse): string
{
    return app_url('bible.php?translation=' . urlencode((string) $verse['translation'])
        . '&book_id=' . (int) $verse['book_id']
        . '&chapter=' . (int) $verse['chapter_number']
        . '&verse=' . (int) $verse['verse_number']);
}

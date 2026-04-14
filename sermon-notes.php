<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Sermon Notes';
$activePage = 'sermon-notes';
$pageDescription = 'Capture sermon notes, cite verses, summarize speaker notes, and organize teaching archives.';
$pageScripts = ['assets/js/sermon-notes.js'];
$user = refresh_current_user();
$pageError = null;
$formError = null;
$folders = [];
$notes = [];
$selectedFolder = null;
$selectedNote = null;
$selectedFolderId = null;
$selectedNoteId = null;
$createMode = isset($_GET['new']);
$migrationNeeded = !sermon_notes_available();
$allNotesCount = 0;

if ($user === null) {
    set_flash('Sign in again to continue.', 'warning');
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($migrationNeeded) {
        $pageError = 'Run the sermon notes migration before saving folders or notes.';
    } else {
        $postAction = trim((string) ($_POST['action'] ?? ''));
        $postedFolderId = (int) ($_POST['folder_id'] ?? 0);
        $postedNoteId = (int) ($_POST['note_id'] ?? 0);

        try {
            switch ($postAction) {
                case 'create_folder':
                    $folderId = create_sermon_note_folder((int) $user['id'], trim((string) ($_POST['folder_name'] ?? '')));
                    set_flash('Folder created.', 'success');
                    redirect(sermon_notes_page_url(['folder' => $folderId]));
                    break;

                case 'rename_folder':
                    update_sermon_note_folder(
                        (int) ($_POST['manage_folder_id'] ?? 0),
                        (int) $user['id'],
                        trim((string) ($_POST['folder_name'] ?? ''))
                    );
                    set_flash('Folder updated.', 'success');
                    redirect(sermon_notes_page_url([
                        'folder' => (int) ($_POST['manage_folder_id'] ?? 0),
                        'note' => $postedNoteId > 0 ? $postedNoteId : null,
                    ]));
                    break;

                case 'delete_folder':
                    delete_sermon_note_folder((int) ($_POST['manage_folder_id'] ?? 0), (int) $user['id']);
                    set_flash('Folder removed. Notes from that folder are still available.', 'success');
                    redirect(sermon_notes_page_url());
                    break;

                case 'save_note':
                    $notePayload = [
                        'folder_id' => $postedFolderId > 0 ? $postedFolderId : null,
                        'title' => trim((string) ($_POST['title'] ?? '')),
                        'speaker_name' => trim((string) ($_POST['speaker_name'] ?? '')),
                        'series_name' => trim((string) ($_POST['series_name'] ?? '')),
                        'service_date' => trim((string) ($_POST['service_date'] ?? '')),
                        'source_url' => trim((string) ($_POST['source_url'] ?? '')),
                        'summary_text' => trim((string) ($_POST['summary_text'] ?? '')),
                        'speaker_notes_text' => trim((string) ($_POST['speaker_notes_text'] ?? '')),
                        'content_html' => (string) ($_POST['content_html'] ?? ''),
                        'content_text' => trim((string) ($_POST['content_text'] ?? '')),
                        'status' => trim((string) ($_POST['status'] ?? 'draft')),
                        'layout_mode' => trim((string) ($_POST['layout_mode'] ?? 'split')),
                        'is_starred' => !empty($_POST['is_starred']) ? 1 : 0,
                        'reference_tags' => decode_submitted_json_array((string) ($_POST['reference_tags_json'] ?? '[]')),
                        'verse_refs' => decode_submitted_json_array((string) ($_POST['verse_refs_json'] ?? '[]')),
                        'storm_board_json' => (string) ($_POST['storm_board_json'] ?? ''),
                    ];

                    if ($postedNoteId > 0) {
                        update_sermon_note_record($postedNoteId, (int) $user['id'], $notePayload);
                        set_flash('Sermon note updated.', 'success');
                        redirect(sermon_notes_page_url([
                            'folder' => $postedFolderId > 0 ? $postedFolderId : null,
                            'note' => $postedNoteId,
                        ]));
                    }

                    $noteId = create_sermon_note_record((int) $user['id'], $notePayload);
                    set_flash('Sermon note saved.', 'success');
                    redirect(sermon_notes_page_url([
                        'folder' => $postedFolderId > 0 ? $postedFolderId : null,
                        'note' => $noteId,
                    ]));
                    break;

                case 'delete_note':
                    delete_sermon_note_record($postedNoteId, (int) $user['id']);
                    set_flash('Sermon note deleted.', 'success');
                    redirect(sermon_notes_page_url([
                        'folder' => $postedFolderId > 0 ? $postedFolderId : null,
                    ]));
                    break;
            }
        } catch (Throwable $exception) {
            $formError = $exception->getMessage();
        }
    }
}

if (!$migrationNeeded) {
    try {
        $folders = fetch_sermon_note_folders((int) $user['id']);
        $requestedFolderId = (int) ($_GET['folder'] ?? 0);
        $requestedNoteId = (int) ($_GET['note'] ?? 0);
        $selectedFolder = $requestedFolderId > 0
            ? fetch_sermon_note_folder($requestedFolderId, (int) $user['id'])
            : null;
        $selectedFolderId = $selectedFolder ? (int) $selectedFolder['id'] : null;
        $allNotesCount = count(fetch_sermon_notes((int) $user['id']));
        $notes = fetch_sermon_notes((int) $user['id'], $selectedFolderId);

        if ($requestedNoteId > 0) {
            $selectedNote = fetch_sermon_note($requestedNoteId, (int) $user['id']);

            if ($selectedNote !== null) {
                $selectedNoteId = (int) $selectedNote['id'];
                $selectedFolderId = !empty($selectedNote['folder_id']) ? (int) $selectedNote['folder_id'] : $selectedFolderId;
                $selectedFolder = $selectedFolderId !== null
                    ? fetch_sermon_note_folder($selectedFolderId, (int) $user['id'])
                    : $selectedFolder;
            }
        } elseif (!$createMode && $notes !== []) {
            $selectedNote = fetch_sermon_note((int) $notes[0]['id'], (int) $user['id']);
            $selectedNoteId = $selectedNote ? (int) $selectedNote['id'] : null;
        }
    } catch (Throwable $exception) {
        $pageError = 'Sermon notes could not be loaded because the database is unavailable.';
    }
}

$initialBoard = sermon_note_board_template();
$selectedVerseRefs = [];
$selectedReferenceTags = [];
$groupedReferenceTags = group_sermon_note_reference_tags([]);
$shareUrl = '';

$form = [
    'note_id' => '',
    'folder_id' => $selectedFolderId ? (string) $selectedFolderId : '',
    'title' => '',
    'speaker_name' => '',
    'series_name' => '',
    'service_date' => date('Y-m-d'),
    'source_url' => '',
    'summary_text' => '',
    'speaker_notes_text' => '',
    'content_html' => '<p></p>',
    'content_text' => '',
    'status' => 'draft',
    'layout_mode' => 'split',
    'is_starred' => '0',
];

if ($selectedNote !== null) {
    $initialBoard = is_array($selectedNote['storm_board'] ?? null) ? $selectedNote['storm_board'] : sermon_note_board_template();
    $selectedVerseRefs = array_map(
        static function (array $verseRef): array {
            return [
                'verse_id' => (int) ($verseRef['verse_id'] ?? 0),
                'reference_kind' => (string) ($verseRef['reference_kind'] ?? 'citation'),
                'reference_label' => (string) ($verseRef['reference_label'] ?? ''),
                'quote_text' => (string) ($verseRef['quote_text'] ?? ''),
            ];
        },
        (array) ($selectedNote['verse_refs'] ?? [])
    );
    $selectedReferenceTags = array_map(
        static function (array $tag): array {
            return [
                'tag_type' => (string) ($tag['tag_type'] ?? ''),
                'label' => (string) ($tag['label'] ?? ''),
                'detail_text' => (string) ($tag['detail_text'] ?? ''),
            ];
        },
        (array) ($selectedNote['reference_tags'] ?? [])
    );
    $groupedReferenceTags = group_sermon_note_reference_tags((array) ($selectedNote['reference_tags'] ?? []));
    $shareUrl = app_url('sermon-note-view.php?code=' . urlencode((string) $selectedNote['share_code']), true);
    $form = [
        'note_id' => (string) $selectedNote['id'],
        'folder_id' => !empty($selectedNote['folder_id']) ? (string) $selectedNote['folder_id'] : '',
        'title' => (string) ($selectedNote['title'] ?? ''),
        'speaker_name' => (string) ($selectedNote['speaker_name'] ?? ''),
        'series_name' => (string) ($selectedNote['series_name'] ?? ''),
        'service_date' => (string) ($selectedNote['service_date'] ?? date('Y-m-d')),
        'source_url' => (string) ($selectedNote['source_url'] ?? ''),
        'summary_text' => (string) ($selectedNote['summary_text'] ?? ''),
        'speaker_notes_text' => (string) ($selectedNote['speaker_notes_text'] ?? ''),
        'content_html' => (string) ($selectedNote['content_html'] ?? '<p></p>'),
        'content_text' => (string) ($selectedNote['content_text'] ?? ''),
        'status' => (string) ($selectedNote['status'] ?? 'draft'),
        'layout_mode' => (string) ($selectedNote['layout_mode'] ?? 'split'),
        'is_starred' => !empty($selectedNote['is_starred']) ? '1' : '0',
    ];
} else {
    $groupedReferenceTags = group_sermon_note_reference_tags([]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_note' && $formError !== null) {
    $postedReferenceTags = decode_submitted_json_array((string) ($_POST['reference_tags_json'] ?? '[]'));
    $postedVerseRefs = decode_submitted_json_array((string) ($_POST['verse_refs_json'] ?? '[]'));
    $postedBoard = decode_sermon_note_board((string) ($_POST['storm_board_json'] ?? ''));
    $selectedVerseRefs = normalize_sermon_note_verse_refs($postedVerseRefs);
    $selectedReferenceTags = normalize_sermon_note_reference_tags($postedReferenceTags);
    $groupedReferenceTags = group_sermon_note_reference_tags($selectedReferenceTags);
    $initialBoard = $postedBoard;
    $form = [
        'note_id' => trim((string) ($_POST['note_id'] ?? '')),
        'folder_id' => trim((string) ($_POST['folder_id'] ?? '')),
        'title' => trim((string) ($_POST['title'] ?? '')),
        'speaker_name' => trim((string) ($_POST['speaker_name'] ?? '')),
        'series_name' => trim((string) ($_POST['series_name'] ?? '')),
        'service_date' => trim((string) ($_POST['service_date'] ?? date('Y-m-d'))),
        'source_url' => trim((string) ($_POST['source_url'] ?? '')),
        'summary_text' => trim((string) ($_POST['summary_text'] ?? '')),
        'speaker_notes_text' => trim((string) ($_POST['speaker_notes_text'] ?? '')),
        'content_html' => (string) ($_POST['content_html'] ?? '<p></p>'),
        'content_text' => trim((string) ($_POST['content_text'] ?? '')),
        'status' => trim((string) ($_POST['status'] ?? 'draft')),
        'layout_mode' => trim((string) ($_POST['layout_mode'] ?? 'split')),
        'is_starred' => !empty($_POST['is_starred']) ? '1' : '0',
    ];
}

$verseRefsJson = json_encode($selectedVerseRefs, JSON_UNESCAPED_SLASHES);
$referenceTagsJson = json_encode($selectedReferenceTags, JSON_UNESCAPED_SLASHES);
$stormBoardJson = json_encode($initialBoard, JSON_UNESCAPED_SLASHES);

if (!is_string($verseRefsJson)) {
    $verseRefsJson = '[]';
}

if (!is_string($referenceTagsJson)) {
    $referenceTagsJson = '[]';
}

if (!is_string($stormBoardJson)) {
    $stormBoardJson = '{}';
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Sermon Notes</p>
                <h1>Build a Bible-aware sermon archive</h1>
                <p>Capture notes, summarize speaker transcripts, cite Scripture, group themes, and keep each teaching in a document folder.</p>
            </div>

            <div class="quick-stat-row">
                <div class="quick-stat">
                    <strong><?= e((string) count($folders)); ?></strong>
                    <span>folders</span>
                </div>
                <div class="quick-stat">
                    <strong><?= e((string) count($notes)); ?></strong>
                    <span><?= $selectedFolder ? 'notes in folder' : 'sermon notes'; ?></span>
                </div>
            </div>

            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(sermon_notes_page_url([
                    'folder' => $selectedFolderId,
                    'new' => 1,
                ])); ?>">New Sermon Note</a>
                <a class="button button-secondary" href="<?= e(app_url('bible.php')); ?>">Open Bible</a>
            </div>
        </div>

        <?php if ($migrationNeeded): ?>
            <div class="flash flash-warning">
                Run `sql/add_sermon_notes.sql` before using this feature.
            </div>
        <?php endif; ?>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <?php if ($formError): ?>
            <div class="flash flash-warning"><?= e($formError); ?></div>
        <?php endif; ?>

        <div class="sermon-workspace<?= $selectedNote ? ' has-note-selected' : ''; ?>" data-sermon-workspace>
            <aside class="panel sermon-left-rail">
                <div class="panel-heading">
                    <div>
                        <h2>Folders</h2>
                        <p class="muted-copy">Keep sermons grouped by series, speaker, or season.</p>
                    </div>
                </div>

                <form class="form-stack compact-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="create_folder">
                    <label>
                        <span>New folder</span>
                        <input type="text" name="folder_name" placeholder="Sunday AM, Romans Series">
                    </label>
                    <button class="button button-secondary" type="submit" <?= $migrationNeeded ? 'disabled' : ''; ?>>Add Folder</button>
                </form>

                <div class="sermon-folder-list top-gap-sm">
                    <a class="sermon-folder-link<?= $selectedFolderId === null ? ' is-active' : ''; ?>" href="<?= e(sermon_notes_page_url()); ?>">
                        <span>All Documents</span>
                        <strong><?= e((string) $allNotesCount); ?></strong>
                    </a>

                    <?php foreach ($folders as $folder): ?>
                        <a
                            class="sermon-folder-link<?= $selectedFolderId === (int) $folder['id'] ? ' is-active' : ''; ?>"
                            href="<?= e(sermon_notes_page_url(['folder' => (int) $folder['id']])); ?>"
                        >
                            <span><?= e((string) $folder['name']); ?></span>
                            <strong><?= e((string) ($folder['note_count'] ?? 0)); ?></strong>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($selectedFolder): ?>
                    <div class="sermon-folder-manage top-gap">
                        <form class="form-stack compact-form" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="rename_folder">
                            <input type="hidden" name="manage_folder_id" value="<?= e((string) $selectedFolder['id']); ?>">
                            <input type="hidden" name="note_id" value="<?= e((string) ($selectedNote['id'] ?? '')); ?>">
                            <label>
                                <span>Rename folder</span>
                                <input type="text" name="folder_name" value="<?= e((string) $selectedFolder['name']); ?>">
                            </label>
                            <button class="button button-secondary" type="submit">Update Folder</button>
                        </form>

                        <form class="top-gap-sm" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete_folder">
                            <input type="hidden" name="manage_folder_id" value="<?= e((string) $selectedFolder['id']); ?>">
                            <button class="button button-secondary" type="submit">Remove Folder</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="panel-heading top-gap">
                    <div>
                        <h2>Documents</h2>
                        <p class="muted-copy"><?= $selectedFolder ? 'This folder view is filtered.' : 'Recent sermon note documents.'; ?></p>
                    </div>
                </div>

                <div class="sermon-document-list">
                    <?php if ($notes === []): ?>
                        <p class="empty-state">No sermon notes yet. Start a new document to begin.</p>
                    <?php else: ?>
                        <?php foreach ($notes as $note): ?>
                            <a
                                class="sermon-document-link<?= $selectedNoteId === (int) $note['id'] ? ' is-active' : ''; ?>"
                                href="<?= e(sermon_notes_page_url([
                                    'folder' => $selectedFolderId,
                                    'note' => (int) $note['id'],
                                ])); ?>"
                            >
                                <strong><?= e((string) $note['title']); ?></strong>
                                <span><?= e((string) $note['content_excerpt']); ?></span>
                                <small><?= e(date('M j, Y g:i A', strtotime((string) $note['updated_at']))); ?></small>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <section class="panel sermon-editor-panel" data-sermon-editor-panel>
                <div class="panel-heading">
                    <div>
                        <h2><?= $selectedNote ? 'Edit Sermon Note' : 'New Sermon Note'; ?></h2>
                        <p class="muted-copy">The writing surface stores rich note content, verses, references, and a storm board in one document.</p>
                    </div>

                    <div class="inline-actions">
                        <button class="button button-secondary" type="button" data-sermon-side-panel-toggle aria-expanded="false">Move Tools To Side</button>
                    </div>
                </div>

                <form class="form-stack" id="sermon-note-form" method="post" data-sermon-note-form>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="save_note">
                    <input type="hidden" name="note_id" value="<?= e($form['note_id']); ?>">
                    <input type="hidden" name="content_html" value="" data-sermon-content-html>
                    <input type="hidden" name="content_text" value="" data-sermon-content-text>
                    <input type="hidden" name="reference_tags_json" value="<?= e($referenceTagsJson); ?>" data-sermon-reference-tags-json>
                    <input type="hidden" name="verse_refs_json" value="<?= e($verseRefsJson); ?>" data-sermon-verse-refs-json>
                    <input type="hidden" name="storm_board_json" value="<?= e($stormBoardJson); ?>" data-sermon-storm-board-json>

                    <div class="sermon-meta-grid">
                        <label>
                            <span>Title</span>
                            <input type="text" name="title" value="<?= e($form['title']); ?>" placeholder="Faith In The Fire" required>
                        </label>

                        <label>
                            <span>Speaker</span>
                            <input type="text" name="speaker_name" value="<?= e($form['speaker_name']); ?>" placeholder="Pastor Daniel">
                        </label>

                        <label>
                            <span>Series</span>
                            <input type="text" name="series_name" value="<?= e($form['series_name']); ?>" placeholder="Daniel">
                        </label>

                        <label>
                            <span>Folder</span>
                            <select name="folder_id">
                                <option value="">Unfiled</option>
                                <?php foreach ($folders as $folder): ?>
                                    <option value="<?= e((string) $folder['id']); ?>" <?= $form['folder_id'] === (string) $folder['id'] ? 'selected' : ''; ?>>
                                        <?= e((string) $folder['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span>Service date</span>
                            <input type="date" name="service_date" value="<?= e($form['service_date']); ?>">
                        </label>

                        <label>
                            <span>Source URL</span>
                            <input type="url" name="source_url" value="<?= e($form['source_url']); ?>" placeholder="https://example.com/sermon">
                        </label>

                        <label>
                            <span>Layout</span>
                            <select name="layout_mode" data-sermon-layout-select>
                                <?php foreach (sermon_note_layout_options() as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= $form['layout_mode'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span>Status</span>
                            <select name="status">
                                <?php foreach (sermon_note_status_options() as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= $form['status'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="sermon-checkbox-field">
                            <input type="checkbox" name="is_starred" value="1" <?= $form['is_starred'] === '1' ? 'checked' : ''; ?>>
                            <span>Star this sermon note</span>
                        </label>
                    </div>

                    <div class="sermon-toolbar" data-sermon-toolbar>
                        <div class="sermon-toolbar-group">
                            <button class="button button-secondary" type="button" data-editor-command="bold">Bold</button>
                            <button class="button button-secondary" type="button" data-editor-command="italic">Italic</button>
                            <button class="button button-secondary" type="button" data-editor-block="h2">H2</button>
                            <button class="button button-secondary" type="button" data-editor-block="blockquote">Quote</button>
                            <button class="button button-secondary" type="button" data-editor-command="insertUnorderedList">Bullets</button>
                            <button class="button button-secondary" type="button" data-editor-highlight="note-highlight-green">Green Highlight</button>
                            <button class="button button-secondary" type="button" data-editor-highlight="note-highlight-theme">Thematic</button>
                        </div>

                        <div class="sermon-toolbar-group sermon-toolbar-link-group">
                            <input type="url" placeholder="https://..." data-editor-link-input>
                            <button class="button button-secondary" type="button" data-editor-link-apply>Add Link</button>
                        </div>
                    </div>

                    <div class="sermon-editor-surface">
                        <div class="sermon-rich-editor" contenteditable="true" spellcheck="true" data-sermon-rich-editor><?= $form['content_html']; ?></div>
                    </div>

                    <div class="panel sermon-board-panel">
                        <div class="panel-heading">
                            <div>
                                <h3>Storm Board</h3>
                                <p class="muted-copy">Keep fast-moving observations, application ideas, and prayer responses close to the document.</p>
                            </div>
                        </div>

                        <div class="sermon-board-grid" data-sermon-board>
                            <?php foreach ($initialBoard as $columnKey => $items): ?>
                                <section class="sermon-board-column" data-board-column="<?= e($columnKey); ?>">
                                    <div class="sermon-board-column-header">
                                        <h4><?= e(mb_convert_case($columnKey, MB_CASE_TITLE, 'UTF-8')); ?></h4>
                                        <button class="button button-secondary" type="button" data-board-add-card="<?= e($columnKey); ?>">Add Card</button>
                                    </div>

                                    <div class="sermon-board-card-list" data-board-card-list="<?= e($columnKey); ?>">
                                        <?php foreach ($items as $item): ?>
                                            <div class="sermon-board-card">
                                                <textarea rows="3"><?= e((string) $item); ?></textarea>
                                                <button class="button button-secondary" type="button" data-board-remove-card>Remove</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="inline-actions">
                        <button class="button button-primary" type="submit" <?= $migrationNeeded ? 'disabled' : ''; ?>>Save Sermon Note</button>
                        <a class="button button-secondary" href="<?= e(sermon_notes_page_url([
                            'folder' => $selectedFolderId,
                            'new' => 1,
                        ])); ?>">New Document</a>
                    </div>
                </form>

                <?php if ($selectedNote): ?>
                    <form class="top-gap" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="delete_note">
                        <input type="hidden" name="note_id" value="<?= e((string) $selectedNote['id']); ?>">
                        <input type="hidden" name="folder_id" value="<?= e((string) ($selectedNote['folder_id'] ?? '')); ?>">
                        <button class="button button-secondary" type="submit">Delete Sermon Note</button>
                    </form>
                <?php endif; ?>
            </section>

            <aside class="panel sermon-side-panel" data-sermon-side-panel>
                <div class="panel-heading">
                    <div>
                        <h2>Bible Tools</h2>
                        <p class="muted-copy">Summaries, verse search, references, and short links stay here.</p>
                    </div>

                    <button class="button button-secondary" type="button" data-sermon-side-panel-close>Close</button>
                </div>

                <label>
                    <span>Summary</span>
                    <textarea name="summary_text" rows="4" form="sermon-note-form" data-sermon-summary-field><?= e($form['summary_text']); ?></textarea>
                </label>

                <label>
                    <span>Speaker notes or transcript</span>
                    <textarea name="speaker_notes_text" rows="6" form="sermon-note-form" data-sermon-speaker-notes-field><?= e($form['speaker_notes_text']); ?></textarea>
                </label>

                <div class="inline-actions">
                    <button class="button button-secondary" type="button" data-sermon-ai-summary>Summarize Notes</button>
                    <button class="button button-secondary" type="button" data-sermon-ai-references>Suggest References</button>
                </div>

                <p class="muted-copy" data-sermon-ai-status>AI suggestions fill draft fields only. Review them before saving.</p>

                <?php if ($shareUrl !== ''): ?>
                    <label class="top-gap-sm">
                        <span>Short link</span>
                        <div class="sermon-short-link-row">
                            <input type="url" readonly value="<?= e($shareUrl); ?>" data-sermon-share-url>
                            <button class="button button-secondary" type="button" data-sermon-copy-share-url>Copy</button>
                        </div>
                    </label>
                <?php endif; ?>

                <div class="sermon-reference-panel top-gap">
                    <div class="panel-heading">
                        <div>
                            <h3>Verse Search</h3>
                            <p class="muted-copy">Search Scripture, preview the verse, then insert a citation or paraphrase.</p>
                        </div>
                    </div>

                    <div class="sermon-verse-search-row">
                        <input type="search" placeholder="John 3:16 or faith" data-sermon-verse-query>
                        <button class="button button-secondary" type="button" data-sermon-verse-search>Search</button>
                    </div>

                    <div class="sermon-verse-search-results" data-sermon-verse-results>
                        <p class="muted-copy">Verse results will appear here.</p>
                    </div>
                </div>

                <div class="sermon-reference-panel top-gap">
                    <div class="panel-heading">
                        <div>
                            <h3>Reference Groups</h3>
                            <p class="muted-copy">Use commas to track people, places, promises, and themes tied to this sermon.</p>
                        </div>
                    </div>

                    <div class="sermon-reference-grid">
                        <?php foreach (sermon_note_reference_type_options() as $type => $label): ?>
                            <?php
                            $existingItems = $groupedReferenceTags[$type] ?? [];
                            $existingValue = implode(', ', array_map(
                                static fn(array $tag): string => (string) ($tag['label'] ?? ''),
                                $existingItems
                            ));
                            ?>
                            <label>
                                <span><?= e($label); ?></span>
                                <input type="text" value="<?= e($existingValue); ?>" data-reference-group="<?= e($type); ?>" placeholder="<?= e($label); ?>">
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sermon-reference-panel top-gap">
                    <div class="panel-heading">
                        <div>
                            <h3>Verse References</h3>
                            <p class="muted-copy">Inserted citations and paraphrases are tracked here.</p>
                        </div>
                    </div>

                    <div class="sermon-verse-ref-list" data-sermon-verse-ref-list>
                        <?php if ($selectedVerseRefs === []): ?>
                            <p class="muted-copy">No verses attached yet.</p>
                        <?php else: ?>
                            <?php foreach ($selectedVerseRefs as $verseRef): ?>
                                <div class="sermon-verse-ref-card">
                                    <strong><?= e((string) ($verseRef['reference_label'] ?? 'Verse')); ?></strong>
                                    <span><?= e(mb_convert_case((string) ($verseRef['reference_kind'] ?? 'citation'), MB_CASE_TITLE, 'UTF-8')); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

<div class="panel-modal sermon-verse-modal" data-sermon-verse-modal hidden aria-hidden="true">
    <div class="panel panel-modal-card" data-sermon-verse-modal-content>
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Verse Preview</p>
                <h3 data-sermon-verse-modal-reference>Select a verse</h3>
                <p class="muted-copy" data-sermon-verse-modal-translation></p>
            </div>
            <button class="button button-secondary" type="button" data-sermon-verse-modal-close>Close</button>
        </div>

        <blockquote class="sermon-verse-modal-text" data-sermon-verse-modal-text></blockquote>

        <div class="inline-actions top-gap-sm">
            <button class="button button-primary" type="button" data-sermon-insert-citation>Insert Citation</button>
            <button class="button button-secondary" type="button" data-sermon-paraphrase-verse>Paraphrase Verse</button>
        </div>

        <p class="muted-copy top-gap-sm" data-sermon-verse-modal-status>Choose how you want this verse to appear in the document.</p>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php

function sermon_notes_page_url(array $params = []): string
{
    $queryParams = [];

    foreach ($params as $key => $value) {
        if ($value === null || $value === '' || $value === false) {
            continue;
        }

        $queryParams[$key] = $value;
    }

    $path = 'sermon-notes.php';

    if ($queryParams !== []) {
        $path .= '?' . http_build_query($queryParams);
    }

    return app_url($path);
}

function decode_submitted_json_array(string $value): array
{
    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
}

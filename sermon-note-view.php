<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$activePage = 'sermon-note-view';
$pageScripts = ['assets/js/sermon-view.js'];
$shareCode = trim((string) ($_GET['code'] ?? ''));
$note = null;
$pageError = null;
$verseStudyPayload = [];

if ($shareCode === '') {
    $pageTitle = 'Shared Sermon Note';
    $pageDescription = 'Shared sermon note preview.';
    $pageError = 'This short link is missing or invalid.';
} else {
    try {
        $note = fetch_public_sermon_note_by_share_code($shareCode);
    } catch (Throwable $exception) {
        $pageError = 'The shared sermon note could not be loaded right now.';
    }

    if ($note === null && $pageError === null) {
        $pageError = 'That sermon note is no longer available.';
    }

    $pageTitle = $note ? (string) ($note['title'] ?? 'Shared Sermon Note') : 'Shared Sermon Note';
    $pageDescription = $note && !empty($note['summary_text'])
        ? truncate_text((string) $note['summary_text'], 150)
        : 'Shared sermon note preview.';
}

$groupedReferenceTags = group_sermon_note_reference_tags((array) ($note['reference_tags'] ?? []));

if ($note !== null) {
    $verseStudyPayload = array_values(array_map(
        static function (array $verseRef): array {
            $referenceLabel = trim((string) ($verseRef['reference_label'] ?? ''));

            if ($referenceLabel === '') {
                $referenceLabel = format_verse_reference($verseRef);
            }

            return [
                'verse_id' => (int) ($verseRef['verse_id'] ?? 0),
                'book_id' => (int) ($verseRef['book_id'] ?? 0),
                'chapter_number' => (int) ($verseRef['chapter_number'] ?? 0),
                'verse_number' => (int) ($verseRef['verse_number'] ?? 0),
                'reference_kind' => (string) ($verseRef['reference_kind'] ?? 'citation'),
                'reference_label' => $referenceLabel,
                'verse_text' => trim((string) ($verseRef['verse_text'] ?? $verseRef['quote_text'] ?? '')),
                'translation' => trim((string) ($verseRef['translation'] ?? '')),
                'reader_url' => app_url('bible.php?' . http_build_query(array_filter([
                    'translation' => trim((string) ($verseRef['translation'] ?? '')),
                    'book_id' => (int) ($verseRef['book_id'] ?? 0) > 0 ? (int) ($verseRef['book_id'] ?? 0) : null,
                    'chapter' => (int) ($verseRef['chapter_number'] ?? 0) > 0 ? (int) ($verseRef['chapter_number'] ?? 0) : null,
                    'verse' => (int) ($verseRef['verse_number'] ?? 0) > 0 ? (int) ($verseRef['verse_number'] ?? 0) : null,
                ], static fn ($value): bool => $value !== null && $value !== ''))),
            ];
        },
        (array) ($note['verse_refs'] ?? [])
    ));
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php elseif ($note): ?>
            <div class="section-heading section-heading-rich">
                <div>
                    <p class="eyebrow">Shared Sermon Note</p>
                    <h1><?= e((string) $note['title']); ?></h1>
                    <p><?= e((string) ($note['summary_text'] ?: 'A shared sermon note document.')); ?></p>
                </div>

                <div class="quick-stat-row">
                    <?php if (!empty($note['speaker_name'])): ?>
                        <div class="quick-stat">
                            <strong><?= e((string) $note['speaker_name']); ?></strong>
                            <span>speaker</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($note['service_date'])): ?>
                        <div class="quick-stat">
                            <strong><?= e(date('M j, Y', strtotime((string) $note['service_date']))); ?></strong>
                            <span>service date</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($note['author_name'])): ?>
                        <div class="quick-stat">
                            <strong><?= e((string) $note['author_name']); ?></strong>
                            <span>shared by</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div
                class="two-column sermon-view-layout"
                data-sermon-view
                data-sermon-verses="<?= e((string) json_encode($verseStudyPayload, JSON_UNESCAPED_SLASHES)); ?>"
            >
                <article class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Sermon View</h2>
                            <p class="muted-copy">Formatted note content with live Scripture study links.</p>
                        </div>
                    </div>

                    <div class="sermon-rich-editor sermon-read-view" data-sermon-view-content>
                        <?= (string) ($note['content_html'] ?? '<p></p>'); ?>
                    </div>
                </article>

                <aside class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>References</h2>
                            <p class="muted-copy">Verse citations, themes, and grouped study references.</p>
                        </div>
                    </div>

                    <?php if (!empty($note['verse_refs'])): ?>
                        <div class="sermon-verse-ref-list">
                            <?php foreach ((array) $note['verse_refs'] as $verseRef): ?>
                                <?php
                                $referenceLabel = trim((string) ($verseRef['reference_label'] ?? ''));

                                if ($referenceLabel === '') {
                                    $referenceLabel = format_verse_reference($verseRef);
                                }
                                ?>
                                <button
                                    class="sermon-verse-ref-card sermon-view-ref-card"
                                    type="button"
                                    data-sermon-study-ref
                                    data-verse-id="<?= e((string) ($verseRef['verse_id'] ?? 0)); ?>"
                                >
                                    <div>
                                        <strong><?= e($referenceLabel); ?></strong>
                                        <span><?= e(mb_convert_case((string) ($verseRef['reference_kind'] ?? 'citation'), MB_CASE_TITLE, 'UTF-8')); ?></span>
                                    </div>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted-copy">No verse references were attached.</p>
                    <?php endif; ?>

                    <?php foreach (sermon_note_reference_type_options() as $type => $label): ?>
                        <?php $items = $groupedReferenceTags[$type] ?? []; ?>
                        <?php if ($items === []): ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <div class="top-gap-sm">
                            <h3><?= e($label); ?></h3>
                            <div class="bible-chip-row">
                                <?php foreach ($items as $item): ?>
                                    <span class="pill pill-dark"><?= e((string) ($item['label'] ?? '')); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </aside>
            </div>

            <section class="panel top-gap">
                <div class="panel-heading">
                    <div>
                        <h2>Storm Board</h2>
                        <p class="muted-copy">Observation, application, and prayer cards tied to this sermon.</p>
                    </div>
                </div>

                <div class="sermon-board-grid">
                    <?php foreach ((array) ($note['storm_board'] ?? sermon_note_board_template()) as $columnKey => $items): ?>
                        <section class="sermon-board-column">
                            <div class="sermon-board-column-header">
                                <h3><?= e(mb_convert_case($columnKey, MB_CASE_TITLE, 'UTF-8')); ?></h3>
                            </div>

                            <div class="sermon-board-card-list">
                                <?php if ($items === []): ?>
                                    <p class="muted-copy">No cards saved.</p>
                                <?php else: ?>
                                    <?php foreach ($items as $item): ?>
                                        <div class="sermon-board-card">
                                            <p><?= nl2br(e((string) $item)); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="panel-modal sermon-verse-modal sermon-study-modal" data-sermon-study-modal hidden aria-hidden="true">
                <div class="panel panel-modal-card" data-sermon-study-modal-content role="dialog" aria-modal="true" aria-labelledby="sermon-study-modal-title">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow" data-sermon-study-translation></p>
                            <h3 id="sermon-study-modal-title" data-sermon-study-reference>Scripture Study</h3>
                        </div>
                        <button class="button button-secondary" type="button" data-sermon-study-close>Close</button>
                    </div>

                    <blockquote class="sermon-verse-modal-text" data-sermon-study-text></blockquote>

                    <div class="inline-actions top-gap">
                        <a class="button button-primary" href="<?= e(app_url('bible.php')); ?>" data-sermon-study-reader>Open In Bible</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

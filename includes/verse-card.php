<?php

declare(strict_types=1);

$verseReference = format_verse_reference($verse);
$verseReaderPath = 'bible.php?translation=' . urlencode((string) $verse['translation'])
    . '&book_id=' . (int) $verse['book_id']
    . '&chapter=' . (int) $verse['chapter_number']
    . '&verse=' . (int) $verse['verse_number'];
$verseReaderUrl = app_url($verseReaderPath) . '#verse-' . (int) $verse['verse_number'];
$verseShareUrl = app_url($verseReaderPath, true) . '#verse-' . (int) $verse['verse_number'];
$verseResourceTerms = function_exists('bible_resource_terms_for_text')
    ? bible_resource_terms_for_text((string) $verse['verse_text'])
    : [];
?>
<article class="scripture-result">
    <div class="scripture-result-top">
        <div>
            <h3>
                <a href="<?= e($verseReaderUrl); ?>">
                    <?= e($verseReference); ?>
                </a>
            </h3>
            <p class="scripture-text">
                <strong><?= e((string) $verse['verse_number']); ?></strong>
                <?= e((string) $verse['verse_text']); ?>
            </p>
        </div>
    </div>

    <nav class="scripture-result-resources" aria-label="<?= e($verseReference); ?> resources">
        <a href="<?= e(app_url('dictionary.php?q=' . urlencode($verseReference))); ?>">Reference</a>
        <?php foreach ($verseResourceTerms as $term): ?>
            <a href="<?= e(app_url('dictionary.php?q=' . urlencode($term))); ?>"><?= e(mb_convert_case($term, MB_CASE_TITLE, 'UTF-8')); ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="inline-actions">
        <button
            class="button button-primary"
            type="button"
            data-scripture-result-share
            data-share-reference="<?= e($verseReference); ?>"
            data-share-text="<?= e((string) $verse['verse_text']); ?>"
            data-share-url="<?= e($verseShareUrl); ?>"
        >Share</button>
        <a class="button button-secondary" href="<?= e(app_url('bible.php?translation=' . urlencode((string) $verse['translation']) . '&book_id=' . $verse['book_id'] . '&chapter=' . $verse['chapter_number'])); ?>">
            Open Chapter
        </a>
        <?php if (is_logged_in() && !empty($verse['id'])): ?>
            <form class="inline-form scripture-save-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="action" value="save-bookmark">
                <input type="hidden" name="verse_id" value="<?= e((string) $verse['id']); ?>">
                <input type="hidden" name="return_query" value="<?= e($query); ?>">
                <input type="hidden" name="return_translation" value="<?= e($selectedTranslation); ?>">
                <input type="hidden" name="return_book_id" value="<?= e((string) $selectedBookId); ?>">
                <input type="hidden" name="return_chapter" value="<?= e((string) $selectedChapter); ?>">
                <input type="hidden" name="return_verse" value="<?= e((string) $selectedVerseNumber); ?>">
                <input type="hidden" name="return_reader_mode" value="<?= e($readerMode); ?>">
                <input type="text" name="tag" placeholder="Tag">
                <select name="highlight_color" aria-label="Highlight color">
                    <option value="">No highlight</option>
                    <?php foreach (['neon-yellow', 'neon-green', 'neon-blue', 'neon-orange', 'neon-pink'] as $color): ?>
                        <option value="<?= e($color); ?>"><?= e($color); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="note" placeholder="Verse notes">
                <button class="button button-primary" type="submit">Highlight Verse</button>
            </form>
            <a class="button button-secondary" href="<?= e(app_url('library.php?view=notes&verse_id=' . $verse['id'])); ?>">Create Bookmark</a>
        <?php elseif (is_logged_in()): ?>
            <span class="muted-copy">Marking for this result is unavailable until the verse is mapped locally.</span>
        <?php else: ?>
            <a class="button button-primary" href="<?= e(app_url('login.php')); ?>">Sign in to bookmark</a>
        <?php endif; ?>
    </div>
</article>

<?php

declare(strict_types=1);
?>
<article class="scripture-result">
    <div class="scripture-result-top">
        <div>
            <h3>
                <a href="<?= e(app_url('bible.php?translation=' . urlencode((string) $verse['translation']) . '&book_id=' . $verse['book_id'] . '&chapter=' . $verse['chapter_number'] . '&verse=' . $verse['verse_number'])); ?>">
                    <?= e(format_verse_reference($verse)); ?>
                </a>
            </h3>
            <p class="scripture-text">
                <strong><?= e((string) $verse['verse_number']); ?></strong>
                <?= e((string) $verse['verse_text']); ?>
            </p>
        </div>
    </div>

    <div class="inline-actions">
        <a class="button button-secondary" href="<?= e(app_url('bible.php?translation=' . urlencode((string) $verse['translation']) . '&book_id=' . $verse['book_id'] . '&chapter=' . $verse['chapter_number'])); ?>">
            Open Chapter
        </a>
        <?php if (is_logged_in() && !empty($verse['id'])): ?>
            <form class="inline-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="verse_id" value="<?= e((string) $verse['id']); ?>">
                <input type="hidden" name="return_query" value="<?= e($query); ?>">
                <input type="hidden" name="return_translation" value="<?= e($selectedTranslation); ?>">
                <input type="hidden" name="return_book_id" value="<?= e((string) $selectedBookId); ?>">
                <input type="hidden" name="return_chapter" value="<?= e((string) $selectedChapter); ?>">
                <input type="text" name="tag" placeholder="Optional tag">
                <button class="button button-primary" type="submit">Save Bookmark</button>
            </form>
            <a class="button button-secondary" href="<?= e(app_url('notes.php?verse_id=' . $verse['id'])); ?>">Add Note</a>
        <?php elseif (is_logged_in()): ?>
            <span class="muted-copy">Bookmarking for this result is unavailable until the verse is mapped locally.</span>
        <?php else: ?>
            <a class="button button-primary" href="<?= e(app_url('login.php')); ?>">Sign in to save</a>
        <?php endif; ?>
    </div>
</article>

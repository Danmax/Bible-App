<?php

declare(strict_types=1);
?>
<article class="scripture-result">
    <div class="scripture-result-top">
        <div>
            <h3><?= e(format_verse_reference($verse)); ?></h3>
            <p class="scripture-text">
                <strong><?= e((string) $verse['verse_number']); ?></strong>
                <?= e((string) $verse['verse_text']); ?>
            </p>
        </div>
    </div>

    <div class="inline-actions">
        <?php if (is_logged_in()): ?>
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
        <?php else: ?>
            <a class="button button-primary" href="<?= e(app_url('login.php')); ?>">Sign in to save</a>
        <?php endif; ?>
    </div>
</article>

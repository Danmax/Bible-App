<section class="reader-selection-panel" data-reader-selection hidden aria-label="Selected verse actions">
    <div class="reader-selection-heading">
        <div>
            <p class="reader-selection-label">Selected verses</p>
            <h2 data-selection-reference></h2>
        </div>
        <button type="button" data-selection-close aria-label="Clear verse selection">✕</button>
    </div>
    <?php if (is_logged_in()): ?>
        <form method="post" action="<?= e(app_url('reader-marks.php')); ?>" data-reader-marks>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <div class="reader-highlight-colors" role="group" aria-label="Highlight selected verses">
                <?php foreach (['neon-yellow' => 'Yellow', 'neon-green' => 'Green', 'neon-blue' => 'Blue', 'neon-orange' => 'Orange', 'neon-pink' => 'Pink'] as $color => $label): ?>
                    <button type="button" class="reader-highlight-color <?= e($color); ?>" data-selection-color="<?= e($color); ?>" aria-label="Highlight <?= e(strtolower($label)); ?>"><span aria-hidden="true">✓</span></button>
                <?php endforeach; ?>
                <button type="button" class="reader-clear-highlight" data-selection-action="clear-highlight" aria-label="Remove highlight" title="Remove highlight">⌫</button>
            </div>
        </form>
    <?php else: ?>
        <a class="reader-signin-link" href="<?= e($readerLoginUrl); ?>">Sign in to save highlights</a>
    <?php endif; ?>
    <div class="reader-selection-actions">
        <button type="button" data-selection-action="copy">Copy</button>
        <button type="button" data-selection-action="share">Share</button>
        <?php if (is_logged_in()): ?>
            <button type="button" data-selection-action="bookmark">Bookmark</button>
            <a href="<?= e($canvasNoteUrl); ?>" data-selection-note>Note</a>
        <?php endif; ?>
    </div>
    <p class="reader-selection-status" data-selection-status role="status" aria-live="polite"></p>
</section>
<p class="reader-save-status" data-reader-save-status role="status" aria-live="polite"></p>

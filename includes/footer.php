<?php

declare(strict_types=1);

$pageScripts = isset($pageScripts) && is_array($pageScripts) ? $pageScripts : [];
?>
        </main>

        <footer class="site-footer">
            <div class="container footer-grid">
                <div>
                    <h3><?= e(APP_NAME); ?></h3>
                    <p>Study The Word Bible, save what matters, and stay rooted in a stronger community rhythm.</p>
                </div>
                <div>
                    <h4>Core Areas</h4>
                    <p>Bible reading, bookmarks, notes, yearly planning, and community events.</p>
                </div>
                <div>
                    <h4>Verse</h4>
                    <p>"Thy word is a lamp unto my feet, and a light unto my path."</p>
                    <p>Psalm 119:105</p>
                </div>
            </div>
            <div class="container footer-base">
                <span>&copy; <span id="year"><?= e(current_year()); ?></span> <?= e(APP_NAME); ?></span>
                <span>PHP + MySQL starter for Hostinger</span>
            </div>
        </footer>
    </div>

    <script src="<?= e(asset_url('assets/js/app.js')); ?>"></script>
    <?php foreach ($pageScripts as $pageScript): ?>
        <?php if (is_string($pageScript) && trim($pageScript) !== ''): ?>
            <script src="<?= e(asset_url($pageScript)); ?>"></script>
        <?php endif; ?>
    <?php endforeach; ?>
</body>
</html>

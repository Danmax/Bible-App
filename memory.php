<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();
$pageTitle = 'Scripture Memory';
$pageDescription = 'Practice saved Scripture with simple, spaced review.';
$activePage = 'tools';
$user = refresh_current_user();
$available = scripture_memory_available();
$cards = [];
$bookmarks = [];
$formError = null;

if ($user === null) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string) ($_POST['action'] ?? '');
        if (!$available) {
            throw new RuntimeException('Run the Scripture memory migration before using this feature.');
        }
        if ($action === 'add') {
            $created = create_scripture_memory_card((int) $user['id'], (int) ($_POST['bookmark_id'] ?? 0));
            set_flash($created ? 'Verse added to memory practice.' : 'That saved verse is already in your practice list.', $created ? 'success' : 'info');
        } elseif ($action === 'review') {
            review_scripture_memory_card((int) ($_POST['card_id'] ?? 0), (int) $user['id'], (string) ($_POST['result'] ?? '') === 'remembered');
            set_flash('Review recorded. Keep going.', 'success');
        }
        redirect('memory.php');
    } catch (Throwable $exception) {
        $formError = $exception->getMessage() === '' ? 'That memory practice update could not be saved.' : $exception->getMessage();
    }
}

try {
    if ($available) {
        $cards = fetch_scripture_memory_cards((int) $user['id']);
    }
    $bookmarks = fetch_bookmarks((int) $user['id']);
} catch (Throwable $exception) {
    $formError = 'Your saved verses could not be loaded right now.';
}

$dueCards = array_values(array_filter($cards, static fn(array $card): bool => strtotime((string) $card['next_review_at']) <= time()));
$practiceCard = $dueCards[0] ?? $cards[0] ?? null;
$memoryBookmarkIds = array_flip(array_map(static fn(array $card): int => (int) $card['bookmark_id'], $cards));
require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container memory-layout">
        <div class="section-heading section-heading-rich"><div><p class="eyebrow">Scripture Memory</p><h1>Carry the Word with you</h1><p>Practice one saved verse at a time. A remembered verse returns later; a missed one comes back tomorrow.</p></div><div class="quick-stat-row"><div class="quick-stat"><strong><?= e((string) count($dueCards)); ?></strong><span>ready today</span></div><div class="quick-stat"><strong><?= e((string) count($cards)); ?></strong><span>in practice</span></div></div></div>
        <?php if (!$available): ?><div class="flash flash-warning">Run <code>sql/add_scripture_memory.sql</code> to enable Scripture memory.</div><?php elseif ($formError): ?><div class="flash flash-warning"><?= e($formError); ?></div><?php endif; ?>

        <div class="memory-grid top-gap">
            <section class="panel memory-practice">
                <div class="passage-study-heading"><div><p class="eyebrow">Practice</p><h2><?= $practiceCard ? e((string) $practiceCard['book_name'] . ' ' . (int) $practiceCard['chapter_number'] . ':' . (int) $practiceCard['verse_number']) : 'Choose a saved verse'; ?></h2></div></div>
                <?php if ($practiceCard): ?>
                    <p class="memory-prompt">Try to say the verse before revealing it.</p>
                    <details class="memory-reveal"><summary>Reveal verse</summary><blockquote><?= e((string) $practiceCard['verse_text']); ?><footer><?= e((string) $practiceCard['translation']); ?></footer></blockquote></details>
                    <form method="post" class="memory-review-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="review"><input type="hidden" name="card_id" value="<?= e((string) $practiceCard['id']); ?>"><button class="button button-secondary" name="result" value="again" type="submit">Practice again tomorrow</button><button class="button button-primary" name="result" value="remembered" type="submit">I remembered it</button></form>
                <?php else: ?><p class="empty-state">Save a verse in the Bible reader, then add it to memory practice below.</p><?php endif; ?>
            </section>
            <aside class="panel memory-add"><p class="eyebrow">Build your list</p><h2>Saved verses</h2><?php if ($bookmarks === []): ?><p class="empty-state">No saved verses yet.</p><a class="button button-primary" href="<?= e(app_url('bible.php')); ?>">Open Bible</a><?php else: ?><div class="memory-bookmark-list"><?php foreach ($bookmarks as $bookmark): ?><div><strong><?= e(format_verse_reference($bookmark)); ?></strong><?php if (isset($memoryBookmarkIds[(int) $bookmark['id']])): ?><span class="pill">In practice</span><?php else: ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="bookmark_id" value="<?= e((string) $bookmark['id']); ?>"><button class="button button-secondary" type="submit">Add</button></form><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?></aside>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

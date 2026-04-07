<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

if (!current_user_has_role(['admin'])) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    $activePage = '';
    require_once dirname(__DIR__) . '/includes/header.php';
    ?>
    <section class="section">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">Restricted</p>
                <h1>Admin access required</h1>
                <p>You do not have permission to manage events.</p>
            </div>
        </div>
    </section>
    <?php
    require_once dirname(__DIR__) . '/includes/footer.php';
    exit;
}

require_once dirname(__DIR__) . '/includes/repository.php';

$pageTitle = 'Admin Events';
$activePage = 'admin';
$pageError = null;
$currentUserId = (int) current_user()['id'];

$validStatuses = ['published', 'draft', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = trim((string) ($_POST['action'] ?? ''));
    $eventId = (int) ($_POST['event_id'] ?? 0);

    try {
        if ($eventId <= 0) {
            throw new RuntimeException('Invalid event selected.');
        }

        if ($action === 'toggle-featured') {
            $statement = db()->prepare(
                'UPDATE community_events SET is_featured = 1 - is_featured WHERE id = :id'
            );
            $statement->execute(['id' => $eventId]);
            set_flash('Featured status toggled.', 'success');
            redirect('admin/events.php' . (isset($_GET['status']) && $_GET['status'] !== 'all' ? '?status=' . urlencode($_GET['status']) : ''));
        }

        if ($action === 'set-status') {
            $newStatus = trim((string) ($_POST['status'] ?? ''));

            if (!in_array($newStatus, $validStatuses, true)) {
                throw new RuntimeException('Invalid status selected.');
            }

            $statement = db()->prepare(
                'UPDATE community_events SET status = :status WHERE id = :id'
            );
            $statement->execute(['status' => $newStatus, 'id' => $eventId]);
            set_flash('Event status updated to ' . $newStatus . '.', 'success');
            redirect('admin/events.php' . (isset($_GET['status']) && $_GET['status'] !== 'all' ? '?status=' . urlencode($_GET['status']) : ''));
        }

        if ($action === 'delete') {
            delete_community_event_record($eventId, $currentUserId, true);
            set_flash('Event deleted.', 'success');
            redirect('admin/events.php' . (isset($_GET['status']) && $_GET['status'] !== 'all' ? '?status=' . urlencode($_GET['status']) : ''));
        }

        throw new RuntimeException('Unknown action.');
    } catch (Throwable $exception) {
        $pageError = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'The change could not be saved because the database is unavailable.';
    }
}

$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
if ($statusFilter !== 'all' && !in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = 'all';
}

$events = [];
$totalEvents = 0;

try {
    if ($statusFilter === 'all') {
        $sql = 'SELECT ce.*, u.name AS creator_name,
                    (SELECT COUNT(*) FROM community_event_rsvps r WHERE r.community_event_id = ce.id AND r.response = \'going\') AS going_count
                FROM community_events ce
                LEFT JOIN users u ON u.id = ce.created_by_user_id
                ORDER BY ce.start_at DESC
                LIMIT 100';
        $events = db()->query($sql)->fetchAll();
    } else {
        $sql = 'SELECT ce.*, u.name AS creator_name,
                    (SELECT COUNT(*) FROM community_event_rsvps r WHERE r.community_event_id = ce.id AND r.response = \'going\') AS going_count
                FROM community_events ce
                LEFT JOIN users u ON u.id = ce.created_by_user_id
                WHERE ce.status = :status
                ORDER BY ce.start_at DESC
                LIMIT 100';
        $stmt = db()->prepare($sql);
        $stmt->execute(['status' => $statusFilter]);
        $events = $stmt->fetchAll();
    }

    $totalEvents = count($events);
} catch (Throwable $exception) {
    $pageError = 'Events could not be loaded: ' . $exception->getMessage();
}

$filterOptions = [
    'all' => 'All',
    'published' => 'Published',
    'draft' => 'Draft',
    'cancelled' => 'Cancelled',
];

$statusPillClasses = [
    'published' => 'pill pill-dark',
    'draft' => 'pill',
    'cancelled' => 'pill',
];

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading section-heading-rich">
            <div>
                <p class="eyebrow">Admin</p>
                <h1>Events</h1>
                <p><?= e((string) $totalEvents); ?> event<?= $totalEvents !== 1 ? 's' : ''; ?> shown.</p>
            </div>

            <div class="hero-actions">
                <a class="button button-secondary" href="<?= e(app_url('admin/index.php')); ?>">Back to Admin</a>
            </div>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="inline-actions top-gap-sm">
            <?php foreach ($filterOptions as $filterValue => $filterLabel): ?>
                <a class="button <?= $statusFilter === $filterValue ? 'button-primary' : 'button-secondary'; ?>"
                   href="<?= e(app_url('admin/events.php' . ($filterValue !== 'all' ? '?status=' . $filterValue : ''))); ?>">
                    <?= e($filterLabel); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($events === []): ?>
            <p class="empty-state top-gap-sm">No events found<?= $statusFilter !== 'all' ? ' with status ' . e($statusFilter) : ''; ?>.</p>
        <?php else: ?>
            <div class="stack-list top-gap-sm">
                <?php foreach ($events as $event): ?>
                    <?php
                    $eventId = (int) $event['id'];
                    $eventStatus = (string) ($event['status'] ?? 'draft');
                    $isFeatured = (int) ($event['is_featured'] ?? 0) === 1;
                    $pillClass = $statusPillClasses[$eventStatus] ?? 'pill';
                    $statusParam = $statusFilter !== 'all' ? '?status=' . urlencode($statusFilter) : '';
                    ?>
                    <div class="list-card">
                        <div>
                            <strong><?= e((string) $event['title']); ?></strong>
                            <p class="muted-copy">
                                <?= e(format_event_datetime((string) ($event['start_at'] ?? ''))); ?>
                                &mdash; by <?= e((string) ($event['creator_name'] ?? 'Unknown')); ?>
                            </p>
                            <div class="inline-actions">
                                <span class="<?= e($pillClass); ?>"><?= e(ucfirst($eventStatus)); ?></span>
                                <?php if ($isFeatured): ?>
                                    <span class="pill pill-dark">Featured</span>
                                <?php endif; ?>
                                <span class="muted-copy"><?= e((string) ($event['going_count'] ?? 0)); ?> going</span>
                            </div>
                        </div>

                        <div class="inline-actions">
                            <form method="post" action="<?= e(app_url('admin/events.php' . $statusParam)); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="toggle-featured">
                                <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                                <button class="button button-secondary" type="submit">
                                    <?= $isFeatured ? 'Unfeature' : 'Feature'; ?>
                                </button>
                            </form>

                            <form method="post" action="<?= e(app_url('admin/events.php' . $statusParam)); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="set-status">
                                <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                                <select name="status" aria-label="Status for <?= e((string) $event['title']); ?>">
                                    <?php foreach ($validStatuses as $statusOption): ?>
                                        <option value="<?= e($statusOption); ?>" <?= $eventStatus === $statusOption ? 'selected' : ''; ?>>
                                            <?= e(ucfirst($statusOption)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="button button-secondary" type="submit">Set Status</button>
                            </form>

                            <form method="post" action="<?= e(app_url('admin/events.php' . $statusParam)); ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="event_id" value="<?= e((string) $eventId); ?>">
                                <button class="button button-secondary" type="submit"
                                        data-confirm="Delete &ldquo;<?= e((string) $event['title']); ?>&rdquo;? This cannot be undone.">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

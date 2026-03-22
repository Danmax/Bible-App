<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

function community_redirect_url(string $categorySlug = 'all', ?int $editEventId = null): string
{
    $params = [];

    if ($categorySlug !== '' && $categorySlug !== 'all') {
        $params['category'] = $categorySlug;
    }

    if ($editEventId !== null) {
        $params['edit'] = $editEventId;
    }

    $query = $params === [] ? '' : '?' . http_build_query($params);

    return app_url('community.php' . $query);
}

function community_format_datetime_input(?string $date): string
{
    if ($date === null || $date === '') {
        return '';
    }

    return date('Y-m-d\TH:i', strtotime($date));
}

function community_parse_datetime_input(?string $value): ?string
{
    $trimmed = trim((string) $value);

    if ($trimmed === '') {
        return null;
    }

    $timestamp = strtotime($trimmed);

    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function community_event_form_defaults(?array $event = null): array
{
    $categoryId = $event['category_id'] ?? null;

    return [
        'title' => (string) ($event['title'] ?? ''),
        'category_id' => $categoryId === null ? '' : (string) $categoryId,
        'event_type' => (string) ($event['event_type'] ?? ''),
        'visibility' => (string) ($event['visibility'] ?? 'public'),
        'location_name' => (string) ($event['location_name'] ?? ''),
        'location_address' => (string) ($event['location_address'] ?? ''),
        'meeting_url' => (string) ($event['meeting_url'] ?? ''),
        'start_at' => community_format_datetime_input($event['start_at'] ?? null),
        'end_at' => community_format_datetime_input($event['end_at'] ?? null),
        'description' => (string) ($event['description'] ?? ''),
        'status' => (string) ($event['status'] ?? 'published'),
        'is_featured' => !empty($event['is_featured']) ? '1' : '0',
    ];
}

function community_validate_event_payload(array $source, array $categoryIds, bool $allowFeatured): array
{
    $formData = [
        'title' => trim((string) ($source['title'] ?? '')),
        'category_id' => trim((string) ($source['category_id'] ?? '')),
        'event_type' => trim((string) ($source['event_type'] ?? '')),
        'visibility' => trim((string) ($source['visibility'] ?? 'public')),
        'location_name' => trim((string) ($source['location_name'] ?? '')),
        'location_address' => trim((string) ($source['location_address'] ?? '')),
        'meeting_url' => trim((string) ($source['meeting_url'] ?? '')),
        'start_at' => trim((string) ($source['start_at'] ?? '')),
        'end_at' => trim((string) ($source['end_at'] ?? '')),
        'description' => trim((string) ($source['description'] ?? '')),
        'status' => trim((string) ($source['status'] ?? 'published')),
        'is_featured' => !empty($source['is_featured']) ? '1' : '0',
    ];

    if ($formData['title'] === '' || $formData['description'] === '' || $formData['event_type'] === '') {
        return [null, $formData, 'Title, event type, and description are required.'];
    }

    $startAt = community_parse_datetime_input($formData['start_at']);

    if ($startAt === null) {
        return [null, $formData, 'Choose a valid start date and time.'];
    }

    $endAt = community_parse_datetime_input($formData['end_at']);

    if ($endAt !== null && strtotime($endAt) < strtotime($startAt)) {
        return [null, $formData, 'The end time must be after the start time.'];
    }

    $visibility = in_array($formData['visibility'], ['public', 'members', 'private'], true)
        ? $formData['visibility']
        : 'public';
    $status = in_array($formData['status'], ['published', 'draft', 'cancelled'], true)
        ? $formData['status']
        : 'published';
    $categoryId = $formData['category_id'] === '' ? null : (int) $formData['category_id'];

    if ($categoryId !== null && !in_array($categoryId, $categoryIds, true)) {
        return [null, $formData, 'Choose a valid event category.'];
    }

    if ($formData['meeting_url'] !== '' && filter_var($formData['meeting_url'], FILTER_VALIDATE_URL) === false) {
        return [null, $formData, 'Meeting link must be a valid URL.'];
    }

    $payload = [
        'title' => $formData['title'],
        'category_id' => $categoryId,
        'event_type' => $formData['event_type'],
        'visibility' => $visibility,
        'location_name' => $formData['location_name'],
        'location_address' => $formData['location_address'],
        'meeting_url' => $formData['meeting_url'],
        'start_at' => $startAt,
        'end_at' => $endAt,
        'description' => $formData['description'],
        'status' => $status,
        'is_featured' => $allowFeatured && $formData['is_featured'] === '1',
    ];

    return [$payload, $formData, null];
}

$pageTitle = 'Community';
$activePage = 'community';
$user = is_logged_in() ? refresh_current_user() : null;
$canManageAllEvents = current_user_has_role(['admin', 'leader']);
$activeCategorySlug = trim((string) ($_GET['category'] ?? 'all'));
$activeCategorySlug = $activeCategorySlug === '' ? 'all' : $activeCategorySlug;
$editingEventId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$pageError = null;
$formError = null;
$categories = [];
$events = [];
$manageableEvents = [];
$editingEvent = null;
$formData = community_event_form_defaults();

try {
    $categories = fetch_event_categories();
} catch (Throwable $exception) {
    $pageError = 'Community events could not be loaded because the database is unavailable.';
}

$categoryIds = array_map(
    static fn(array $category): int => (int) $category['id'],
    $categories
);
$categorySlugMap = [];

foreach ($categories as $category) {
    $categorySlugMap[(string) $category['slug']] = (int) $category['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($user === null) {
        set_flash('Sign in first to manage events or RSVP.', 'warning');
        redirect('login.php');
    }

    $action = (string) ($_POST['action'] ?? '');
    $postCategorySlug = trim((string) ($_POST['category'] ?? $activeCategorySlug));
    $postCategorySlug = $postCategorySlug === '' ? 'all' : $postCategorySlug;

    try {
        if ($action === 'rsvp') {
            $eventId = (int) ($_POST['event_id'] ?? 0);
            $response = (string) ($_POST['response'] ?? '');
            $event = fetch_community_event_by_id($eventId, (int) $user['id'], $canManageAllEvents);

            if ($event === null || $event['status'] === 'draft') {
                throw new RuntimeException('That event is no longer available.');
            }

            if ($response === 'clear') {
                delete_community_event_rsvp($eventId, (int) $user['id']);
                set_flash('RSVP removed.', 'success');
            } elseif (in_array($response, ['going', 'interested', 'maybe', 'not-going'], true)) {
                upsert_community_event_rsvp($eventId, (int) $user['id'], $response);
                set_flash('RSVP updated.', 'success');
            } else {
                throw new RuntimeException('Select a valid RSVP response.');
            }

            redirect(community_redirect_url($postCategorySlug));
        }

        if (!in_array($action, ['create-event', 'update-event', 'delete-event'], true)) {
            throw new RuntimeException('Unknown community action.');
        }

        if ($action === 'delete-event') {
            $eventId = (int) ($_POST['event_id'] ?? 0);
            $event = fetch_community_event_by_id($eventId, (int) $user['id'], $canManageAllEvents);

            if (!can_manage_community_event($event, $user)) {
                throw new RuntimeException('You are not allowed to delete that event.');
            }

            delete_community_event_record($eventId, (int) $user['id'], $canManageAllEvents);
            set_flash('Event deleted.', 'success');
            redirect(community_redirect_url($postCategorySlug));
        }

        [$payload, $formData, $formError] = community_validate_event_payload($_POST, $categoryIds, $canManageAllEvents);

        if ($formError === null && $payload !== null) {
            if ($action === 'create-event') {
                $createdEventId = create_community_event_record((int) $user['id'], $payload);
                set_flash('Community event created.', 'success');
                redirect(community_redirect_url($postCategorySlug, $createdEventId));
            }

            $eventId = (int) ($_POST['event_id'] ?? 0);
            $event = fetch_community_event_by_id($eventId, (int) $user['id'], $canManageAllEvents);

            if (!can_manage_community_event($event, $user)) {
                throw new RuntimeException('You are not allowed to update that event.');
            }

            update_community_event_record($eventId, $payload, (int) $user['id'], $canManageAllEvents);
            set_flash('Community event updated.', 'success');
            redirect(community_redirect_url($postCategorySlug, $eventId));
        }

        $editingEventId = (int) ($_POST['event_id'] ?? $editingEventId ?? 0);
    } catch (Throwable $exception) {
        if ($formError === null) {
            $formError = $exception->getMessage();
        }
    }
}

$activeCategoryId = $categorySlugMap[$activeCategorySlug] ?? null;

if ($activeCategorySlug !== 'all' && $activeCategoryId === null && $categories !== []) {
    $activeCategorySlug = 'all';
}

try {
    $events = fetch_community_events($activeCategorySlug === 'all' ? null : $activeCategoryId, $user['id'] ?? null, $canManageAllEvents);

    if ($user !== null) {
        $manageableEvents = fetch_manageable_community_events((int) $user['id'], $canManageAllEvents);
    }

    if ($editingEventId) {
        $editingEvent = fetch_community_event_by_id((int) $editingEventId, $user['id'] ?? null, $canManageAllEvents);

        if ($editingEvent !== null && !can_manage_community_event($editingEvent, $user)) {
            $editingEvent = null;
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Community events could not be loaded because the database is unavailable.';
}

if ($editingEvent !== null && $formError === null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $formData = community_event_form_defaults($editingEvent);
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Community Feed</p>
            <h1>Gather, publish, and respond to community events</h1>
            <p>Browse the shared church calendar, RSVP to upcoming gatherings, and manage the events your community is planning.</p>
        </div>

        <?php if ($pageError): ?>
            <div class="flash flash-warning"><?= e($pageError); ?></div>
        <?php endif; ?>

        <div class="filter-row">
            <a class="filter-chip <?= $activeCategorySlug === 'all' ? 'is-active' : ''; ?>" href="<?= e(community_redirect_url('all')); ?>">All</a>
            <?php foreach ($categories as $category): ?>
                <a
                    class="filter-chip <?= $activeCategorySlug === (string) $category['slug'] ? 'is-active' : ''; ?>"
                    href="<?= e(community_redirect_url((string) $category['slug'])); ?>"
                >
                    <?= e((string) $category['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="community-layout top-gap">
            <div class="stack-list">
                <?php if ($events === []): ?>
                    <div class="panel">
                        <p class="empty-state">No community events match this view yet. Create one from the manager panel.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                        <?php $canManageThisEvent = can_manage_community_event($event, $user); ?>
                        <article class="event-card community-event-card">
                            <div class="community-card-top">
                                <div class="community-pill-row">
                                    <span class="pill"><?= e((string) ($event['category_label'] ?: 'Community')); ?></span>
                                    <?php if (!empty($event['is_featured'])): ?>
                                        <span class="pill pill-dark">Featured</span>
                                    <?php endif; ?>
                                    <?php if ((string) $event['status'] !== 'published'): ?>
                                        <span class="pill pill-dark"><?= e(ucfirst((string) $event['status'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($canManageThisEvent): ?>
                                    <a class="button button-secondary" href="<?= e(community_redirect_url($activeCategorySlug, (int) $event['id'])); ?>">Edit</a>
                                <?php endif; ?>
                            </div>

                            <h3><?= e((string) $event['title']); ?></h3>
                            <p><?= e((string) $event['description']); ?></p>

                            <div class="community-meta-grid">
                                <span><strong>When:</strong> <?= e(format_event_datetime((string) $event['start_at'])); ?></span>
                                <span><strong>Type:</strong> <?= e((string) $event['event_type']); ?></span>
                                <?php if (!empty($event['location_name'])): ?>
                                    <span><strong>Where:</strong> <?= e((string) $event['location_name']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($event['meeting_url'])): ?>
                                    <span><strong>Online:</strong> Zoom / Meet link attached</span>
                                <?php endif; ?>
                                <?php if (!empty($event['created_by_name'])): ?>
                                    <span><strong>Created by:</strong> <?= e((string) $event['created_by_name']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="community-rsvp-summary">
                                <span class="pill pill-dark"><?= e((string) $event['going_count']); ?> going</span>
                                <span class="pill pill-dark"><?= e((string) $event['interested_count']); ?> interested</span>
                                <span class="pill pill-dark"><?= e((string) $event['maybe_count']); ?> maybe</span>
                            </div>

                            <div class="inline-actions">
                                <?php if (!empty($event['meeting_url'])): ?>
                                    <a class="button button-secondary" href="<?= e((string) $event['meeting_url']); ?>" target="_blank" rel="noreferrer">Open Link</a>
                                <?php endif; ?>
                                <?php if (!empty($event['location_address'])): ?>
                                    <span class="muted-copy"><?= e((string) $event['location_address']); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($user !== null): ?>
                                <div class="community-rsvp-row">
                                    <?php foreach (['going' => 'Going', 'interested' => 'Interested', 'maybe' => 'Maybe', 'not-going' => 'Can’t Go'] as $responseValue => $responseLabel): ?>
                                        <form method="post" action="<?= e(app_url('community.php')); ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="rsvp">
                                            <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                            <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                            <input
                                                type="hidden"
                                                name="response"
                                                value="<?= e($responseValue); ?>"
                                            >
                                            <button
                                                class="filter-chip <?= (string) ($event['current_user_rsvp'] ?? '') === $responseValue ? 'is-active' : ''; ?>"
                                                type="submit"
                                            >
                                                <?= e($responseLabel); ?>
                                            </button>
                                        </form>
                                    <?php endforeach; ?>

                                    <?php if (!empty($event['current_user_rsvp'])): ?>
                                        <form method="post" action="<?= e(app_url('community.php')); ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="rsvp">
                                            <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                            <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                            <input type="hidden" name="response" value="clear">
                                            <button class="button button-secondary" type="submit">Clear RSVP</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p class="muted-copy">Sign in to RSVP, track attendance, and manage your events.</p>
                            <?php endif; ?>

                            <?php if ($canManageThisEvent): ?>
                                <div class="inline-actions">
                                    <a class="button button-secondary" href="<?= e(community_redirect_url($activeCategorySlug, (int) $event['id'])); ?>">Manage Event</a>
                                    <form method="post" action="<?= e(app_url('community.php')); ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="delete-event">
                                        <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                        <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                        <button class="button button-secondary" type="submit">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <aside class="panel community-manager-panel">
                <div class="panel-heading">
                    <div>
                        <h2><?= $editingEvent ? 'Edit event' : 'Create event'; ?></h2>
                        <p class="muted-copy">
                            <?= $user === null ? 'Sign in to create community events and respond to RSVP flows.' : 'Build the shared community calendar from here.'; ?>
                        </p>
                    </div>
                    <?php if ($editingEvent): ?>
                        <a class="button button-secondary" href="<?= e(community_redirect_url($activeCategorySlug)); ?>">New Event</a>
                    <?php endif; ?>
                </div>

                <?php if ($formError): ?>
                    <div class="flash flash-warning"><?= e($formError); ?></div>
                <?php endif; ?>

                <?php if ($user === null): ?>
                    <p class="empty-state">Create an account or sign in to publish events, update your own listings, and collect RSVPs.</p>
                    <div class="inline-actions">
                        <a class="button button-primary" href="<?= e(app_url('login.php')); ?>">Login</a>
                        <a class="button button-secondary" href="<?= e(app_url('register.php')); ?>">Create Account</a>
                    </div>
                <?php else: ?>
                    <form class="form-stack" method="post" action="<?= e(app_url('community.php' . ($editingEvent ? '?edit=' . (int) $editingEvent['id'] : ''))); ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                        <input type="hidden" name="action" value="<?= e($editingEvent ? 'update-event' : 'create-event'); ?>">
                        <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                        <?php if ($editingEvent): ?>
                            <input type="hidden" name="event_id" value="<?= e((string) $editingEvent['id']); ?>">
                        <?php endif; ?>

                        <label>
                            Title
                            <input name="title" required value="<?= e($formData['title']); ?>">
                        </label>

                        <label>
                            Category
                            <select name="category_id">
                                <option value="">Select category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= e((string) $category['id']); ?>" <?= $formData['category_id'] === (string) $category['id'] ? 'selected' : ''; ?>>
                                        <?= e((string) $category['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            Event Type
                            <input name="event_type" placeholder="Worship, Zoom, Outreach, Study" required value="<?= e($formData['event_type']); ?>">
                        </label>

                        <label>
                            Starts
                            <input type="datetime-local" name="start_at" required value="<?= e($formData['start_at']); ?>">
                        </label>

                        <label>
                            Ends
                            <input type="datetime-local" name="end_at" value="<?= e($formData['end_at']); ?>">
                        </label>

                        <label>
                            Location Name
                            <input name="location_name" placeholder="Main Sanctuary or Zoom" value="<?= e($formData['location_name']); ?>">
                        </label>

                        <label>
                            Location Address
                            <input name="location_address" placeholder="123 Main St" value="<?= e($formData['location_address']); ?>">
                        </label>

                        <label>
                            Meeting Link
                            <input name="meeting_url" placeholder="https://..." value="<?= e($formData['meeting_url']); ?>">
                        </label>

                        <label>
                            Visibility
                            <select name="visibility">
                                <?php foreach (['public' => 'Public', 'members' => 'Members', 'private' => 'Private'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= $formData['visibility'] === $value ? 'selected' : ''; ?>>
                                        <?= e($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            Status
                            <select name="status">
                                <?php foreach (['published' => 'Published', 'draft' => 'Draft', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= $formData['status'] === $value ? 'selected' : ''; ?>>
                                        <?= e($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <?php if ($canManageAllEvents): ?>
                            <label class="community-checkbox">
                                <input type="checkbox" name="is_featured" value="1" <?= $formData['is_featured'] === '1' ? 'checked' : ''; ?>>
                                <span>Feature this event on the feed</span>
                            </label>
                        <?php endif; ?>

                        <label>
                            Description
                            <textarea name="description" rows="6" required><?= e($formData['description']); ?></textarea>
                        </label>

                        <div class="inline-actions">
                            <button class="button button-primary" type="submit"><?= $editingEvent ? 'Update Event' : 'Create Event'; ?></button>
                            <?php if ($editingEvent): ?>
                                <a class="button button-secondary" href="<?= e(community_redirect_url($activeCategorySlug)); ?>">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($user !== null): ?>
                    <div class="top-gap">
                        <div class="panel-heading">
                            <div>
                                <h3><?= $canManageAllEvents ? 'Event queue' : 'Your events'; ?></h3>
                                <p class="muted-copy"><?= $canManageAllEvents ? 'Leaders can review every event from this panel.' : 'Quick shortcuts into the events you created.'; ?></p>
                            </div>
                        </div>

                        <?php if ($manageableEvents === []): ?>
                            <p class="empty-state">No events to manage yet.</p>
                        <?php else: ?>
                            <div class="stack-list">
                                <?php foreach ($manageableEvents as $manageableEvent): ?>
                                    <div class="list-card list-card-block">
                                        <div>
                                            <strong><?= e((string) $manageableEvent['title']); ?></strong>
                                            <span><?= e(format_event_datetime((string) $manageableEvent['start_at'])); ?></span>
                                            <span class="muted-copy"><?= e((string) ($manageableEvent['category_label'] ?: $manageableEvent['event_type'])); ?></span>
                                        </div>
                                        <a class="button button-secondary" href="<?= e(community_redirect_url($activeCategorySlug, (int) $manageableEvent['id'])); ?>">Edit</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

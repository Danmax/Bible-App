<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/openai.php';

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

function community_event_format_options(): array
{
    return [
        'standard' => 'Church Gathering',
        'potluck' => 'Potluck',
        'study' => 'Bible Study',
        'prayer' => 'Prayer Gathering',
        'worship' => 'Worship Night',
        'discipleship' => 'Discipleship',
        'outreach' => 'Community Outreach',
        'service' => 'Sunday Service',
        'fellowship' => 'Fellowship',
        'scripture-memory' => 'Scripture Memory',
    ];
}

function community_split_lines(string $value): array
{
    $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
    $normalized = [];

    foreach ($lines as $line) {
        $trimmed = trim((string) $line);

        if ($trimmed === '' || in_array($trimmed, $normalized, true)) {
            continue;
        }

        $normalized[] = $trimmed;
    }

    return $normalized;
}

function community_parse_potluck_item_lines(string $value): array
{
    $items = [];

    foreach (community_split_lines($value) as $line) {
        $parts = explode('|', $line, 2);
        $label = trim((string) ($parts[0] ?? ''));
        $details = trim((string) ($parts[1] ?? ''));

        if ($label === '') {
            continue;
        }

        $items[] = [
            'label' => mb_substr($label, 0, 160),
            'details' => mb_substr($details, 0, 255),
        ];
    }

    return $items;
}

function community_parse_potluck_seed_rows(array $types, array $details): array
{
    $items = [];
    $maxRows = max(count($types), count($details));

    for ($index = 0; $index < $maxRows; $index++) {
        $type = trim((string) ($types[$index] ?? ''));
        $detail = trim((string) ($details[$index] ?? ''));

        if ($type === '' && $detail === '') {
            continue;
        }

        if ($type === '') {
            $type = 'Item';
        }

        $items[] = [
            'label' => mb_substr($type, 0, 160),
            'details' => mb_substr($detail, 0, 255),
        ];
    }

    return $items;
}

function community_custom_options_text(array $options): string
{
    $lines = [];

    foreach ($options as $option) {
        $trimmed = trim((string) $option);

        if ($trimmed === '') {
            continue;
        }

        $lines[] = $trimmed;
    }

    return implode("\n", $lines);
}

function community_potluck_items_text(array $items): string
{
    $lines = [];

    foreach ($items as $item) {
        $label = trim((string) ($item['label'] ?? ''));
        $details = trim((string) ($item['details'] ?? ''));

        if ($label === '') {
            continue;
        }

        $lines[] = $details === '' ? $label : $label . ' | ' . $details;
    }

    return implode("\n", $lines);
}

function community_response_label(string $response): string
{
    return match ($response) {
        'going' => 'Going',
        'interested' => 'Interested',
        'maybe' => 'Maybe',
        'not-going' => "Can't go",
        default => ucfirst(str_replace('-', ' ', $response)),
    };
}

function community_event_current_user_item(array $event, ?int $userId): ?array
{
    if ($userId === null || $userId <= 0) {
        return null;
    }

    foreach (($event['items'] ?? []) as $item) {
        if ((int) ($item['claimed_by_user_id'] ?? 0) === $userId) {
            return $item;
        }
    }

    return null;
}

function community_build_event_reminder_template(array $event, string $type): array
{
    $title = trim((string) ($event['title'] ?? 'Community event'));
    $eventDateLabel = format_event_datetime((string) ($event['start_at'] ?? ''));
    $locationName = trim((string) ($event['location_name'] ?? ''));
    $options = $event['settings']['custom_options'] ?? [];

    if ($type === 'same-day') {
        $subject = $title . ' reminder for today';
        $opening = 'This is your same-day reminder for ' . $title . '.';
    } else {
        $subject = $title . ' reminder for this week';
        $opening = 'This is your 3-day reminder for ' . $title . '.';
    }

    $lines = [
        $opening,
        'Event time: ' . $eventDateLabel,
    ];

    if ($locationName !== '') {
        $lines[] = 'Location: ' . $locationName;
    }

    if ($options !== []) {
        $lines[] = 'Important details: ' . implode(' | ', array_slice(array_map('trim', $options), 0, 4));
    }

    $lines[] = 'Please review your RSVP and the item you committed to bring before the event.';

    return [
        'subject' => $subject,
        'body' => implode("\n\n", $lines),
    ];
}

function community_collect_message_recipients(array $event, string $mode): array
{
    $recipients = [];

    foreach (($event['attendees'] ?? []) as $attendee) {
        $email = trim((string) ($attendee['attendee_email'] ?? ''));
        $response = (string) ($attendee['response'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        if (!in_array($response, ['going', 'interested', 'maybe'], true)) {
            continue;
        }

        if ($mode === 'three-days' && array_key_exists('remind_three_days', $attendee) && empty($attendee['remind_three_days'])) {
            continue;
        }

        if ($mode === 'same-day' && array_key_exists('remind_same_day', $attendee) && empty($attendee['remind_same_day'])) {
            continue;
        }

        $recipients[] = $attendee;
    }

    return $recipients;
}

function community_personalize_event_message_body(string $body, array $attendee): string
{
    $response = trim((string) ($attendee['response'] ?? ''));
    $bringItem = trim((string) (($attendee['bring_item_name'] ?? '') !== '' ? $attendee['bring_item_name'] : ($attendee['bring_item_label'] ?? '')));
    $parts = [trim($body)];

    if ($response !== '') {
        $parts[] = 'Your RSVP: ' . community_response_label($response);
    }

    if ($bringItem !== '') {
        $parts[] = 'Your item to bring: ' . $bringItem;
    }

    return implode("\n\n", array_filter($parts, static fn(string $part): bool => trim($part) !== ''));
}

function community_event_form_defaults(?array $event = null): array
{
    $categoryId = $event['category_id'] ?? null;
    $settings = normalize_community_event_settings((array) ($event['settings'] ?? []));

    return [
        'title' => (string) ($event['title'] ?? ''),
        'category_id' => $categoryId === null ? '' : (string) $categoryId,
        'event_type' => (string) ($event['event_type'] ?? ''),
        'event_format' => (string) ($settings['format'] ?? 'standard'),
        'custom_options_text' => community_custom_options_text((array) ($settings['custom_options'] ?? [])),
        'visibility' => (string) ($event['visibility'] ?? 'public'),
        'image_url' => (string) ($event['image_url'] ?? ''),
        'location_name' => (string) ($event['location_name'] ?? ''),
        'location_address' => (string) ($event['location_address'] ?? ''),
        'meeting_url' => (string) ($event['meeting_url'] ?? ''),
        'start_at' => community_format_datetime_input($event['start_at'] ?? null),
        'end_at' => community_format_datetime_input($event['end_at'] ?? null),
        'description' => (string) ($event['description'] ?? ''),
        'status' => (string) ($event['status'] ?? 'published'),
        'is_featured' => !empty($event['is_featured']) ? '1' : '0',
        'reminder_three_days' => !empty($settings['reminders']['three_days']) ? '1' : '0',
        'reminder_same_day' => !empty($settings['reminders']['same_day']) ? '1' : '0',
        'potluck_allow_self_pick' => !empty($settings['potluck']['allow_self_pick']) ? '1' : '0',
        'potluck_allow_custom_items' => !empty($settings['potluck']['allow_custom_items']) ? '1' : '0',
        'potluck_allow_host_assign' => !empty($settings['potluck']['allow_host_assign']) ? '1' : '0',
        'potluck_items_text' => community_potluck_items_text((array) ($event['items'] ?? [])),
    ];
}

function community_validate_event_payload(array $source, array $categoryIds, bool $allowFeatured): array
{
    $eventImagesAvailable = community_event_images_available();

    $formData = [
        'title' => trim((string) ($source['title'] ?? '')),
        'category_id' => trim((string) ($source['category_id'] ?? '')),
        'event_type' => trim((string) ($source['event_type'] ?? '')),
        'event_format' => trim((string) ($source['event_format'] ?? 'standard')),
        'custom_options_text' => trim((string) ($source['custom_options_text'] ?? '')),
        'visibility' => trim((string) ($source['visibility'] ?? 'public')),
        'image_url' => trim((string) ($source['image_url'] ?? '')),
        'location_name' => trim((string) ($source['location_name'] ?? '')),
        'location_address' => trim((string) ($source['location_address'] ?? '')),
        'meeting_url' => trim((string) ($source['meeting_url'] ?? '')),
        'start_at' => trim((string) ($source['start_at'] ?? '')),
        'end_at' => trim((string) ($source['end_at'] ?? '')),
        'description' => trim((string) ($source['description'] ?? '')),
        'status' => trim((string) ($source['status'] ?? 'published')),
        'is_featured' => !empty($source['is_featured']) ? '1' : '0',
        'reminder_three_days' => !empty($source['reminder_three_days']) ? '1' : '0',
        'reminder_same_day' => !empty($source['reminder_same_day']) ? '1' : '0',
        'potluck_allow_self_pick' => !empty($source['potluck_allow_self_pick']) ? '1' : '0',
        'potluck_allow_custom_items' => !empty($source['potluck_allow_custom_items']) ? '1' : '0',
        'potluck_allow_host_assign' => !empty($source['potluck_allow_host_assign']) ? '1' : '0',
        'potluck_items_text' => trim((string) ($source['potluck_items_text'] ?? '')),
    ];

    if ($formData['title'] === '' || $formData['description'] === '' || $formData['event_type'] === '') {
        return [null, $formData, 'Title, event type, and description are required.'];
    }

    $formatOptions = community_event_format_options();

    if (!array_key_exists($formData['event_format'], $formatOptions)) {
        $formData['event_format'] = 'standard';
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

    if ($formData['image_url'] !== '' && filter_var($formData['image_url'], FILTER_VALIDATE_URL) === false) {
        return [null, $formData, 'Event image must be a valid URL.'];
    }

    if (!$eventImagesAvailable && $formData['image_url'] !== '') {
        return [null, $formData, 'Run the community event image migration before saving an event image.'];
    }

    $customOptions = community_split_lines($formData['custom_options_text']);
    $potluckItems = [];

    if ($formData['event_format'] === 'potluck') {
        $seedTypes = isset($source['potluck_seed_type']) && is_array($source['potluck_seed_type']) ? $source['potluck_seed_type'] : [];
        $seedDetails = isset($source['potluck_seed_detail']) && is_array($source['potluck_seed_detail']) ? $source['potluck_seed_detail'] : [];
        $potluckItems = community_parse_potluck_seed_rows($seedTypes, $seedDetails);

        if ($potluckItems === []) {
            $potluckItems = community_parse_potluck_item_lines($formData['potluck_items_text']);
        }
    }

    $payload = [
        'title' => $formData['title'],
        'category_id' => $categoryId,
        'event_type' => $formData['event_type'],
        'visibility' => $visibility,
        'image_url' => $formData['image_url'],
        'location_name' => $formData['location_name'],
        'location_address' => $formData['location_address'],
        'meeting_url' => $formData['meeting_url'],
        'start_at' => $startAt,
        'end_at' => $endAt,
        'description' => $formData['description'],
        'status' => $status,
        'is_featured' => $allowFeatured && $formData['is_featured'] === '1',
        'settings' => [
            'format' => $formData['event_format'],
            'custom_options' => $customOptions,
            'reminders' => [
                'three_days' => $formData['reminder_three_days'] === '1',
                'same_day' => $formData['reminder_same_day'] === '1',
            ],
            'potluck' => [
                'enabled' => $formData['event_format'] === 'potluck',
                'allow_self_pick' => $formData['event_format'] === 'potluck' && $formData['potluck_allow_self_pick'] === '1',
                'allow_custom_items' => $formData['event_format'] === 'potluck' && $formData['potluck_allow_custom_items'] === '1',
                'allow_host_assign' => $formData['event_format'] === 'potluck' && $formData['potluck_allow_host_assign'] === '1',
            ],
        ],
        'potluck_items' => $potluckItems,
    ];

    return [$payload, $formData, null];
}

$pageTitle = 'Community';
$activePage = 'community';
$user = is_logged_in() ? refresh_current_user() : null;
$canManageAllEvents = current_user_has_role(['admin', 'leader']);
$eventImagesAvailable = community_event_images_available();
$activeCategorySlug = trim((string) ($_GET['category'] ?? 'all'));
$activeCategorySlug = $activeCategorySlug === '' ? 'all' : $activeCategorySlug;
$editingEventId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$pageError = null;
$formError = null;
$categories = [];
$events = [];
$manageableEvents = [];
$manageableEventMap = [];
$editingEvent = null;
$formData = community_event_form_defaults();
$showComposePanel = false;
$eventFormatOptions = community_event_format_options();

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

            if ($event === null || (string) ($event['status'] ?? '') === 'draft') {
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

        if (in_array($action, ['claim-item', 'release-item', 'add-item', 'update-item', 'delete-item', 'assign-item', 'update-item-preferences', 'send-event-message', 'send-event-reminder'], true)) {
            $eventId = (int) ($_POST['event_id'] ?? 0);
            $event = fetch_community_event_by_id($eventId, (int) $user['id'], $canManageAllEvents);

            if ($event === null || (string) ($event['status'] ?? '') === 'draft') {
                throw new RuntimeException('That event is no longer available.');
            }

            $canManageThisEvent = can_manage_community_event($event, $user);
            $settings = normalize_community_event_settings((array) ($event['settings'] ?? []));
            $potluckEnabled = !empty($settings['potluck']['enabled']);

            if (in_array($action, ['claim-item', 'release-item', 'add-item', 'update-item', 'delete-item', 'assign-item'], true) && !$potluckEnabled) {
                throw new RuntimeException('Item planning is only available for potluck events.');
            }

            if ($action === 'claim-item') {
                if (!$canManageThisEvent && empty($settings['potluck']['allow_self_pick'])) {
                    throw new RuntimeException('This potluck is host-assigned only.');
                }

                $itemId = (int) ($_POST['item_id'] ?? 0);
                $response = (string) ($_POST['response'] ?? ($event['current_user_rsvp'] ?? 'going'));
                $response = in_array($response, ['going', 'interested', 'maybe'], true) ? $response : 'going';
                claim_community_event_item(
                    $eventId,
                    $itemId,
                    (int) $user['id'],
                    $response,
                    trim((string) ($_POST['bring_item_note'] ?? '')),
                    !empty($_POST['remind_three_days']),
                    !empty($_POST['remind_same_day'])
                );
                set_flash('Item confirmed for this event.', 'success');
                redirect(community_redirect_url($postCategorySlug));
            }

            if ($action === 'release-item') {
                $itemId = (int) ($_POST['item_id'] ?? 0);
                release_community_event_item_claim($eventId, $itemId, (int) $user['id'], $canManageThisEvent);
                set_flash('Item released back to the list.', 'success');
                redirect(community_redirect_url($postCategorySlug));
            }

            if ($action === 'add-item') {
                if (!$canManageThisEvent && empty($settings['potluck']['allow_custom_items'])) {
                    throw new RuntimeException('Only the event host can add new items to this potluck.');
                }

                $label = trim((string) ($_POST['item_label'] ?? ''));
                $details = trim((string) ($_POST['item_details'] ?? ''));

                if ($label === '') {
                    throw new RuntimeException('Add an item name before saving.');
                }

                $itemId = create_community_event_item($eventId, (int) $user['id'], $label, $details);

                if (!empty($_POST['claim_item'])) {
                    if (!$canManageThisEvent && empty($settings['potluck']['allow_self_pick'])) {
                        throw new RuntimeException('Items can only be assigned by the host for this event.');
                    }

                    $response = (string) ($_POST['response'] ?? ($event['current_user_rsvp'] ?? 'going'));
                    $response = in_array($response, ['going', 'interested', 'maybe'], true) ? $response : 'going';
                    claim_community_event_item(
                        $eventId,
                        $itemId,
                        (int) $user['id'],
                        $response,
                        trim((string) ($_POST['bring_item_note'] ?? '')),
                        !empty($_POST['remind_three_days']),
                        !empty($_POST['remind_same_day'])
                    );
                    set_flash('New item added and assigned to you.', 'success');
                } else {
                    set_flash('New item added to the potluck list.', 'success');
                }

                redirect(community_redirect_url($postCategorySlug));
            }

            if ($action === 'update-item') {
                if (!$canManageThisEvent) {
                    throw new RuntimeException('Only hosts and leaders can update event items.');
                }

                $itemId = (int) ($_POST['item_id'] ?? 0);
                $label = trim((string) ($_POST['item_label'] ?? ''));
                $details = trim((string) ($_POST['item_details'] ?? ''));

                if ($label === '') {
                    throw new RuntimeException('Add an item name before saving changes.');
                }

                update_community_event_item($eventId, $itemId, $label, $details);
                set_flash('Potluck item updated.', 'success');
                redirect(community_redirect_url($postCategorySlug));
            }

            if ($action === 'delete-item') {
                if (!$canManageThisEvent) {
                    throw new RuntimeException('Only hosts and leaders can remove event items.');
                }

                $itemId = (int) ($_POST['item_id'] ?? 0);
                delete_community_event_item($eventId, $itemId);
                set_flash('Potluck item removed.', 'success');
                redirect(community_redirect_url($postCategorySlug));
            }

            if ($action === 'assign-item') {
                if (!$canManageThisEvent) {
                    throw new RuntimeException('Only hosts and leaders can assign items.');
                }

                if (empty($settings['potluck']['allow_host_assign']) && !$canManageAllEvents) {
                    throw new RuntimeException('Host assignment is disabled for this event.');
                }

                $itemId = (int) ($_POST['item_id'] ?? 0);
                $attendeeUserId = (int) ($_POST['attendee_user_id'] ?? 0);

                if ($attendeeUserId <= 0) {
                    throw new RuntimeException('Choose an attendee to assign the item to.');
                }

                assign_community_event_item($eventId, $itemId, $attendeeUserId);
                set_flash('Item assigned to the selected attendee.', 'success');
                redirect(community_redirect_url($postCategorySlug));
            }

            if ($action === 'update-item-preferences') {
                $response = (string) ($event['current_user_rsvp'] ?? '');

                if (!in_array($response, ['going', 'interested', 'maybe'], true)) {
                    throw new RuntimeException('RSVP to the event before saving item reminders.');
                }

                $bringItemId = !empty($event['current_user_bring_item_id']) ? (int) $event['current_user_bring_item_id'] : null;
                $bringItemLabel = trim((string) ($event['current_user_bring_item_label'] ?? ''));

                if ($bringItemId !== null) {
                    $item = fetch_community_event_item_by_id($bringItemId, $eventId);

                    if ($item !== null) {
                        $bringItemLabel = (string) ($item['label'] ?? $bringItemLabel);
                    }
                }

                upsert_community_event_rsvp_details(
                    $eventId,
                    (int) $user['id'],
                    $response,
                    $bringItemId,
                    $bringItemLabel === '' ? null : $bringItemLabel,
                    trim((string) ($_POST['bring_item_note'] ?? '')),
                    !empty($_POST['remind_three_days']),
                    !empty($_POST['remind_same_day'])
                );
                set_flash('Your potluck reminders and item note were updated.', 'success');
                redirect(community_redirect_url($postCategorySlug));
            }

            if ($action === 'send-event-message') {
                if (!$canManageThisEvent) {
                    throw new RuntimeException('Only hosts and leaders can message attendees.');
                }

                if (!mailer_enabled()) {
                    throw new RuntimeException('Email delivery is not configured for event updates.');
                }

                $subject = trim((string) ($_POST['subject'] ?? ''));
                $body = trim((string) ($_POST['body'] ?? ''));

                if ($subject === '' || $body === '') {
                    throw new RuntimeException('Add a subject and message before sending the update.');
                }

                $recipients = community_collect_message_recipients($event, 'message');
                $deliveredCount = 0;

                foreach ($recipients as $recipient) {
                    send_community_event_email(
                        (string) ($recipient['attendee_name'] ?? ''),
                        (string) ($recipient['attendee_email'] ?? ''),
                        (string) $event['title'],
                        format_event_datetime((string) $event['start_at']),
                        $subject,
                        community_personalize_event_message_body($body, $recipient),
                        (string) ($event['meeting_url'] ?? '')
                    );
                    $deliveredCount++;
                }

                create_community_event_message_record(
                    $eventId,
                    (int) $user['id'],
                    'custom-update',
                    $subject,
                    $body,
                    $deliveredCount
                );
                set_flash('Event update sent to ' . $deliveredCount . ' attendee' . ($deliveredCount === 1 ? '' : 's') . '.', 'success');
                redirect(community_redirect_url($postCategorySlug));
            }

            if ($action === 'send-event-reminder') {
                if (!$canManageThisEvent) {
                    throw new RuntimeException('Only hosts and leaders can send reminders.');
                }

                if (!mailer_enabled()) {
                    throw new RuntimeException('Email delivery is not configured for reminders.');
                }

                $reminderType = (string) ($_POST['reminder_type'] ?? 'three-days');

                if (!in_array($reminderType, ['three-days', 'same-day'], true)) {
                    throw new RuntimeException('Choose a valid reminder type.');
                }

                $template = community_build_event_reminder_template($event, $reminderType);
                $recipients = community_collect_message_recipients($event, $reminderType);
                $deliveredCount = 0;

                foreach ($recipients as $recipient) {
                    send_community_event_email(
                        (string) ($recipient['attendee_name'] ?? ''),
                        (string) ($recipient['attendee_email'] ?? ''),
                        (string) $event['title'],
                        format_event_datetime((string) $event['start_at']),
                        (string) $template['subject'],
                        community_personalize_event_message_body((string) $template['body'], $recipient),
                        (string) ($event['meeting_url'] ?? '')
                    );
                    $deliveredCount++;
                }

                create_community_event_message_record(
                    $eventId,
                    (int) $user['id'],
                    $reminderType,
                    (string) $template['subject'],
                    (string) $template['body'],
                    $deliveredCount
                );
                set_flash('Reminder sent to ' . $deliveredCount . ' attendee' . ($deliveredCount === 1 ? '' : 's') . '.', 'success');
                redirect(community_redirect_url($postCategorySlug));
            }
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
                create_community_event_record((int) $user['id'], $payload);
                set_flash('Community event created.', 'success');
                redirect(community_redirect_url($postCategorySlug));
            }

            $eventId = (int) ($_POST['event_id'] ?? 0);
            $event = fetch_community_event_by_id($eventId, (int) $user['id'], $canManageAllEvents);

            if (!can_manage_community_event($event, $user)) {
                throw new RuntimeException('You are not allowed to update that event.');
            }

            update_community_event_record($eventId, $payload, (int) $user['id'], $canManageAllEvents);
            set_flash('Community event updated.', 'success');
            redirect(community_redirect_url($postCategorySlug));
        }

        $editingEventId = (int) ($_POST['event_id'] ?? $editingEventId ?? 0);
    } catch (Throwable $exception) {
        if (in_array($action, ['create-event', 'update-event'], true)) {
            if ($formError === null) {
                $formError = $exception->getMessage();
            }
            $editingEventId = (int) ($_POST['event_id'] ?? $editingEventId ?? 0);
        } else {
            $pageError = $exception->getMessage();
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

    foreach ($manageableEvents as $manageableEvent) {
        $manageableEventMap[(int) $manageableEvent['id']] = $manageableEvent;
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

$showComposePanel = $editingEvent !== null || $formError !== null;

require_once __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">Community Feed</p>
            <h1>Gather, plan, and coordinate Bible-centered events</h1>
            <p>Build potlucks, Bible studies, prayer gatherings, worship nights, discipleship meetups, and Sunday events with RSVP tracking, item commitments, and attendee updates in one place.</p>
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

        <?php if ($user !== null): ?>
            <div data-community-panels>
                <div class="community-action-bar top-gap">
                    <button
                        class="button button-primary"
                        type="button"
                        data-community-panel-toggle="compose"
                        data-compose-create
                        aria-expanded="<?= $showComposePanel ? 'true' : 'false'; ?>"
                    >
                        <?= $editingEvent ? 'Edit Event' : 'Create Event'; ?>
                    </button>
                    <button
                        class="button button-secondary"
                        type="button"
                        data-community-panel-toggle="manage"
                        aria-expanded="false"
                    >
                        Manage Events
                    </button>
                </div>

                <section
                    class="panel-modal"
                    data-community-panel="compose"
                    data-panel-modal
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="community-compose-modal-title"
                    <?= $showComposePanel ? '' : 'hidden aria-hidden="true" style="display: none;"'; ?>
                >
                    <div class="panel community-manager-panel panel-modal-card" data-panel-modal-content>
                        <div class="panel-heading">
                            <div>
                                <h2 id="community-compose-modal-title"><?= $editingEvent ? 'Edit event' : 'Create event'; ?></h2>
                                <p class="muted-copy">Choose a Bible-centered event format, define the details attendees need, and turn on potluck planning when the gathering needs shared items.</p>
                            </div>
                            <button class="button button-secondary" type="button" data-community-panel-close="compose">Close</button>
                        </div>

                        <?php if ($formError): ?>
                            <div class="flash flash-warning"><?= e($formError); ?></div>
                        <?php endif; ?>

                        <div
                            class="community-ai-panel"
                            data-ai-event-builder
                            data-ai-endpoint="<?= e(app_url('community-ai-event.php')); ?>"
                        >
                            <div class="panel-heading">
                                <div>
                                    <h3>AI Event Draft</h3>
                                    <p class="muted-copy">Speak or type a prompt, then review the generated event draft before saving.</p>
                                </div>
                                <span class="pill pill-dark"><?= e(strtoupper(openai_event_model())); ?></span>
                            </div>

                            <label>
                                Prompt
                                <textarea
                                    name="ai_prompt"
                                    rows="4"
                                    placeholder="Example: Create a church potluck after Sunday service with desserts, main dishes, host assignments, and reminder emails."
                                    data-ai-prompt
                                ></textarea>
                            </label>

                            <div class="inline-actions top-gap-sm">
                                <button class="button button-secondary" type="button" data-ai-voice-start <?= openai_event_drafts_enabled() ? '' : 'disabled'; ?>>Voice to Text</button>
                                <button class="button button-secondary" type="button" data-ai-voice-stop hidden>Stop</button>
                                <button class="button button-primary" type="button" data-ai-generate <?= openai_event_drafts_enabled() ? '' : 'disabled'; ?>>Create Draft</button>
                            </div>

                            <p class="muted-copy" data-ai-status>
                                <?= openai_event_drafts_enabled()
                                    ? 'AI drafting fills the form only. Review and edit the result before publishing.'
                                    : 'Add OPENAI_API_KEY to enable AI event drafting.'; ?>
                            </p>
                        </div>

                        <form
                            class="form-stack top-gap"
                            method="post"
                            action="<?= e(app_url('community.php')); ?>"
                            data-community-event-form
                            data-ai-event-form
                        >
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="<?= e($editingEvent ? 'update-event' : 'create-event'); ?>">
                            <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                            <?php if ($editingEvent): ?>
                                <input type="hidden" name="event_id" value="<?= e((string) $editingEvent['id']); ?>">
                            <?php endif; ?>

                            <label>
                                Title
                                <input name="title" required value="<?= e($formData['title']); ?>" data-ai-field="title">
                            </label>

                            <div class="community-form-grid">
                                <label>
                                    Category
                                    <select name="category_id" data-ai-field="category_id">
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
                                    <input name="event_type" placeholder="Bible study, prayer night, worship service" required value="<?= e($formData['event_type']); ?>" data-ai-field="event_type">
                                </label>
                            </div>

                            <div class="community-form-grid">
                                <label>
                                    Event Format
                                    <select name="event_format" data-ai-field="event_format" data-ai-context-field="event_format" data-community-event-format>
                                        <?php foreach ($eventFormatOptions as $value => $label): ?>
                                            <option value="<?= e($value); ?>" <?= $formData['event_format'] === $value ? 'selected' : ''; ?>>
                                                <?= e($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label>
                                    Visibility
                                    <select name="visibility" data-ai-field="visibility">
                                        <?php foreach (['public' => 'Public', 'members' => 'Members', 'private' => 'Private'] as $value => $label): ?>
                                            <option value="<?= e($value); ?>" <?= $formData['visibility'] === $value ? 'selected' : ''; ?>>
                                                <?= e($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>

                            <div class="community-form-grid">
                                <label>
                                    Starts
                                    <input type="datetime-local" name="start_at" required value="<?= e($formData['start_at']); ?>" data-ai-field="start_at">
                                </label>

                                <label>
                                    Ends
                                    <input type="datetime-local" name="end_at" value="<?= e($formData['end_at']); ?>" data-ai-field="end_at">
                                </label>
                            </div>

                            <div class="community-form-grid">
                                <label>
                                    Location Name
                                <input name="location_name" placeholder="Main Sanctuary, Fellowship Hall, or Prayer Room" value="<?= e($formData['location_name']); ?>" data-ai-field="location_name">
                                </label>

                                <label>
                                    Location Address
                                    <input name="location_address" placeholder="123 Main St" value="<?= e($formData['location_address']); ?>" data-ai-field="location_address">
                                </label>
                            </div>

                            <label>
                                Meeting Link
                                <input name="meeting_url" placeholder="https://..." value="<?= e($formData['meeting_url']); ?>" data-ai-field="meeting_url">
                            </label>

                            <label>
                                Event image URL
                                <input
                                    name="image_url"
                                    placeholder="https://example.com/event-photo.jpg"
                                    value="<?= e($formData['image_url']); ?>"
                                    <?= $eventImagesAvailable ? '' : 'disabled'; ?>
                                >
                                <?php if ($eventImagesAvailable): ?>
                                    <span class="muted-copy">Add a flyer, church photo, or event cover image for the event card.</span>
                                <?php else: ?>
                                    <span class="form-note">Event images will be available after `sql/add_community_event_images.sql` is applied to the database.</span>
                                <?php endif; ?>
                            </label>

                            <label>
                                Ministry details
                                <textarea name="custom_options_text" rows="4" placeholder="Bring a Bible and notebook&#10;Childcare available after worship&#10;Prayer requests will be collected at the end"><?= e($formData['custom_options_text']); ?></textarea>
                                <span class="muted-copy">One detail per line. Use this for Scripture focus, childcare, prayer flow, supplies, parking, study prep, or anything attendees should know.</span>
                            </label>

                            <div class="community-inline-settings">
                                <label class="community-checkbox">
                                    <input type="checkbox" name="reminder_three_days" value="1" <?= $formData['reminder_three_days'] === '1' ? 'checked' : ''; ?>>
                                    <span>Default 3-day reminders</span>
                                </label>
                                <label class="community-checkbox">
                                    <input type="checkbox" name="reminder_same_day" value="1" <?= $formData['reminder_same_day'] === '1' ? 'checked' : ''; ?>>
                                    <span>Default same-day reminders</span>
                                </label>
                                <?php if ($canManageAllEvents): ?>
                                    <label class="community-checkbox">
                                        <input type="checkbox" name="is_featured" value="1" <?= $formData['is_featured'] === '1' ? 'checked' : ''; ?> data-ai-field="is_featured">
                                        <span>Feature this event on the feed</span>
                                    </label>
                                <?php endif; ?>
                            </div>

                            <section
                                class="community-inset-panel"
                                data-community-potluck-options
                                <?= $formData['event_format'] === 'potluck' ? '' : 'hidden aria-hidden="true"'; ?>
                            >
                                <div class="panel-heading">
                                    <div>
                                        <h3>Potluck planning</h3>
                                        <p class="muted-copy">Create the initial item list, decide whether attendees can pick items themselves, and let hosts assign dishes or supplies when needed.</p>
                                    </div>
                                </div>

                                <div class="community-inline-settings">
                                    <label class="community-checkbox">
                                        <input type="checkbox" name="potluck_allow_self_pick" value="1" <?= $formData['potluck_allow_self_pick'] === '1' ? 'checked' : ''; ?>>
                                        <span>Attendees can pick their own item</span>
                                    </label>
                                    <label class="community-checkbox">
                                        <input type="checkbox" name="potluck_allow_custom_items" value="1" <?= $formData['potluck_allow_custom_items'] === '1' ? 'checked' : ''; ?>>
                                        <span>Attendees can add new items</span>
                                    </label>
                                    <label class="community-checkbox">
                                        <input type="checkbox" name="potluck_allow_host_assign" value="1" <?= $formData['potluck_allow_host_assign'] === '1' ? 'checked' : ''; ?>>
                                        <span>Host can assign items</span>
                                    </label>
                                </div>

                                <label>
                                    Potluck item list
                                    <div class="community-potluck-seed-builder" data-community-potluck-seed-builder>
                                        <div class="community-potluck-preset-row" data-potluck-preset-row>
                                            <?php foreach ([
                                                'community' => 'Community',
                                                'bbq' => 'BBQ',
                                                'picnic' => 'Picnic',
                                                'thanksgiving' => 'Thanksgiving',
                                                'christmas' => 'Christmas',
                                                'brunch' => 'Brunch',
                                                'chili' => 'Chili',
                                                'pizza' => 'Pizza',
                                                'celebration' => 'Celebration',
                                            ] as $presetValue => $presetLabel): ?>
                                                <button
                                                    class="button button-secondary"
                                                    type="button"
                                                    data-potluck-preset="<?= e($presetValue); ?>"
                                                >
                                                    <?= e($presetLabel); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="community-potluck-seed-list" data-potluck-seed-list></div>
                                        <div class="inline-actions">
                                            <button class="button button-secondary" type="button" data-potluck-seed-add>Add item row</button>
                                        </div>
                                        <textarea
                                            name="potluck_items_text"
                                            rows="6"
                                            hidden
                                            data-potluck-seed-output
                                            data-ai-field="potluck_items_text"
                                        ><?= e($formData['potluck_items_text']); ?></textarea>
                                    </div>
                                    <span class="muted-copy">Choose a preset or use a short type and detail, like `Main dish` and `Lasagna`, then edit the rows as needed.</span>
                                </label>
                            </section>

                            <div class="community-form-grid">
                                <label>
                                    Status
                                    <select name="status" data-ai-field="status">
                                        <?php foreach (['published' => 'Published', 'draft' => 'Draft', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                                            <option value="<?= e($value); ?>" <?= $formData['status'] === $value ? 'selected' : ''; ?>>
                                                <?= e($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>

                            <label>
                                Description
                                <textarea name="description" rows="6" required data-ai-field="description"><?= e($formData['description']); ?></textarea>
                            </label>

                            <div class="inline-actions">
                                <button class="button button-primary" type="submit"><?= $editingEvent ? 'Update Event' : 'Create Event'; ?></button>
                                <a class="button button-secondary" href="<?= e(community_redirect_url($activeCategorySlug)); ?>">Cancel</a>
                            </div>
                        </form>
                    </div>
                </section>

                <section
                    class="panel-modal"
                    data-community-panel="manage"
                    data-panel-modal
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="community-manage-modal-title"
                    hidden
                    aria-hidden="true"
                    style="display: none;"
                >
                    <div class="panel community-manager-panel panel-modal-card" data-panel-modal-content>
                        <div class="panel-heading">
                            <div>
                                <h2 id="community-manage-modal-title"><?= $canManageAllEvents ? 'Event queue' : 'Your events'; ?></h2>
                                <p class="muted-copy"><?= $canManageAllEvents ? 'Leaders can review every event from this panel.' : 'Quick shortcuts into the events you created.'; ?></p>
                            </div>
                            <button class="button button-secondary" type="button" data-community-panel-close="manage">Close</button>
                        </div>

                        <?php if ($manageableEvents === []): ?>
                            <p class="empty-state">No events to manage yet.</p>
                        <?php else: ?>
                            <div class="stack-list">
                                <?php foreach ($manageableEvents as $manageableEvent): ?>
                                    <?php $manageableSettings = normalize_community_event_settings((array) ($manageableEvent['settings'] ?? [])); ?>
                                    <div class="list-card list-card-block">
                                        <div>
                                            <strong><?= e((string) $manageableEvent['title']); ?></strong>
                                            <span><?= e(format_event_datetime((string) $manageableEvent['start_at'])); ?></span>
                                            <span class="muted-copy"><?= e((string) ($manageableEvent['category_label'] ?: $manageableEvent['event_type'])); ?></span>
                                            <span class="muted-copy">
                                                <?= e($eventFormatOptions[(string) ($manageableSettings['format'] ?? 'standard')] ?? 'Standard'); ?>
                                                <?php if (!empty($manageableSettings['potluck']['enabled'])): ?>
                                                    • <?= e((string) ($manageableEvent['claimed_item_count'] ?? 0)); ?>/<?= e((string) ($manageableEvent['potluck_item_count'] ?? 0)); ?> items claimed
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php $manageEditPayload = [
                                            'id' => (int) $manageableEvent['id'],
                                            'title' => (string) ($manageableEvent['title'] ?? ''),
                                            'category_id' => (string) ($manageableEvent['category_id'] ?? ''),
                                            'event_type' => (string) ($manageableEvent['event_type'] ?? ''),
                                            'event_format' => (string) ($manageableSettings['format'] ?? 'standard'),
                                            'visibility' => (string) ($manageableEvent['visibility'] ?? 'public'),
                                            'image_url' => (string) ($manageableEvent['image_url'] ?? ''),
                                            'location_name' => (string) ($manageableEvent['location_name'] ?? ''),
                                            'location_address' => (string) ($manageableEvent['location_address'] ?? ''),
                                            'meeting_url' => (string) ($manageableEvent['meeting_url'] ?? ''),
                                            'start_at' => community_format_datetime_input($manageableEvent['start_at'] ?? null),
                                            'end_at' => community_format_datetime_input($manageableEvent['end_at'] ?? null),
                                            'description' => (string) ($manageableEvent['description'] ?? ''),
                                            'status' => (string) ($manageableEvent['status'] ?? 'published'),
                                            'is_featured' => !empty($manageableEvent['is_featured']),
                                            'reminder_three_days' => !empty($manageableSettings['reminders']['three_days']),
                                            'reminder_same_day' => !empty($manageableSettings['reminders']['same_day']),
                                            'custom_options_text' => implode("\n", array_filter(array_map('trim', $manageableSettings['custom_options'] ?? []))),
                                            'potluck_items_text' => community_potluck_items_text($manageableEvent['items'] ?? []),
                                            'potluck_allow_self_pick' => !empty($manageableSettings['potluck']['allow_self_pick']),
                                            'potluck_allow_custom_items' => !empty($manageableSettings['potluck']['allow_custom_items']),
                                            'potluck_allow_host_assign' => !empty($manageableSettings['potluck']['allow_host_assign']),
                                        ]; ?>
                                        <button
                                            class="button button-secondary"
                                            type="button"
                                            data-edit-event="<?= e(json_encode($manageEditPayload)); ?>"
                                        >Edit</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        <?php else: ?>
            <div class="panel top-gap">
                <p class="empty-state">Create an account or sign in to publish events, update your listings, RSVP, and manage potluck items.</p>
                <div class="inline-actions">
                    <a class="button button-primary" href="<?= e(app_url('login.php')); ?>">Login</a>
                    <a class="button button-secondary" href="<?= e(app_url('register.php')); ?>">Create Account</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="stack-list top-gap">
            <?php if ($events === []): ?>
                <div class="panel">
                    <p class="empty-state">No community events match this view yet. Create one from the event controls above.</p>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <?php
                    $canManageThisEvent = can_manage_community_event($event, $user);
                    $managerEvent = $manageableEventMap[(int) $event['id']] ?? $event;
                    $settings = normalize_community_event_settings((array) ($event['settings'] ?? []));
                    $potluckEnabled = !empty($settings['potluck']['enabled']);
                    $currentUserItem = community_event_current_user_item($event, $user['id'] ?? null);
                    $currentUserResponse = (string) ($event['current_user_rsvp'] ?? '');
                    $currentUserBringItemLabel = trim((string) ($event['current_user_bring_item_label'] ?? ''));
                    $currentUserRemindThreeDays = array_key_exists('current_user_remind_three_days', $event)
                        ? !empty($event['current_user_remind_three_days'])
                        : !empty($settings['reminders']['three_days']);
                    $currentUserRemindSameDay = array_key_exists('current_user_remind_same_day', $event)
                        ? !empty($event['current_user_remind_same_day'])
                        : !empty($settings['reminders']['same_day']);
                    $attendees = (array) ($managerEvent['attendees'] ?? []);
                    $recentMessages = (array) ($managerEvent['recent_messages'] ?? ($event['recent_messages'] ?? []));
                    $eventFormatLabel = $eventFormatOptions[(string) ($settings['format'] ?? 'standard')] ?? 'Standard';
                    $eventDateLabel = format_event_datetime((string) $event['start_at']);
                    $eventLocationLabel = trim((string) ($event['location_name'] ?? ''));
                    $eventHostLabel = trim((string) ($event['created_by_name'] ?? ''));
                    $eventModeLabel = !empty($event['meeting_url']) && $eventLocationLabel !== ''
                        ? 'In person + online'
                        : (!empty($event['meeting_url']) ? 'Online event' : 'In person');
                    $assignableAttendees = array_values(array_filter(
                        $attendees,
                        static fn(array $attendee): bool => in_array((string) ($attendee['response'] ?? ''), ['going', 'interested', 'maybe'], true)
                    ));
                    ?>
                    <?php
                    $editPayload = $canManageThisEvent ? [
                        'id' => (int) $event['id'],
                        'title' => (string) ($event['title'] ?? ''),
                        'category_id' => (string) ($event['category_id'] ?? ''),
                        'event_type' => (string) ($event['event_type'] ?? ''),
                        'event_format' => (string) ($settings['format'] ?? 'standard'),
                        'visibility' => (string) ($event['visibility'] ?? 'public'),
                        'image_url' => (string) ($event['image_url'] ?? ''),
                        'location_name' => (string) ($event['location_name'] ?? ''),
                        'location_address' => (string) ($event['location_address'] ?? ''),
                        'meeting_url' => (string) ($event['meeting_url'] ?? ''),
                        'start_at' => community_format_datetime_input($event['start_at'] ?? null),
                        'end_at' => community_format_datetime_input($event['end_at'] ?? null),
                        'description' => (string) ($event['description'] ?? ''),
                        'status' => (string) ($event['status'] ?? 'published'),
                        'is_featured' => !empty($event['is_featured']),
                        'reminder_three_days' => !empty($settings['reminders']['three_days']),
                        'reminder_same_day' => !empty($settings['reminders']['same_day']),
                        'custom_options_text' => implode("\n", array_filter(array_map('trim', $settings['custom_options'] ?? []))),
                        'potluck_items_text' => community_potluck_items_text($event['items'] ?? []),
                        'potluck_allow_self_pick' => !empty($settings['potluck']['allow_self_pick']),
                        'potluck_allow_custom_items' => !empty($settings['potluck']['allow_custom_items']),
                        'potluck_allow_host_assign' => !empty($settings['potluck']['allow_host_assign']),
                    ] : null;
                    $totalRsvp = (int) ($event['going_count'] ?? 0) + (int) ($event['interested_count'] ?? 0) + (int) ($event['maybe_count'] ?? 0);
                    ?>
                    <article class="event-card community-event-card">
                        <?php if (!empty($event['image_url'])): ?>
                            <div class="community-event-image-wrap">
                                <img
                                    class="community-event-image"
                                    src="<?= e((string) $event['image_url']); ?>"
                                    alt="<?= e((string) $event['title']); ?>"
                                    loading="lazy"
                                >
                            </div>
                        <?php endif; ?>

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
                            <?php if ($canManageThisEvent && $editPayload !== null): ?>
                                <button
                                    class="button button-secondary button-sm"
                                    type="button"
                                    data-edit-event="<?= e(json_encode($editPayload)); ?>"
                                >Edit</button>
                            <?php endif; ?>
                        </div>

                        <div class="community-card-summary">
                            <h3><?= e((string) $event['title']); ?></h3>
                            <p class="community-card-meta">
                                <?= e($eventDateLabel); ?>
                                <?php if ($eventLocationLabel !== ''): ?>
                                    <span class="community-card-meta-sep">·</span><?= e($eventLocationLabel); ?>
                                <?php elseif (!empty($event['meeting_url'])): ?>
                                    <span class="community-card-meta-sep">·</span>Online
                                <?php endif; ?>
                            </p>
                        </div>

                        <?php if ($user !== null): ?>
                            <div class="community-rsvp-row">
                                <?php foreach (['going' => 'Going', 'interested' => 'Interested', 'maybe' => 'Maybe', 'not-going' => "Can't Go"] as $responseValue => $responseLabel): ?>
                                    <form method="post" action="<?= e(app_url('community.php')); ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="rsvp">
                                        <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                        <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                        <input type="hidden" name="response" value="<?= e($responseValue); ?>">
                                        <button
                                            class="filter-chip <?= $currentUserResponse === $responseValue ? 'is-active' : ''; ?>"
                                            type="submit"
                                        ><?= e($responseLabel); ?></button>
                                    </form>
                                <?php endforeach; ?>
                                <?php if ($currentUserResponse !== ''): ?>
                                    <form method="post" action="<?= e(app_url('community.php')); ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="rsvp">
                                        <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                        <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                        <input type="hidden" name="response" value="clear">
                                        <button class="button button-secondary button-sm" type="submit">Clear</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="muted-copy">Sign in to RSVP and manage your attendance.</p>
                        <?php endif; ?>

                        <details class="community-card-details">
                            <summary class="community-card-details-toggle">
                                <?php
                                $detailParts = [];
                                if ($totalRsvp > 0) $detailParts[] = $totalRsvp . ' attending';
                                if ($potluckEnabled) $detailParts[] = ($event['claimed_item_count'] ?? 0) . '/' . ($event['potluck_item_count'] ?? 0) . ' items';
                                echo e($detailParts !== [] ? implode(' · ', $detailParts) : 'Details');
                                ?>
                            </summary>

                            <div class="community-card-details-body">
                                <p class="community-event-description"><?= e((string) $event['description']); ?></p>

                                <div class="community-event-facts">
                                    <div class="community-event-fact">
                                        <span class="community-event-fact-label">When</span>
                                        <strong><?= e($eventDateLabel); ?></strong>
                                    </div>
                                    <div class="community-event-fact">
                                        <span class="community-event-fact-label">Focus</span>
                                        <strong><?= e((string) $event['event_type']); ?></strong>
                                    </div>
                                    <div class="community-event-fact">
                                        <span class="community-event-fact-label">Place</span>
                                        <strong><?= e($eventLocationLabel !== '' ? $eventLocationLabel : $eventModeLabel); ?></strong>
                                    </div>
                                    <?php if ($eventHostLabel !== ''): ?>
                                        <div class="community-event-fact">
                                            <span class="community-event-fact-label">Host</span>
                                            <strong><?= e($eventHostLabel); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (($settings['custom_options'] ?? []) !== []): ?>
                                    <div class="community-option-list">
                                        <?php foreach ($settings['custom_options'] as $option): ?>
                                            <span class="community-option-chip"><?= e((string) $option); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($event['location_address'])): ?>
                                    <p class="community-event-address"><?= e((string) $event['location_address']); ?></p>
                                <?php endif; ?>

                                <div class="inline-actions">
                                    <a class="button button-secondary button-sm" href="<?= e(app_url('community-event-calendar.php?event_id=' . (int) $event['id'])); ?>">Add to Calendar</a>
                                    <?php if (!empty($event['meeting_url'])): ?>
                                        <a class="button button-secondary button-sm" href="<?= e((string) $event['meeting_url']); ?>" target="_blank" rel="noreferrer">Open Link</a>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </details>

                        <?php if ($potluckEnabled): ?>
                            <section class="community-event-subpanel">
                                <div class="panel-heading">
                                    <div>
                                        <h4>Potluck planner</h4>
                                        <p class="muted-copy">Attendees can commit to items, hosts can assign items, and everyone can see what is still open.</p>
                                    </div>
                                </div>

                                <?php if ($currentUserItem !== null): ?>
                                    <div class="community-current-item">
                                        <strong>You're bringing:</strong> <?= e((string) $currentUserItem['label']); ?>
                                        <span class="muted-copy">RSVP: <?= e(community_response_label($currentUserResponse !== '' ? $currentUserResponse : 'going')); ?></span>
                                    </div>
                                <?php elseif ($currentUserBringItemLabel !== ''): ?>
                                    <div class="community-current-item">
                                        <strong>You're bringing:</strong> <?= e($currentUserBringItemLabel); ?>
                                        <span class="muted-copy">RSVP: <?= e(community_response_label($currentUserResponse !== '' ? $currentUserResponse : 'going')); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($user !== null && in_array($currentUserResponse, ['going', 'interested', 'maybe'], true)): ?>
                                    <form method="post" action="<?= e(app_url('community.php')); ?>" class="community-preference-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="update-item-preferences">
                                        <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                        <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                        <label>
                                            Item note
                                            <input
                                                name="bring_item_note"
                                                maxlength="255"
                                                placeholder="Add quantity, prep notes, or delivery details"
                                                value="<?= e((string) ($event['current_user_bring_item_note'] ?? '')); ?>"
                                            >
                                        </label>
                                        <div class="community-inline-settings">
                                            <label class="community-checkbox">
                                                <input type="checkbox" name="remind_three_days" value="1" <?= $currentUserRemindThreeDays ? 'checked' : ''; ?>>
                                                <span>3-day reminder</span>
                                            </label>
                                            <label class="community-checkbox">
                                                <input type="checkbox" name="remind_same_day" value="1" <?= $currentUserRemindSameDay ? 'checked' : ''; ?>>
                                                <span>Same-day reminder</span>
                                            </label>
                                        </div>
                                        <button class="button button-secondary" type="submit">Save my plan</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($user === null): ?>
                                    <div class="community-claim-banner">
                                        <strong>Want to bring something?</strong>
                                        <span class="muted-copy">Sign in first so you can RSVP and claim an item for this potluck.</span>
                                    </div>
                                <?php elseif (!in_array($currentUserResponse, ['going', 'interested', 'maybe'], true)): ?>
                                    <div class="community-claim-banner">
                                        <strong>Ready to help with the potluck?</strong>
                                        <span class="muted-copy">Choose Going, Interested, or Maybe above, then open any available item and confirm what you want to bring.</span>
                                    </div>
                                <?php elseif (!$canManageThisEvent && empty($settings['potluck']['allow_self_pick'])): ?>
                                    <div class="community-claim-banner">
                                        <strong>This potluck is host assigned.</strong>
                                        <span class="muted-copy">The host will match attendees to items, but you can still save your note and reminders above.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="community-claim-banner">
                                        <strong>Claim to bring from the list below.</strong>
                                        <span class="muted-copy">Open any available item, add your note if needed, and confirm it for this event.</span>
                                    </div>
                                <?php endif; ?>

                                <div class="community-item-list">
                                    <?php if (($event['items'] ?? []) === []): ?>
                                        <p class="empty-state">No items have been added yet.</p>
                                    <?php else: ?>
                                        <?php foreach ($event['items'] as $item): ?>
                                            <?php
                                            $itemStatus = (string) ($item['status'] ?? 'open');
                                            $itemIsClaimedByCurrentUser = $user !== null && (int) ($item['claimed_by_user_id'] ?? 0) === (int) ($user['id'] ?? 0);
                                            $canSelfPick = $user !== null && ($canManageThisEvent || !empty($settings['potluck']['allow_self_pick']));
                                            ?>
                                            <div class="community-item-row">
                                                <div class="community-item-copy">
                                                    <div class="community-item-heading">
                                                        <span class="pill"><?= e((string) $item['label']); ?></span>
                                                        <strong><?= e((string) (($item['details'] ?? '') !== '' ? $item['details'] : $item['label'])); ?></strong>
                                                        <span class="pill pill-dark"><?= e(ucfirst($itemStatus)); ?></span>
                                                    </div>
                                                    <span class="muted-copy">Type: <?= e((string) $item['label']); ?></span>
                                                    <?php if (!empty($item['claimed_by_name'])): ?>
                                                        <span class="muted-copy">
                                                            <?= $itemStatus === 'assigned' ? 'Assigned to' : 'Claimed by'; ?> <?= e((string) $item['claimed_by_name']); ?>
                                                            <?php if (!empty($item['assigned_by_host'])): ?>
                                                                • host assigned
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="muted-copy">Still open</span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="community-item-actions">
                                                    <?php if ($itemStatus === 'open'): ?>
                                                        <?php if ($user !== null && $canSelfPick && in_array($currentUserResponse, ['going', 'interested', 'maybe'], true)): ?>
                                                            <form method="post" action="<?= e(app_url('community.php')); ?>" class="community-inline-form">
                                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                                <input type="hidden" name="action" value="claim-item">
                                                                <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                                                <input type="hidden" name="item_id" value="<?= e((string) $item['id']); ?>">
                                                                <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                                                <input type="hidden" name="response" value="<?= e((string) ($currentUserResponse !== '' ? $currentUserResponse : 'going')); ?>">
                                                                <input type="hidden" name="bring_item_note" value="<?= e((string) ($event['current_user_bring_item_note'] ?? '')); ?>">
                                                                <input type="hidden" name="remind_three_days" value="<?= $currentUserRemindThreeDays ? '1' : '0'; ?>">
                                                                <input type="hidden" name="remind_same_day" value="<?= $currentUserRemindSameDay ? '1' : '0'; ?>">
                                                                <button class="button button-primary" type="submit">Claim</button>
                                                            </form>
                                                        <?php elseif ($user === null): ?>
                                                            <span class="muted-copy">Sign in to claim this item.</span>
                                                        <?php elseif (!in_array($currentUserResponse, ['going', 'interested', 'maybe'], true)): ?>
                                                            <span class="muted-copy">RSVP above to claim this item.</span>
                                                        <?php elseif (!$canManageThisEvent && empty($settings['potluck']['allow_self_pick'])): ?>
                                                            <span class="muted-copy">Host assignment only.</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <?php if ($user !== null && ($itemIsClaimedByCurrentUser || $canManageThisEvent && !empty($item['claimed_by_user_id']))): ?>
                                                        <form method="post" action="<?= e(app_url('community.php')); ?>" class="community-inline-form">
                                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                            <input type="hidden" name="action" value="release-item">
                                                            <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                                            <input type="hidden" name="item_id" value="<?= e((string) $item['id']); ?>">
                                                            <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                                            <button class="button button-secondary" type="submit"><?= $itemIsClaimedByCurrentUser ? 'Release item' : 'Clear assignment'; ?></button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <?php if ($canManageThisEvent && !empty($settings['potluck']['allow_host_assign']) && $assignableAttendees !== []): ?>
                                                        <form method="post" action="<?= e(app_url('community.php')); ?>" class="community-inline-form">
                                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                            <input type="hidden" name="action" value="assign-item">
                                                            <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                                            <input type="hidden" name="item_id" value="<?= e((string) $item['id']); ?>">
                                                            <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                                            <select name="attendee_user_id">
                                                                <option value="">Assign to attendee</option>
                                                                <?php foreach ($assignableAttendees as $attendee): ?>
                                                                    <option value="<?= e((string) $attendee['user_id']); ?>">
                                                                        <?= e((string) $attendee['attendee_name']); ?> · <?= e(community_response_label((string) $attendee['response'])); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button class="button button-secondary" type="submit">Assign</button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <?php if ($canManageThisEvent): ?>
                                                        <form method="post" action="<?= e(app_url('community.php')); ?>" class="community-inline-form community-item-manage-form">
                                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                            <input type="hidden" name="action" value="update-item">
                                                            <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                                            <input type="hidden" name="item_id" value="<?= e((string) $item['id']); ?>">
                                                            <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                                            <input name="item_label" value="<?= e((string) $item['label']); ?>" placeholder="Type" maxlength="160">
                                                            <input name="item_details" value="<?= e((string) ($item['details'] ?? '')); ?>" placeholder="Detail" maxlength="255">
                                                            <button class="button button-secondary" type="submit">Save item</button>
                                                        </form>

                                                        <form method="post" action="<?= e(app_url('community.php')); ?>" class="community-inline-form">
                                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                            <input type="hidden" name="action" value="delete-item">
                                                            <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                                            <input type="hidden" name="item_id" value="<?= e((string) $item['id']); ?>">
                                                            <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                                            <button class="button button-secondary" type="submit">Delete item</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <?php if ($user !== null && ($canManageThisEvent || !empty($settings['potluck']['allow_custom_items']))): ?>
                                    <form method="post" action="<?= e(app_url('community.php')); ?>" class="community-item-create-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="add-item">
                                        <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                        <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                        <input type="hidden" name="response" value="<?= e((string) (($event['current_user_rsvp'] ?? '') !== '' ? $event['current_user_rsvp'] : 'going')); ?>">
                                        <input type="hidden" name="remind_three_days" value="1">
                                        <input type="hidden" name="remind_same_day" value="1">
                                        <input name="item_label" placeholder="Type" maxlength="160">
                                        <input name="item_details" placeholder="Detail" maxlength="255">
                                        <?php if ($canManageThisEvent || !empty($settings['potluck']['allow_self_pick'])): ?>
                                            <label class="community-checkbox">
                                                <input type="checkbox" name="claim_item" value="1">
                                                <span>Claim this new item now</span>
                                            </label>
                                        <?php endif; ?>
                                        <button class="button button-secondary" type="submit">Add item</button>
                                    </form>
                                <?php endif; ?>
                            </section>
                        <?php endif; ?>

                        <?php if ($canManageThisEvent): ?>
                            <details class="community-host-details">
                                <summary class="community-host-summary">Host tools</summary>
                            <section class="community-event-subpanel">
                                <div class="panel-heading">
                                    <div>
                                        <h4>Host tools</h4>
                                        <p class="muted-copy">Track attendee status, see who is bringing what, and send updates or reminders from one place.</p>
                                    </div>
                                </div>

                                <div class="community-host-grid">
                                    <div class="community-attendee-list">
                                        <strong>Attendees</strong>
                                        <?php if ($attendees === []): ?>
                                            <p class="muted-copy">No RSVPs yet.</p>
                                        <?php else: ?>
                                            <?php foreach ($attendees as $attendee): ?>
                                                <div class="community-attendee-row">
                                                    <div>
                                                        <strong><?= e((string) $attendee['attendee_name']); ?></strong>
                                                        <span class="muted-copy"><?= e(community_response_label((string) $attendee['response'])); ?></span>
                                                    </div>
                                                    <div class="muted-copy">
                                                        <?php if (!empty($attendee['bring_item_name']) || !empty($attendee['bring_item_label'])): ?>
                                                            Bringing <?= e((string) (($attendee['bring_item_name'] ?? '') !== '' ? $attendee['bring_item_name'] : $attendee['bring_item_label'])); ?>
                                                        <?php else: ?>
                                                            No item assigned yet
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="community-message-stack">
                                        <form method="post" action="<?= e(app_url('community.php')); ?>" class="community-message-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="send-event-message">
                                            <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                            <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                            <label>
                                                Update subject
                                                <input name="subject" placeholder="Parking update or final headcount">
                                            </label>
                                            <label>
                                                Message
                                                <textarea name="body" rows="4" placeholder="Share arrival details, prep requests, setup notes, or any change attendees should know."></textarea>
                                            </label>
                                            <button class="button button-secondary" type="submit">Send update</button>
                                        </form>

                                        <div class="community-reminder-grid">
                                            <form method="post" action="<?= e(app_url('community.php')); ?>">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="send-event-reminder">
                                                <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                                <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                                <input type="hidden" name="reminder_type" value="three-days">
                                                <button class="button button-secondary" type="submit">Send 3-day reminder</button>
                                            </form>
                                            <form method="post" action="<?= e(app_url('community.php')); ?>">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                                <input type="hidden" name="action" value="send-event-reminder">
                                                <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                                <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                                <input type="hidden" name="reminder_type" value="same-day">
                                                <button class="button button-secondary" type="submit">Send same-day reminder</button>
                                            </form>
                                        </div>

                                        <div class="community-message-log">
                                            <strong>Recent messages</strong>
                                            <?php if ($recentMessages === []): ?>
                                                <p class="muted-copy">No updates have been sent yet.</p>
                                            <?php else: ?>
                                                <?php foreach ($recentMessages as $message): ?>
                                                    <div class="community-message-log-item">
                                                        <strong><?= e((string) $message['subject']); ?></strong>
                                                        <span class="muted-copy">
                                                            <?= e((string) ($message['sender_name'] ?? 'Host')); ?> ·
                                                            <?= e(date('M j, g:i a', strtotime((string) $message['created_at']))); ?> ·
                                                            <?= e((string) $message['delivered_count']); ?> delivered
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </section>
                            </details>

                            <div class="inline-actions">
                                <form method="post" action="<?= e(app_url('community.php')); ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete-event">
                                    <input type="hidden" name="event_id" value="<?= e((string) $event['id']); ?>">
                                    <input type="hidden" name="category" value="<?= e($activeCategorySlug); ?>">
                                    <button class="button button-secondary" type="submit">Delete event</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

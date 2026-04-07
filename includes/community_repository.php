<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function fetch_event_categories(): array
{
    $statement = db()->query(
        'SELECT *
        FROM community_event_categories
        ORDER BY label ASC'
    );

    return $statement->fetchAll();
}

function fetch_community_events(?int $categoryId, ?int $userId, bool $canManageAllEvents): array
{
    $params = [];
    $conditions = [];

    if (!$canManageAllEvents) {
        if ($userId !== null) {
            $conditions[] = "(ce.status = 'published' OR ce.created_by_user_id = :user_id_filter)";
            $params['user_id_filter'] = $userId;
        } else {
            $conditions[] = "ce.status = 'published'";
        }
        $conditions[] = "ce.visibility != 'private'";
    }

    if ($categoryId !== null) {
        $conditions[] = 'ce.category_id = :category_id';
        $params['category_id'] = $categoryId;
    }

    $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $rsvpJoin = $userId !== null
        ? 'LEFT JOIN community_event_rsvps rsvp ON rsvp.community_event_id = ce.id AND rsvp.user_id = :user_id'
        : '';

    if ($userId !== null) {
        $params['user_id'] = $userId;
    }

    $rsvpSelect = $userId !== null
        ? ', rsvp.response AS current_user_rsvp, rsvp.bring_item_id AS current_user_bring_item_id, rsvp.bring_item_label AS current_user_bring_item_label, rsvp.bring_item_note AS current_user_bring_item_note, rsvp.remind_three_days, rsvp.remind_same_day'
        : '';

    $statement = db()->prepare(
        "SELECT ce.*, cat.slug AS category_slug, cat.label AS category_label, cat.icon AS category_icon, cat.color AS category_color{$rsvpSelect}
        FROM community_events ce
        LEFT JOIN community_event_categories cat ON cat.id = ce.category_id
        {$rsvpJoin}
        {$where}
        ORDER BY ce.is_featured DESC, ce.start_at ASC, ce.id DESC"
    );
    $statement->execute($params);

    $events = $statement->fetchAll();

    foreach ($events as &$event) {
        $event['settings'] = normalize_community_event_settings(
            json_decode((string) ($event['settings_json'] ?? ''), true) ?? []
        );
        $event['attendees'] = [];
        $event['items'] = [];
    }

    return $events;
}

function fetch_community_event_by_id(int $eventId, ?int $userId, bool $canManageAllEvents): ?array
{
    $params = ['id' => $eventId];

    $rsvpJoin = $userId !== null
        ? 'LEFT JOIN community_event_rsvps rsvp ON rsvp.community_event_id = ce.id AND rsvp.user_id = :user_id'
        : '';

    if ($userId !== null) {
        $params['user_id'] = $userId;
    }

    $rsvpSelect = $userId !== null
        ? ', rsvp.response AS current_user_rsvp, rsvp.bring_item_id AS current_user_bring_item_id, rsvp.bring_item_label AS current_user_bring_item_label, rsvp.bring_item_note AS current_user_bring_item_note, rsvp.remind_three_days, rsvp.remind_same_day'
        : '';

    $statement = db()->prepare(
        "SELECT ce.*, cat.slug AS category_slug, cat.label AS category_label, cat.icon AS category_icon, cat.color AS category_color{$rsvpSelect}
        FROM community_events ce
        LEFT JOIN community_event_categories cat ON cat.id = ce.category_id
        {$rsvpJoin}
        WHERE ce.id = :id
        LIMIT 1"
    );
    $statement->execute($params);

    $event = $statement->fetch();

    if ($event === false) {
        return null;
    }

    if (!$canManageAllEvents && (string) ($event['status'] ?? '') === 'draft' && (int) ($event['created_by_user_id'] ?? 0) !== $userId) {
        return null;
    }

    $event['settings'] = normalize_community_event_settings(
        json_decode((string) ($event['settings_json'] ?? ''), true) ?? []
    );

    $event['attendees'] = fetch_community_event_attendees($eventId);
    $event['items'] = fetch_community_event_items($eventId);
    tally_community_event_rsvp_counts($event);

    return $event;
}

function fetch_manageable_community_events(int $userId, bool $canManageAllEvents): array
{
    if ($canManageAllEvents) {
        $statement = db()->query(
            "SELECT ce.*, cat.slug AS category_slug, cat.label AS category_label
            FROM community_events ce
            LEFT JOIN community_event_categories cat ON cat.id = ce.category_id
            WHERE ce.status != 'cancelled'
                AND ce.start_at >= NOW() - INTERVAL 7 DAY
            ORDER BY ce.start_at ASC, ce.id DESC
            LIMIT 100"
        );
    } else {
        $statement = db()->prepare(
            "SELECT ce.*, cat.slug AS category_slug, cat.label AS category_label
            FROM community_events ce
            LEFT JOIN community_event_categories cat ON cat.id = ce.category_id
            WHERE ce.created_by_user_id = :user_id
                AND ce.status != 'cancelled'
                AND ce.start_at >= NOW() - INTERVAL 7 DAY
            ORDER BY ce.start_at ASC, ce.id DESC
            LIMIT 50"
        );
        $statement->execute(['user_id' => $userId]);
    }

    $events = $statement->fetchAll();

    foreach ($events as &$event) {
        $event['settings'] = normalize_community_event_settings(
            json_decode((string) ($event['settings_json'] ?? ''), true) ?? []
        );
        $event['attendees'] = fetch_community_event_attendees((int) $event['id']);
        $event['items'] = fetch_community_event_items((int) $event['id']);
        tally_community_event_rsvp_counts($event);
    }

    return $events;
}

function fetch_community_event_attendees(int $eventId): array
{
    $statement = db()->prepare(
        'SELECT r.*, u.name AS attendee_name, u.email AS attendee_email
        FROM community_event_rsvps r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.community_event_id = :event_id
        ORDER BY r.created_at ASC'
    );
    $statement->execute(['event_id' => $eventId]);

    return $statement->fetchAll();
}

function tally_community_event_rsvp_counts(array &$event): void
{
    $going = 0;
    $interested = 0;
    $maybe = 0;

    foreach (($event['attendees'] ?? []) as $attendee) {
        match ((string) ($attendee['response'] ?? '')) {
            'going' => $going++,
            'interested' => $interested++,
            'maybe' => $maybe++,
            default => null,
        };
    }

    $event['going_count'] = $going;
    $event['interested_count'] = $interested;
    $event['maybe_count'] = $maybe;

    $items = $event['items'] ?? [];
    $event['potluck_item_count'] = count($items);
    $event['claimed_item_count'] = count(array_filter($items, static fn(array $i): bool => (string) ($i['status'] ?? '') === 'claimed'));
}

function fetch_community_event_items(int $eventId): array
{
    $statement = db()->prepare(
        'SELECT i.*, u.name AS claimed_by_name
        FROM community_event_items i
        LEFT JOIN users u ON u.id = i.claimed_by_user_id
        WHERE i.community_event_id = :event_id
        ORDER BY i.sort_order ASC, i.id ASC'
    );
    $statement->execute(['event_id' => $eventId]);

    return $statement->fetchAll();
}

function fetch_community_event_item_by_id(int $itemId, int $eventId): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM community_event_items
        WHERE id = :id
            AND community_event_id = :event_id
        LIMIT 1'
    );
    $statement->execute([
        'id' => $itemId,
        'event_id' => $eventId,
    ]);

    $item = $statement->fetch();

    return $item ?: null;
}

function create_community_event_record(int $userId, array $payload): int
{
    $settingsJson = json_encode($payload['settings'] ?? [], JSON_UNESCAPED_SLASHES);

    $statement = db()->prepare(
        'INSERT INTO community_events
            (created_by_user_id, category_id, title, description, event_type, settings_json, visibility, image_url, location_name, location_address, meeting_url, start_at, end_at, is_featured, status)
        VALUES
            (:user_id, :category_id, :title, :description, :event_type, :settings_json, :visibility, :image_url, :location_name, :location_address, :meeting_url, :start_at, :end_at, :is_featured, :status)'
    );
    $statement->execute([
        'user_id' => $userId,
        'category_id' => $payload['category_id'],
        'title' => $payload['title'],
        'description' => $payload['description'],
        'event_type' => $payload['event_type'],
        'settings_json' => $settingsJson,
        'visibility' => $payload['visibility'],
        'image_url' => $payload['image_url'] !== '' ? $payload['image_url'] : null,
        'location_name' => $payload['location_name'] !== '' ? $payload['location_name'] : null,
        'location_address' => $payload['location_address'] !== '' ? $payload['location_address'] : null,
        'meeting_url' => $payload['meeting_url'] !== '' ? $payload['meeting_url'] : null,
        'start_at' => $payload['start_at'],
        'end_at' => $payload['end_at'],
        'is_featured' => $payload['is_featured'] ? 1 : 0,
        'status' => $payload['status'],
    ]);

    $eventId = (int) db()->lastInsertId();

    if (isset($payload['potluck_items']) && $payload['potluck_items'] !== []) {
        insert_community_event_items($eventId, $payload['potluck_items']);
    }

    return $eventId;
}

function update_community_event_record(int $eventId, array $payload, int $userId, bool $canManageAllEvents): void
{
    $settingsJson = json_encode($payload['settings'] ?? [], JSON_UNESCAPED_SLASHES);

    $ownerCondition = $canManageAllEvents ? '' : 'AND created_by_user_id = :user_id';
    $params = [
        'id' => $eventId,
        'category_id' => $payload['category_id'],
        'title' => $payload['title'],
        'description' => $payload['description'],
        'event_type' => $payload['event_type'],
        'settings_json' => $settingsJson,
        'visibility' => $payload['visibility'],
        'image_url' => $payload['image_url'] !== '' ? $payload['image_url'] : null,
        'location_name' => $payload['location_name'] !== '' ? $payload['location_name'] : null,
        'location_address' => $payload['location_address'] !== '' ? $payload['location_address'] : null,
        'meeting_url' => $payload['meeting_url'] !== '' ? $payload['meeting_url'] : null,
        'start_at' => $payload['start_at'],
        'end_at' => $payload['end_at'],
        'is_featured' => $payload['is_featured'] ? 1 : 0,
        'status' => $payload['status'],
    ];

    if (!$canManageAllEvents) {
        $params['user_id'] = $userId;
    }

    $statement = db()->prepare(
        "UPDATE community_events
        SET category_id = :category_id,
            title = :title,
            description = :description,
            event_type = :event_type,
            settings_json = :settings_json,
            visibility = :visibility,
            image_url = :image_url,
            location_name = :location_name,
            location_address = :location_address,
            meeting_url = :meeting_url,
            start_at = :start_at,
            end_at = :end_at,
            is_featured = :is_featured,
            status = :status
        WHERE id = :id {$ownerCondition}"
    );
    $statement->execute($params);

    if (isset($payload['potluck_items'])) {
        $format = (string) (($payload['settings']['format'] ?? '') ?: '');

        if ($format === 'potluck' && $payload['potluck_items'] !== []) {
            db()->prepare('DELETE FROM community_event_items WHERE community_event_id = :event_id AND claimed_by_user_id IS NULL')
                ->execute(['event_id' => $eventId]);
            insert_community_event_items($eventId, $payload['potluck_items']);
        }
    }
}

function delete_community_event_record(int $eventId, int $userId, bool $canManageAllEvents): void
{
    $ownerCondition = $canManageAllEvents ? '' : 'AND created_by_user_id = :user_id';
    $params = ['id' => $eventId];

    if (!$canManageAllEvents) {
        $params['user_id'] = $userId;
    }

    $statement = db()->prepare("DELETE FROM community_events WHERE id = :id {$ownerCondition}");
    $statement->execute($params);
}


function upsert_community_event_rsvp(int $eventId, int $userId, string $response): void
{
    $statement = db()->prepare(
        'INSERT INTO community_event_rsvps (community_event_id, user_id, response)
        VALUES (:event_id, :user_id, :response)
        ON DUPLICATE KEY UPDATE response = :response_update'
    );
    $statement->execute([
        'event_id' => $eventId,
        'user_id' => $userId,
        'response' => $response,
        'response_update' => $response,
    ]);
}

function delete_community_event_rsvp(int $eventId, int $userId): void
{
    $statement = db()->prepare(
        'DELETE FROM community_event_rsvps
        WHERE community_event_id = :event_id
            AND user_id = :user_id'
    );
    $statement->execute([
        'event_id' => $eventId,
        'user_id' => $userId,
    ]);
}

function upsert_community_event_rsvp_details(
    int $eventId,
    int $userId,
    string $response,
    ?int $bringItemId,
    ?string $bringItemName,
    string $bringItemNote,
    bool $remindThreeDays,
    bool $remindSameDay
): void {
    $statement = db()->prepare(
        'INSERT INTO community_event_rsvps
            (community_event_id, user_id, response, bring_item_id, bring_item_label, bring_item_note, remind_three_days, remind_same_day)
        VALUES
            (:event_id, :user_id, :response, :bring_item_id, :bring_item_label, :bring_item_note, :remind_three_days, :remind_same_day)
        ON DUPLICATE KEY UPDATE
            response = :response_update,
            bring_item_id = :bring_item_id_update,
            bring_item_label = :bring_item_label_update,
            bring_item_note = :bring_item_note_update,
            remind_three_days = :remind_three_days_update,
            remind_same_day = :remind_same_day_update'
    );
    $statement->execute([
        'event_id' => $eventId,
        'user_id' => $userId,
        'response' => $response,
        'bring_item_id' => $bringItemId,
        'bring_item_label' => $bringItemName,
        'bring_item_note' => $bringItemNote !== '' ? $bringItemNote : null,
        'remind_three_days' => $remindThreeDays ? 1 : 0,
        'remind_same_day' => $remindSameDay ? 1 : 0,
        'response_update' => $response,
        'bring_item_id_update' => $bringItemId,
        'bring_item_label_update' => $bringItemName,
        'bring_item_note_update' => $bringItemNote !== '' ? $bringItemNote : null,
        'remind_three_days_update' => $remindThreeDays ? 1 : 0,
        'remind_same_day_update' => $remindSameDay ? 1 : 0,
    ]);
}

function create_community_event_item(int $eventId, int $userId, string $label, string $details): int
{
    $statement = db()->prepare(
        'INSERT INTO community_event_items (community_event_id, created_by_user_id, label, details)
        VALUES (:event_id, :user_id, :label, :details)'
    );
    $statement->execute([
        'event_id' => $eventId,
        'user_id' => $userId,
        'label' => mb_substr(trim($label), 0, 160),
        'details' => $details !== '' ? mb_substr(trim($details), 0, 255) : null,
    ]);

    return (int) db()->lastInsertId();
}

function update_community_event_item(int $eventId, int $itemId, string $label, string $details): void
{
    $statement = db()->prepare(
        'UPDATE community_event_items
        SET label = :label,
            details = :details
        WHERE id = :id
            AND community_event_id = :event_id'
    );
    $statement->execute([
        'id' => $itemId,
        'event_id' => $eventId,
        'label' => mb_substr(trim($label), 0, 160),
        'details' => $details !== '' ? mb_substr(trim($details), 0, 255) : null,
    ]);
}

function delete_community_event_item(int $eventId, int $itemId): void
{
    $statement = db()->prepare(
        'DELETE FROM community_event_items
        WHERE id = :id
            AND community_event_id = :event_id'
    );
    $statement->execute([
        'id' => $itemId,
        'event_id' => $eventId,
    ]);
}

function claim_community_event_item(
    int $eventId,
    int $itemId,
    int $userId,
    string $response,
    string $note,
    bool $remindThreeDays,
    bool $remindSameDay
): void {
    db()->beginTransaction();

    try {
        $statement = db()->prepare(
            'UPDATE community_event_items
            SET claimed_by_user_id = :user_id,
                status = :status
            WHERE id = :id
                AND community_event_id = :event_id
                AND (claimed_by_user_id IS NULL OR claimed_by_user_id = :user_id_check)'
        );
        $statement->execute([
            'user_id' => $userId,
            'status' => 'claimed',
            'id' => $itemId,
            'event_id' => $eventId,
            'user_id_check' => $userId,
        ]);

        $rsvpStatement = db()->prepare(
            'INSERT INTO community_event_rsvps
                (community_event_id, user_id, response, bring_item_id, bring_item_note, remind_three_days, remind_same_day)
            VALUES
                (:event_id, :user_id, :response, :item_id, :note, :remind_three_days, :remind_same_day)
            ON DUPLICATE KEY UPDATE
                response = :response_update,
                bring_item_id = :item_id_update,
                bring_item_note = :note_update,
                remind_three_days = :remind_three_days_update,
                remind_same_day = :remind_same_day_update'
        );
        $rsvpStatement->execute([
            'event_id' => $eventId,
            'user_id' => $userId,
            'response' => $response,
            'item_id' => $itemId,
            'note' => $note !== '' ? $note : null,
            'remind_three_days' => $remindThreeDays ? 1 : 0,
            'remind_same_day' => $remindSameDay ? 1 : 0,
            'response_update' => $response,
            'item_id_update' => $itemId,
            'note_update' => $note !== '' ? $note : null,
            'remind_three_days_update' => $remindThreeDays ? 1 : 0,
            'remind_same_day_update' => $remindSameDay ? 1 : 0,
        ]);

        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}

function release_community_event_item_claim(int $eventId, int $itemId, int $userId, bool $canManageEvent): void
{
    db()->beginTransaction();

    try {
        $ownerCondition = $canManageEvent ? '' : 'AND claimed_by_user_id = :user_id_check';
        $params = [
            'id' => $itemId,
            'event_id' => $eventId,
            'status' => 'open',
        ];

        if (!$canManageEvent) {
            $params['user_id_check'] = $userId;
        }

        $statement = db()->prepare(
            "UPDATE community_event_items
            SET claimed_by_user_id = NULL,
                status = :status,
                assigned_by_host = 0
            WHERE id = :id
                AND community_event_id = :event_id
                {$ownerCondition}"
        );
        $statement->execute($params);

        $rsvpStatement = db()->prepare(
            'UPDATE community_event_rsvps
            SET bring_item_id = NULL,
                bring_item_label = NULL,
                bring_item_note = NULL
            WHERE community_event_id = :event_id
                AND user_id = :user_id
                AND bring_item_id = :item_id'
        );
        $rsvpStatement->execute([
            'event_id' => $eventId,
            'user_id' => $userId,
            'item_id' => $itemId,
        ]);

        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}

function assign_community_event_item(int $eventId, int $itemId, int $attendeeUserId): void
{
    db()->beginTransaction();

    try {
        $statement = db()->prepare(
            'UPDATE community_event_items
            SET claimed_by_user_id = :user_id,
                status = :status,
                assigned_by_host = 1
            WHERE id = :id
                AND community_event_id = :event_id'
        );
        $statement->execute([
            'user_id' => $attendeeUserId,
            'status' => 'claimed',
            'id' => $itemId,
            'event_id' => $eventId,
        ]);

        $rsvpStatement = db()->prepare(
            'UPDATE community_event_rsvps
            SET bring_item_id = :item_id
            WHERE community_event_id = :event_id
                AND user_id = :user_id'
        );
        $rsvpStatement->execute([
            'item_id' => $itemId,
            'event_id' => $eventId,
            'user_id' => $attendeeUserId,
        ]);

        db()->commit();
    } catch (Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
}

function create_community_event_message_record(
    int $eventId,
    int $userId,
    string $type,
    string $subject,
    string $body,
    int $deliveredCount
): void {
    $statement = db()->prepare(
        'INSERT INTO community_event_messages
            (community_event_id, sender_user_id, message_type, subject, body, delivered_count)
        VALUES
            (:event_id, :user_id, :type, :subject, :body, :delivered_count)'
    );
    $statement->execute([
        'event_id' => $eventId,
        'user_id' => $userId,
        'type' => $type,
        'subject' => mb_substr($subject, 0, 180),
        'body' => $body,
        'delivered_count' => $deliveredCount,
    ]);
}

function normalize_community_event_settings(array $settings): array
{
    return [
        'format' => (string) ($settings['format'] ?? 'standard'),
        'custom_options' => (array) ($settings['custom_options'] ?? []),
        'reminders' => [
            'three_days' => !empty($settings['reminders']['three_days']),
            'same_day' => !empty($settings['reminders']['same_day']),
        ],
        'potluck' => [
            'enabled' => !empty($settings['potluck']['enabled']),
            'allow_self_pick' => !empty($settings['potluck']['allow_self_pick']),
            'allow_custom_items' => !empty($settings['potluck']['allow_custom_items']),
            'allow_host_assign' => !empty($settings['potluck']['allow_host_assign']),
        ],
    ];
}

function community_event_images_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW COLUMNS FROM community_events LIKE 'image_url'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function insert_community_event_items(int $eventId, array $items): void
{
    $statement = db()->prepare(
        'INSERT INTO community_event_items (community_event_id, label, details, sort_order)
        VALUES (:event_id, :label, :details, :sort_order)'
    );

    foreach ($items as $index => $item) {
        $statement->execute([
            'event_id' => $eventId,
            'label' => mb_substr(trim((string) ($item['label'] ?? '')), 0, 160),
            'details' => trim((string) ($item['details'] ?? '')) !== '' ? mb_substr(trim((string) $item['details']), 0, 255) : null,
            'sort_order' => $index,
        ]);
    }
}

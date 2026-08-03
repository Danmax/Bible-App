<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Build the personalized Good News dashboard feed from activity across the app.
 *
 * @return array<int, array{type: string, timestamp: int, data: array<string, mixed>}>
 */
function fetch_dashboard_feed(int $userId, int $limit = 15): array
{
    if ($userId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $queryLimit = $limit;
    $feed = [];
    $pdo = db();

    $plannerStatement = $pdo->prepare(
        'SELECT id, title, description, event_date, event_type,
            UNIX_TIMESTAMP(event_date) AS feed_timestamp
        FROM planner_events
        WHERE user_id = :user_id
            AND event_date >= CURDATE()
            AND event_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ORDER BY event_date ASC, id ASC
        LIMIT ' . $queryLimit
    );
    $plannerStatement->execute(['user_id' => $userId]);

    foreach ($plannerStatement->fetchAll() as $event) {
        $feed[] = [
            'type' => 'planner_event',
            'timestamp' => (int) $event['feed_timestamp'],
            'data' => [
                'id' => (int) $event['id'],
                'title' => (string) $event['title'],
                'description' => (string) ($event['description'] ?? ''),
                'event_date' => (string) $event['event_date'],
                'event_type' => (string) $event['event_type'],
            ],
        ];
    }

    $communityStatement = $pdo->prepare(
        "SELECT ce.id, ce.title, ce.start_at, ce.location_name, rsvp.response,
            UNIX_TIMESTAMP(ce.start_at) AS feed_timestamp
        FROM community_event_rsvps AS rsvp
        INNER JOIN community_events AS ce ON ce.id = rsvp.community_event_id
        WHERE rsvp.user_id = :user_id
            AND rsvp.response IN ('going', 'interested')
            AND ce.status = 'published'
            AND ce.start_at >= NOW()
            AND ce.start_at < DATE_ADD(NOW(), INTERVAL 7 DAY)
        ORDER BY ce.start_at ASC, ce.id ASC
        LIMIT " . $queryLimit
    );
    $communityStatement->execute(['user_id' => $userId]);

    foreach ($communityStatement->fetchAll() as $event) {
        $feed[] = [
            'type' => 'community_event',
            'timestamp' => (int) $event['feed_timestamp'],
            'data' => [
                'id' => (int) $event['id'],
                'title' => (string) $event['title'],
                'start_at' => (string) $event['start_at'],
                'location_name' => (string) ($event['location_name'] ?? ''),
                'response' => (string) $event['response'],
            ],
        ];
    }

    $friendRequestStatement = $pdo->prepare(
        "SELECT fi.id, fi.sender_user_id, fi.created_at, sender.name AS sender_name,
            sender.avatar_url AS sender_avatar_url,
            UNIX_TIMESTAMP(fi.created_at) AS feed_timestamp
        FROM friend_invites AS fi
        INNER JOIN users AS sender ON sender.id = fi.sender_user_id
        INNER JOIN users AS recipient ON recipient.id = :user_id
        WHERE fi.status = 'pending'
            AND fi.expires_at >= NOW()
            AND (
                fi.recipient_user_id = recipient.id
                OR (
                    fi.recipient_user_id IS NULL
                    AND fi.recipient_email = recipient.email
                )
            )
        ORDER BY fi.created_at DESC, fi.id DESC
        LIMIT " . $queryLimit
    );
    $friendRequestStatement->execute(['user_id' => $userId]);

    foreach ($friendRequestStatement->fetchAll() as $request) {
        $feed[] = [
            'type' => 'friend_request',
            'timestamp' => (int) $request['feed_timestamp'],
            'data' => [
                'id' => (int) $request['id'],
                'sender_user_id' => (int) $request['sender_user_id'],
                'sender_name' => (string) $request['sender_name'],
                'sender_avatar_url' => (string) ($request['sender_avatar_url'] ?? ''),
                'created_at' => (string) $request['created_at'],
            ],
        ];
    }

    $friendBookmarkStatement = $pdo->prepare(
        'SELECT bookmarks.id, bookmarks.created_at, friend.id AS friend_user_id,
            friend.name AS friend_name, friend.avatar_url AS friend_avatar_url,
            verses.book_id, books.name AS book_name, verses.chapter_number,
            verses.verse_number, verses.translation, verses.verse_text,
            UNIX_TIMESTAMP(bookmarks.created_at) AS feed_timestamp
        FROM friendships
        INNER JOIN users AS friend ON friend.id = CASE
            WHEN friendships.user_one_id = :user_id_friend THEN friendships.user_two_id
            ELSE friendships.user_one_id
        END
        INNER JOIN bookmarks ON bookmarks.user_id = friend.id
        INNER JOIN verses ON verses.id = bookmarks.verse_id
        INNER JOIN books ON books.id = verses.book_id
        WHERE (friendships.user_one_id = :user_id_one OR friendships.user_two_id = :user_id_two)
            AND bookmarks.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
        ORDER BY bookmarks.created_at DESC, bookmarks.id DESC
        LIMIT 1'
    );
    $friendBookmarkStatement->execute([
        'user_id_friend' => $userId,
        'user_id_one' => $userId,
        'user_id_two' => $userId,
    ]);

    $bookmark = $friendBookmarkStatement->fetch();

    if ($bookmark !== false) {
        $feed[] = [
            'type' => 'friend_bookmark',
            'timestamp' => (int) $bookmark['feed_timestamp'],
            'data' => [
                'id' => (int) $bookmark['id'],
                'friend_user_id' => (int) $bookmark['friend_user_id'],
                'friend_name' => (string) $bookmark['friend_name'],
                'friend_avatar_url' => (string) ($bookmark['friend_avatar_url'] ?? ''),
                'book_id' => (int) $bookmark['book_id'],
                'book_name' => (string) $bookmark['book_name'],
                'chapter_number' => (int) $bookmark['chapter_number'],
                'verse_number' => (int) $bookmark['verse_number'],
                'translation' => (string) $bookmark['translation'],
                'verse_text' => (string) $bookmark['verse_text'],
                'created_at' => (string) $bookmark['created_at'],
            ],
        ];
    }

    usort(
        $feed,
        static fn(array $left, array $right): int => $right['timestamp'] <=> $left['timestamp']
    );

    return array_slice($feed, 0, $limit);
}

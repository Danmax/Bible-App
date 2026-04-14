<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/system_repository.php';
require_once __DIR__ . '/user_repository.php';
require_once __DIR__ . '/planner_repository.php';
require_once __DIR__ . '/bible_repository.php';
require_once __DIR__ . '/community_repository.php';
require_once __DIR__ . '/sermon_repository.php';

function fetch_dashboard_stats(int $userId): array
{
    return [
        'bookmarks' => count_records(
            'SELECT COUNT(*) FROM bookmarks WHERE user_id = :user_id',
            ['user_id' => $userId]
        ),
        'notes' => count_records(
            'SELECT COUNT(*) FROM study_notes WHERE user_id = :user_id',
            ['user_id' => $userId]
        ),
        'goals' => count_records(
            'SELECT COUNT(*) FROM yearly_goals WHERE user_id = :user_id AND status = :status',
            ['user_id' => $userId, 'status' => 'active']
        ),
        'events' => count_records(
            'SELECT COUNT(*) FROM community_events WHERE status = :status AND start_at >= NOW()',
            ['status' => 'published']
        ),
    ];
}

function format_event_date(?string $date): string
{
    if ($date === null || $date === '') {
        return 'TBD';
    }

    return date('M d', strtotime($date));
}

function format_event_datetime(?string $date): string
{
    if ($date === null || $date === '') {
        return 'TBD';
    }

    return date('M j, Y g:i A', strtotime($date));
}

function truncate_text(string $text, int $length = 140): string
{
    $trimmed = trim($text);

    if (mb_strlen($trimmed) <= $length) {
        return $trimmed;
    }

    return rtrim(mb_substr($trimmed, 0, $length - 3)) . '...';
}

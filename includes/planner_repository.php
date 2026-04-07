<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function fetch_yearly_goals_for_user(int $userId, int $year): array
{
    $statement = db()->prepare(
        'SELECT *
        FROM yearly_goals
        WHERE user_id = :user_id
            AND year = :year
        ORDER BY
            CASE status
                WHEN :active_status THEN 0
                WHEN :paused_status THEN 1
                WHEN :completed_status THEN 2
                ELSE 3
            END,
            updated_at DESC,
            id DESC'
    );
    $statement->execute([
        'user_id' => $userId,
        'year' => $year,
        'active_status' => 'active',
        'paused_status' => 'paused',
        'completed_status' => 'completed',
    ]);

    return $statement->fetchAll();
}

function fetch_yearly_goal_by_id(int $goalId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM yearly_goals
        WHERE id = :id
            AND user_id = :user_id
        LIMIT 1'
    );
    $statement->execute([
        'id' => $goalId,
        'user_id' => $userId,
    ]);

    $goal = $statement->fetch();

    return $goal ?: null;
}

function create_yearly_goal_record(int $userId, array $payload): int
{
    $statement = db()->prepare(
        'INSERT INTO yearly_goals (user_id, year, goal_title, goal_type, target_value, current_value, status)
        VALUES (:user_id, :year, :goal_title, :goal_type, :target_value, :current_value, :status)'
    );
    $statement->execute([
        'user_id' => $userId,
        'year' => $payload['year'],
        'goal_title' => $payload['goal_title'],
        'goal_type' => $payload['goal_type'],
        'target_value' => $payload['target_value'],
        'current_value' => $payload['current_value'],
        'status' => $payload['status'],
    ]);

    return (int) db()->lastInsertId();
}

function update_yearly_goal_record(int $goalId, int $userId, array $payload): void
{
    $statement = db()->prepare(
        'UPDATE yearly_goals
        SET year = :year,
            goal_title = :goal_title,
            goal_type = :goal_type,
            target_value = :target_value,
            current_value = :current_value,
            status = :status
        WHERE id = :id
            AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $goalId,
        'user_id' => $userId,
        'year' => $payload['year'],
        'goal_title' => $payload['goal_title'],
        'goal_type' => $payload['goal_type'],
        'target_value' => $payload['target_value'],
        'current_value' => $payload['current_value'],
        'status' => $payload['status'],
    ]);
}

function delete_yearly_goal_record(int $goalId, int $userId): void
{
    $statement = db()->prepare('DELETE FROM yearly_goals WHERE id = :id AND user_id = :user_id');
    $statement->execute([
        'id' => $goalId,
        'user_id' => $userId,
    ]);
}

function fetch_planner_events_for_user(int $userId, int $limit = 50): array
{
    $statement = db()->prepare(
        'SELECT *
        FROM planner_events
        WHERE user_id = :user_id
        ORDER BY event_date ASC, id ASC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function fetch_planner_events_between(int $userId, string $startDateTime, string $endDateTime): array
{
    $statement = db()->prepare(
        'SELECT *
        FROM planner_events
        WHERE user_id = :user_id
            AND event_date >= :start_date
            AND event_date < :end_date
        ORDER BY event_date ASC, id ASC'
    );
    $statement->execute([
        'user_id' => $userId,
        'start_date' => $startDateTime,
        'end_date' => $endDateTime,
    ]);

    return $statement->fetchAll();
}

function fetch_planner_event_by_id(int $eventId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT *
        FROM planner_events
        WHERE id = :id
            AND user_id = :user_id
        LIMIT 1'
    );
    $statement->execute([
        'id' => $eventId,
        'user_id' => $userId,
    ]);

    $event = $statement->fetch();

    return $event ?: null;
}

function create_planner_event_record(int $userId, array $payload): int
{
    $statement = db()->prepare(
        'INSERT INTO planner_events (user_id, title, description, event_date, event_type, related_community_event_id)
        VALUES (:user_id, :title, :description, :event_date, :event_type, :related_community_event_id)'
    );
    $statement->execute([
        'user_id' => $userId,
        'title' => $payload['title'],
        'description' => $payload['description'],
        'event_date' => $payload['event_date'],
        'event_type' => $payload['event_type'],
        'related_community_event_id' => $payload['related_community_event_id'],
    ]);

    return (int) db()->lastInsertId();
}

function update_planner_event_record(int $eventId, int $userId, array $payload): void
{
    $statement = db()->prepare(
        'UPDATE planner_events
        SET title = :title,
            description = :description,
            event_date = :event_date,
            event_type = :event_type,
            related_community_event_id = :related_community_event_id
        WHERE id = :id
            AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $eventId,
        'user_id' => $userId,
        'title' => $payload['title'],
        'description' => $payload['description'],
        'event_date' => $payload['event_date'],
        'event_type' => $payload['event_type'],
        'related_community_event_id' => $payload['related_community_event_id'],
    ]);
}

function delete_planner_event_record(int $eventId, int $userId): void
{
    $statement = db()->prepare('DELETE FROM planner_events WHERE id = :id AND user_id = :user_id');
    $statement->execute([
        'id' => $eventId,
        'user_id' => $userId,
    ]);
}

function fetch_planner_schedule(int $userId, int $limit = 8): array
{
    $statement = db()->prepare(
        'SELECT
            id,
            title,
            description,
            event_date,
            event_type,
            related_community_event_id,
            created_at,
            updated_at,
            :source AS source
        FROM planner_events
        WHERE user_id = :user_id
            AND event_date >= NOW() - INTERVAL 1 DAY
        ORDER BY event_date ASC, id ASC
        LIMIT ' . (int) $limit
    );
    $statement->execute([
        'source' => 'personal',
        'user_id' => $userId,
    ]);

    return $statement->fetchAll();
}

function fetch_prayer_entries_for_user(int $userId, int $limit = 8): array
{
    $statement = db()->prepare(
        'SELECT *
        FROM prayer_entries
        WHERE user_id = :user_id
        ORDER BY
            CASE WHEN status = "active" THEN 0 ELSE 1 END,
            updated_at DESC,
            id DESC
        LIMIT ' . (int) $limit
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function create_prayer_entry_record(int $userId, string $title, ?string $details = null, string $status = 'active'): int
{
    $statement = db()->prepare(
        'INSERT INTO prayer_entries (user_id, title, details, status)
        VALUES (:user_id, :title, :details, :status)'
    );
    $statement->execute([
        'user_id' => $userId,
        'title' => trim($title),
        'details' => normalize_optional_text($details),
        'status' => $status,
    ]);

    return (int) db()->lastInsertId();
}

function update_prayer_entry_status(int $entryId, int $userId, string $status): void
{
    $statement = db()->prepare(
        'UPDATE prayer_entries
        SET status = :status
        WHERE id = :id
            AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $entryId,
        'user_id' => $userId,
        'status' => $status,
    ]);
}

function delete_prayer_entry_record(int $entryId, int $userId): void
{
    $statement = db()->prepare('DELETE FROM prayer_entries WHERE id = :id AND user_id = :user_id');
    $statement->execute([
        'id' => $entryId,
        'user_id' => $userId,
    ]);
}

function summarize_yearly_goals(array $goals): array
{
    $summary = [
        'total' => count($goals),
        'active' => 0,
        'completed' => 0,
        'progress_percent' => 0,
    ];

    $percentages = [];

    foreach ($goals as $goal) {
        $status = (string) ($goal['status'] ?? 'active');

        if ($status === 'active') {
            $summary['active']++;
        }

        if ($status === 'completed') {
            $summary['completed']++;
        }

        $percent = calculate_goal_progress_percent($goal);

        if ($percent !== null) {
            $percentages[] = $percent;
        }
    }

    if ($percentages !== []) {
        $summary['progress_percent'] = (int) round(array_sum($percentages) / count($percentages));
    }

    return $summary;
}

function calculate_goal_progress_percent(array $goal): ?int
{
    $targetValue = isset($goal['target_value']) ? (int) $goal['target_value'] : 0;
    $currentValue = isset($goal['current_value']) ? (int) $goal['current_value'] : 0;

    if ($targetValue <= 0) {
        return null;
    }

    return max(0, min(100, (int) round(($currentValue / $targetValue) * 100)));
}
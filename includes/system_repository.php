<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function friend_invites_use_hashed_tokens(): bool
{
    static $usesHashedTokens = null;

    if ($usesHashedTokens !== null) {
        return $usesHashedTokens;
    }

    try {
        $statement = db()->query("SHOW COLUMNS FROM friend_invites LIKE 'invite_token_hash'");
        $usesHashedTokens = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $usesHashedTokens = false;
    }

    return $usesHashedTokens;
}

function audit_logs_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE 'audit_logs'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function user_sessions_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE 'user_sessions'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function public_sessions_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE 'public_sessions'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function public_radio_stations_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE 'public_radio_stations'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function public_radio_playlist_support_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    if (!public_radio_stations_available()) {
        $available = false;

        return $available;
    }

    try {
        $statement = db()->query("SHOW COLUMNS FROM public_radio_stations LIKE 'youtube_playlist_id'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function public_radio_live_support_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    if (!public_radio_stations_available()) {
        $available = false;

        return $available;
    }

    try {
        $statement = db()->query("SHOW COLUMNS FROM public_radio_stations LIKE 'is_live'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function record_audit_event(?int $actorUserId, string $eventType, ?int $targetUserId = null, array $context = []): void
{
    if (!audit_logs_available()) {
        return;
    }

    $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES);

    if (!is_string($contextJson)) {
        $contextJson = '{}';
    }

    try {
        $statement = db()->prepare(
            'INSERT INTO audit_logs (
                actor_user_id,
                target_user_id,
                event_type,
                ip_address,
                user_agent,
                context_json
            ) VALUES (
                :actor_user_id,
                :target_user_id,
                :event_type,
                :ip_address,
                :user_agent,
                :context_json
            )'
        );
        $statement->execute([
            'actor_user_id' => $actorUserId,
            'context_json' => $contextJson,
            'event_type' => trim($eventType),
            'ip_address' => current_request_ip_address(),
            'target_user_id' => $targetUserId,
            'user_agent' => current_request_user_agent(),
        ]);
    } catch (Throwable $exception) {
        return;
    }
}

function current_request_ip_address(): ?string
{
    $ipAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    return $ipAddress !== '' ? $ipAddress : null;
}

function current_request_user_agent(): ?string
{
    $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    return $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null;
}

function count_records(string $sql, array $params = []): int
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

function normalize_optional_text(string $value): ?string
{
    $trimmed = trim($value);

    return $trimmed === '' ? null : $trimmed;
}

function normalize_book_key(string $value): string
{
    return preg_replace('/[^a-z0-9]/i', '', mb_strtolower(trim($value))) ?? '';
}
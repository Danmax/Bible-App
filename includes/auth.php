<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/repository.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $isSecure = ($https !== '' && $https !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

send_security_headers();

function set_flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type,
    ];
}

function pull_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !is_string($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(422);
        exit('Invalid form token.');
    }
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(self), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "img-src 'self' https: data:; " .
        "media-src 'self' https: blob:; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com data:; " .
        "script-src 'self'; " .
        "connect-src 'self' https://api.nlt.to https://playerservices.streamtheworld.com; " .
        "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com; " .
        "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'"
    );

    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));

    if ($https !== '' && $https !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function rate_limit_key(string $action, ?string $identity = null): string
{
    $parts = [
        trim($action),
        trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
    ];

    if ($identity !== null && $identity !== '') {
        $parts[] = trim($identity);
    }

    return implode('|', $parts);
}

function enforce_rate_limit(string $key, int $maxAttempts, int $windowSeconds): void
{
    $now = time();

    try {
        $pdo = db();
        $statement = $pdo->prepare('SELECT attempts, window_started_at FROM rate_limits WHERE action_key = :key LIMIT 1');
        $statement->execute(['key' => $key]);
        $row = $statement->fetch();

        if ($row !== false) {
            $windowStartedAt = (int) $row['window_started_at'];
            $attempts = (int) $row['attempts'];

            if (($now - $windowStartedAt) >= $windowSeconds) {
                $updateStmt = $pdo->prepare('UPDATE rate_limits SET attempts = 1, window_started_at = :now WHERE action_key = :key');
                $updateStmt->execute(['now' => $now, 'key' => $key]);
            } else {
                if ($attempts >= $maxAttempts) {
                    $retryAfter = max(1, $windowSeconds - ($now - $windowStartedAt));
                    throw new RuntimeException('Too many attempts. Wait ' . $retryAfter . ' seconds and try again.');
                }

                $updateStmt = $pdo->prepare('UPDATE rate_limits SET attempts = attempts + 1 WHERE action_key = :key');
                $updateStmt->execute(['key' => $key]);
            }
        } else {
            $insertStmt = $pdo->prepare('INSERT IGNORE INTO rate_limits (action_key, attempts, window_started_at) VALUES (:key, 1, :now)');
            $insertStmt->execute(['key' => $key, 'now' => $now]);
        }
        return;
    } catch (Throwable $exception) {
        if ($exception instanceof RuntimeException && str_starts_with($exception->getMessage(), 'Too many attempts.')) {
            throw $exception;
        }
    }

    $state = $_SESSION['rate_limits'][$key] ?? [
        'count' => 0,
        'window_started_at' => $now,
    ];

    if (($now - (int) $state['window_started_at']) >= $windowSeconds) {
        $state = [
            'count' => 0,
            'window_started_at' => $now,
        ];
    }

    if ((int) $state['count'] >= $maxAttempts) {
        $retryAfter = max(1, $windowSeconds - ($now - (int) $state['window_started_at']));
        throw new RuntimeException('Too many attempts. Wait ' . $retryAfter . ' seconds and try again.');
    }

    $state['count'] = (int) $state['count'] + 1;
    $_SESSION['rate_limits'][$key] = $state;
}

function clear_rate_limit(string $key): void
{
    try {
        db()->prepare('DELETE FROM rate_limits WHERE action_key = :key')->execute(['key' => $key]);
    } catch (Throwable $exception) {
        // Ignore database errors if the table hasn't been created yet.
    }

    unset($_SESSION['rate_limits'][$key]);
}

function log_in_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) ($user['role'] ?? 'member'),
        'city' => (string) ($user['city'] ?? ''),
        'avatar_url' => (string) ($user['avatar_url'] ?? ''),
    ];

    register_authenticated_session((int) $user['id']);
}

function logout_user(): void
{
    revoke_authenticated_session();
    unset($_SESSION['user']);
    unset($_SESSION['auth_session_token']);
    session_regenerate_id(true);
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_user_has_role(array $roles): bool
{
    $user = current_user();

    if ($user === null) {
        return false;
    }

    return in_array((string) ($user['role'] ?? 'member'), $roles, true);
}

function can_manage_community_event(?array $event, ?array $user = null): bool
{
    if ($event === null) {
        return false;
    }

    $activeUser = $user ?? current_user();

    if ($activeUser === null) {
        return false;
    }

    if (in_array((string) ($activeUser['role'] ?? 'member'), ['admin', 'leader'], true)) {
        return true;
    }

    return (int) ($event['created_by_user_id'] ?? 0) === (int) ($activeUser['id'] ?? 0);
}

function refresh_current_user(): ?array
{
    $user = current_user();

    if ($user === null) {
        return null;
    }

    try {
        $freshUser = fetch_user_by_id((int) $user['id']);
    } catch (Throwable $exception) {
        return $user;
    }

    if ($freshUser === null) {
        logout_user();

        return null;
    }

    $_SESSION['user'] = [
        'id' => (int) $freshUser['id'],
        'name' => (string) $freshUser['name'],
        'email' => (string) $freshUser['email'],
        'role' => (string) ($freshUser['role'] ?? 'member'),
        'city' => (string) ($freshUser['city'] ?? ''),
        'avatar_url' => (string) ($freshUser['avatar_url'] ?? ''),
    ];

    return current_user();
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('Sign in first to access your study dashboard.', 'warning');
        redirect('login.php');
    }
}

function redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function session_idle_timeout_seconds(): int
{
    return max(300, (int) APP_SESSION_IDLE_TIMEOUT);
}

function session_absolute_timeout_seconds(): int
{
    return max(session_idle_timeout_seconds(), (int) APP_SESSION_ABSOLUTE_TIMEOUT);
}

function register_authenticated_session(int $userId): void
{
    if (!user_sessions_available()) {
        return;
    }

    $sessionToken = bin2hex(random_bytes(32));
    $lastSeenAt = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', time() + session_idle_timeout_seconds());

    upsert_user_session_record($userId, session_id(), $sessionToken, $lastSeenAt, $expiresAt);
    $_SESSION['auth_session_token'] = $sessionToken;

    record_audit_event($userId, 'session.started', $userId, [
        'session_id_hash' => hash('sha256', session_id()),
    ]);
}

function revoke_authenticated_session(): void
{
    if (!user_sessions_available()) {
        return;
    }

    $userId = (int) ($_SESSION['user']['id'] ?? 0);

    try {
        revoke_user_session_record(session_id());

        if ($userId > 0) {
            record_audit_event($userId, 'session.revoked', $userId, [
                'session_id_hash' => hash('sha256', session_id()),
            ]);
        }
    } catch (Throwable $exception) {
        return;
    }
}

function ensure_authenticated_session_is_valid(): void
{
    if (!isset($_SESSION['user']) || !user_sessions_available()) {
        return;
    }

    $userId = (int) ($_SESSION['user']['id'] ?? 0);

    if ($userId <= 0) {
        return;
    }

    $sessionToken = trim((string) ($_SESSION['auth_session_token'] ?? ''));

    if ($sessionToken === '') {
        register_authenticated_session($userId);

        return;
    }

    $minimumCreatedAt = date('Y-m-d H:i:s', time() - session_absolute_timeout_seconds());

    try {
        $sessionRecord = fetch_active_user_session_record($userId, session_id(), $sessionToken, $minimumCreatedAt);
    } catch (Throwable $exception) {
        return;
    }

    if ($sessionRecord === null) {
        unset($_SESSION['user']);
        unset($_SESSION['auth_session_token']);
        session_regenerate_id(true);
        set_flash('Your session expired. Sign in again to continue.', 'warning');

        return;
    }

    $lastSeenAt = strtotime((string) ($sessionRecord['last_seen_at'] ?? ''));

    if ($lastSeenAt !== false && (time() - $lastSeenAt) < 300) {
        return;
    }

    try {
        touch_user_session_record(
            (int) $sessionRecord['id'],
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s', time() + session_idle_timeout_seconds())
        );
    } catch (Throwable $exception) {
        return;
    }
}

ensure_authenticated_session_is_valid();

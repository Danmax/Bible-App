<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/repository.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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

function log_in_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) ($user['role'] ?? 'member'),
    ];
}

function logout_user(): void
{
    unset($_SESSION['user']);
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

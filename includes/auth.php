<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

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

function login_demo_user(string $name, string $email): void
{
    $_SESSION['user'] = [
        'id' => 1,
        'name' => $name,
        'email' => $email,
        'role' => 'member',
    ];
}

function logout_user(): void
{
    unset($_SESSION['user']);
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
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

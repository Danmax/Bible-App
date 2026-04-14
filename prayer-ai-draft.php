<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/openai.php';

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    restore_error_handler();
    exit;
}

verify_csrf();

$user = refresh_current_user();

if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Sign in first to draft prayer requests with AI.']);
    restore_error_handler();
    exit;
}

$prompt = trim((string) ($_POST['prompt'] ?? ''));

if ($prompt === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Enter a prompt first.']);
    restore_error_handler();
    exit;
}

try {
    $draft = openai_generate_prayer_request_draft($prompt);
    $normalized = [
        'title' => trim((string) ($draft['title'] ?? '')),
        'details' => trim((string) ($draft['details'] ?? '')),
        'status' => trim((string) ($draft['status'] ?? 'active')),
    ];

    if (!in_array($normalized['status'], ['active', 'answered'], true)) {
        $normalized['status'] = 'active';
    }

    echo json_encode([
        'draft' => $normalized,
        'model' => openai_event_model(),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()]);
}

restore_error_handler();

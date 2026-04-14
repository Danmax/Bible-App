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
    echo json_encode(['error' => 'Sign in first to draft planner events with AI.']);
    restore_error_handler();
    exit;
}

$prompt = trim((string) ($_POST['prompt'] ?? ''));
$eventDateHint = normalize_planner_ai_datetime($_POST['event_date'] ?? '');

if ($prompt === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Enter a prompt first.']);
    restore_error_handler();
    exit;
}

try {
    $draft = openai_generate_planner_event_draft($prompt, $eventDateHint);
    $allowedTypes = ['study', 'prayer', 'service', 'family', 'community', 'goal', 'reminder'];
    $normalized = [
        'title' => trim((string) ($draft['title'] ?? '')),
        'event_type' => trim((string) ($draft['event_type'] ?? 'study')),
        'event_date' => normalize_planner_ai_datetime($draft['event_date'] ?? ''),
        'description' => trim((string) ($draft['description'] ?? '')),
    ];

    if (!in_array($normalized['event_type'], $allowedTypes, true)) {
        $normalized['event_type'] = 'study';
    }

    if ($normalized['event_date'] === '' && $eventDateHint !== '') {
        $normalized['event_date'] = $eventDateHint;
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

function normalize_planner_ai_datetime(mixed $value): string
{
    $trimmed = trim((string) $value);

    if ($trimmed === '') {
        return '';
    }

    $timestamp = strtotime($trimmed);

    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $timestamp);
}

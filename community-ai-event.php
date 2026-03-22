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
    echo json_encode(['error' => 'Sign in first to draft events with AI.']);
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
    $categories = fetch_event_categories();
    $draft = openai_generate_event_draft($prompt, $categories);
    $categoryIds = array_map(
        static fn(array $category): string => (string) ($category['id'] ?? ''),
        $categories
    );

    $normalized = [
        'title' => trim((string) ($draft['title'] ?? '')),
        'category_id' => trim((string) ($draft['category_id'] ?? '')),
        'event_type' => trim((string) ($draft['event_type'] ?? '')),
        'visibility' => trim((string) ($draft['visibility'] ?? 'public')),
        'location_name' => trim((string) ($draft['location_name'] ?? '')),
        'location_address' => trim((string) ($draft['location_address'] ?? '')),
        'meeting_url' => trim((string) ($draft['meeting_url'] ?? '')),
        'start_at' => normalize_ai_event_datetime($draft['start_at'] ?? ''),
        'end_at' => normalize_ai_event_datetime($draft['end_at'] ?? ''),
        'description' => trim((string) ($draft['description'] ?? '')),
        'status' => trim((string) ($draft['status'] ?? 'published')),
        'is_featured' => !empty($draft['is_featured']) ? '1' : '0',
    ];

    if (!in_array($normalized['category_id'], $categoryIds, true)) {
        $normalized['category_id'] = '';
    }

    if (!in_array($normalized['visibility'], ['public', 'members', 'private'], true)) {
        $normalized['visibility'] = 'public';
    }

    if (!in_array($normalized['status'], ['published', 'draft', 'cancelled'], true)) {
        $normalized['status'] = 'published';
    }

    if ($normalized['meeting_url'] !== '' && filter_var($normalized['meeting_url'], FILTER_VALIDATE_URL) === false) {
        $normalized['meeting_url'] = '';
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

function normalize_ai_event_datetime(mixed $value): string
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

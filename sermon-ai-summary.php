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
    echo json_encode(['error' => 'Sign in first to summarize sermon notes.']);
    restore_error_handler();
    exit;
}

$speakerNotes = trim((string) ($_POST['speaker_notes_text'] ?? ''));
$noteText = trim((string) ($_POST['note_text'] ?? ''));

try {
    $draft = openai_generate_sermon_summary($speakerNotes, $noteText);
    $keyPoints = normalize_ai_string_list($draft['key_points'] ?? []);
    $applicationPoints = normalize_ai_string_list($draft['application_points'] ?? []);

    echo json_encode([
        'draft' => [
            'summary' => trim((string) ($draft['summary'] ?? '')),
            'key_points' => $keyPoints,
            'application_points' => $applicationPoints,
            'prayer_focus' => trim((string) ($draft['prayer_focus'] ?? '')),
            'title' => trim((string) ($draft['title'] ?? '')),
        ],
        'model' => openai_event_model(),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()]);
}

restore_error_handler();

function normalize_ai_string_list(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    $normalized = [];

    foreach ($value as $item) {
        $text = trim((string) $item);

        if ($text !== '') {
            $normalized[] = $text;
        }
    }

    return $normalized;
}

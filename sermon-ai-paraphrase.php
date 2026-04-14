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
    echo json_encode(['error' => 'Sign in first to paraphrase verses.']);
    restore_error_handler();
    exit;
}

$verseId = (int) ($_POST['verse_id'] ?? 0);
$context = trim((string) ($_POST['context'] ?? ''));
$verse = $verseId > 0 ? fetch_verse_by_id($verseId) : null;

if ($verse === null) {
    http_response_code(422);
    echo json_encode(['error' => 'Choose a verse first.']);
    restore_error_handler();
    exit;
}

try {
    $draft = openai_generate_sermon_paraphrase(
        format_verse_reference($verse),
        (string) ($verse['verse_text'] ?? ''),
        $context
    );

    echo json_encode([
        'draft' => [
            'paraphrase' => trim((string) ($draft['paraphrase'] ?? '')),
            'summary' => trim((string) ($draft['summary'] ?? '')),
            'verse_id' => (int) $verse['id'],
            'reference_label' => format_verse_reference($verse),
        ],
        'model' => openai_event_model(),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()]);
}

restore_error_handler();

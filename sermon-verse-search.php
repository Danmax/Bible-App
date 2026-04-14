<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    restore_error_handler();
    exit;
}

$user = refresh_current_user();

if ($user === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Sign in first to search verses.']);
    restore_error_handler();
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$translation = strtoupper(trim((string) ($_GET['translation'] ?? APP_DEFAULT_TRANSLATION)));

if ($query === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Enter a verse search first.']);
    restore_error_handler();
    exit;
}

if (!in_array($translation, fetch_available_translations(), true)) {
    $translation = APP_DEFAULT_TRANSLATION;
}

try {
    $results = search_scripture($query, $translation);
    $normalized = [];

    foreach (array_slice((array) ($results['results'] ?? []), 0, 12) as $verse) {
        if (!is_array($verse) || empty($verse['id'])) {
            continue;
        }

        $normalized[] = [
            'verse_id' => (int) $verse['id'],
            'reference_label' => format_verse_reference($verse),
            'verse_text' => trim((string) ($verse['verse_text'] ?? '')),
            'translation' => trim((string) ($verse['translation'] ?? $translation)),
        ];
    }

    echo json_encode([
        'results' => $normalized,
        'heading' => trim((string) ($results['heading'] ?? 'Search Results')),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()]);
}

restore_error_handler();

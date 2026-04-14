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
    echo json_encode(['error' => 'Sign in first to build sermon references.']);
    restore_error_handler();
    exit;
}

$noteText = trim((string) ($_POST['note_text'] ?? ''));
$translation = strtoupper(trim((string) ($_POST['translation'] ?? APP_DEFAULT_TRANSLATION)));

if (!in_array($translation, fetch_available_translations(), true)) {
    $translation = APP_DEFAULT_TRANSLATION;
}

try {
    $draft = openai_generate_sermon_reference_suggestions($noteText);
    $referenceTags = normalize_sermon_reference_tag_groups($draft['reference_tags'] ?? []);
    $verseSuggestions = resolve_sermon_reference_queries($draft['verse_queries'] ?? [], $translation);

    echo json_encode([
        'draft' => [
            'reference_tags' => $referenceTags,
            'verse_refs' => $verseSuggestions,
        ],
        'model' => openai_event_model(),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()]);
}

restore_error_handler();

function normalize_sermon_reference_tag_groups(mixed $value): array
{
    $normalized = [];

    foreach (sermon_note_reference_type_options() as $type => $label) {
        $items = is_array($value) && isset($value[$type]) && is_array($value[$type]) ? $value[$type] : [];

        foreach ($items as $item) {
            $text = trim((string) $item);

            if ($text !== '') {
                $normalized[] = [
                    'tag_type' => $type,
                    'label' => $text,
                ];
            }
        }
    }

    return $normalized;
}

function resolve_sermon_reference_queries(mixed $value, string $translation): array
{
    if (!is_array($value)) {
        return [];
    }

    $books = fetch_books();
    $normalized = [];

    foreach ($value as $query) {
        $queryText = trim((string) $query);

        if ($queryText === '') {
            continue;
        }

        $reference = parse_reference_query($queryText, $books);
        $results = $reference !== null
            ? fetch_reference_verses($reference, $translation)
            : search_scripture($queryText, $translation);
        $verse = $results['results'][0] ?? null;

        if (!is_array($verse) || empty($verse['id'])) {
            continue;
        }

        $normalized[] = [
            'verse_id' => (int) $verse['id'],
            'reference_kind' => 'mentioned',
            'reference_label' => format_verse_reference($verse),
            'quote_text' => trim((string) ($verse['verse_text'] ?? '')),
        ];
    }

    return normalize_sermon_note_verse_refs($normalized);
}

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
    echo json_encode(['error' => 'Sign in first to draft planner goals with AI.']);
    restore_error_handler();
    exit;
}

$prompt = trim((string) ($_POST['prompt'] ?? ''));
$yearHint = filter_var($_POST['year'] ?? null, FILTER_VALIDATE_INT);
$yearHint = $yearHint !== false ? $yearHint : null;

if ($prompt === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Enter a prompt first.']);
    restore_error_handler();
    exit;
}

try {
    $draft = openai_generate_planner_goal_draft($prompt, $yearHint);
    $allowedTypes = ['reading', 'attendance', 'devotion', 'prayer', 'service', 'custom'];
    $allowedStatuses = ['active', 'paused', 'completed'];
    $normalized = [
        'goal_title' => trim((string) ($draft['goal_title'] ?? '')),
        'goal_type' => trim((string) ($draft['goal_type'] ?? 'reading')),
        'year' => normalize_goal_year($draft['year'] ?? '', $yearHint),
        'target_value' => normalize_goal_number($draft['target_value'] ?? ''),
        'current_value' => normalize_goal_number($draft['current_value'] ?? '0', '0'),
        'status' => trim((string) ($draft['status'] ?? 'active')),
    ];

    if (!in_array($normalized['goal_type'], $allowedTypes, true)) {
        $normalized['goal_type'] = 'custom';
    }

    if (!in_array($normalized['status'], $allowedStatuses, true)) {
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

function normalize_goal_year(mixed $value, ?int $fallback): string
{
    $year = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 2000, 'max_range' => 2100],
    ]);

    if ($year === false || $year === null) {
        return $fallback !== null ? (string) $fallback : (string) date('Y');
    }

    return (string) $year;
}

function normalize_goal_number(mixed $value, string $fallback = ''): string
{
    $trimmed = trim((string) $value);

    if ($trimmed === '') {
        return $fallback;
    }

    $number = filter_var($trimmed, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0],
    ]);

    if ($number === false || $number === null) {
        return $fallback;
    }

    return (string) $number;
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/reader_marks.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['error' => 'Use POST to save a mark.']);
    exit;
}
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Sign in to save your highlights.']);
    exit;
}
verify_csrf();
try {
    $ids = $_POST['verse_ids'] ?? [];
    $action = $_POST['mark_action'] ?? '';
    $color = $_POST['color'] ?? '';
    if (!is_array($ids) || !is_string($action) || !is_string($color)) {
        throw new InvalidArgumentException('Select valid verses and a color.');
    }
    $saved = save_reader_marks((int) current_user()['id'], $ids, $action, $color);
    echo json_encode(['verse_ids' => $saved, 'action' => $action, 'color' => $color]);
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('Reader marks: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Your mark could not be saved. Please try again.']);
}

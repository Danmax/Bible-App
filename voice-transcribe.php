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
    echo json_encode(['error' => 'Sign in first to transcribe audio.']);
    restore_error_handler();
    exit;
}

enforce_ai_rate_limit('voice-transcription', (int) $user['id'], 6, 900);

if (!openai_event_drafts_enabled()) {
    http_response_code(503);
    echo json_encode(['error' => 'Voice transcription is not configured yet.']);
    restore_error_handler();
    exit;
}

$audioUpload = $_FILES['audio'] ?? null;

if (!is_array($audioUpload) || (int) ($audioUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['error' => 'Record audio first, then try again.']);
    restore_error_handler();
    exit;
}

$filePath = (string) ($audioUpload['tmp_name'] ?? '');
$fileName = (string) ($audioUpload['name'] ?? 'recording.webm');
$fileSize = (int) ($audioUpload['size'] ?? 0);

if ($fileSize <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'The audio recording was empty.']);
    restore_error_handler();
    exit;
}

if ($fileSize > 15 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Audio recordings must be smaller than 15 MB.']);
    restore_error_handler();
    exit;
}

$fileInfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = (string) $fileInfo->file($filePath);
$allowedMimeTypes = [
    'audio/mpeg',
    'audio/mp4',
    'audio/x-m4a',
    'audio/wav',
    'audio/x-wav',
    'audio/webm',
    'video/webm',
    'audio/ogg',
    'application/ogg',
];

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Upload a supported audio recording.']);
    restore_error_handler();
    exit;
}

try {
    $transcript = openai_transcribe_audio_upload($filePath, $fileName, $mimeType);

    echo json_encode([
        'text' => $transcript,
        'model' => 'gpt-4o-mini-transcribe',
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    report_ai_endpoint_error($exception);
}

restore_error_handler();

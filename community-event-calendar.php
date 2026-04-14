<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
$user = is_logged_in() ? refresh_current_user() : null;
$canManageAllEvents = current_user_has_role(['admin', 'leader']);
$event = null;

if ($eventId !== false && $eventId !== null) {
    $event = fetch_community_event_by_id($eventId, $user['id'] ?? null, $canManageAllEvents);
}

if ($event === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Event not found.';
    exit;
}

$startTimestamp = strtotime((string) $event['start_at']);
$endTimestamp = !empty($event['end_at'])
    ? strtotime((string) $event['end_at'])
    : strtotime('+1 hour', $startTimestamp ?: time());

if ($startTimestamp === false) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Event start time is invalid.';
    exit;
}

if ($endTimestamp === false || $endTimestamp <= $startTimestamp) {
    $endTimestamp = strtotime('+1 hour', $startTimestamp);
}

$calendarLines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Good News Bible//Community Events//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:' . calendar_escape_text('community-event-' . (string) $event['id'] . '@' . calendar_uid_host()),
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART:' . gmdate('Ymd\THis\Z', $startTimestamp),
    'DTEND:' . gmdate('Ymd\THis\Z', $endTimestamp),
    'SUMMARY:' . calendar_escape_text((string) $event['title']),
    'DESCRIPTION:' . calendar_escape_text(calendar_event_description($event)),
    'STATUS:' . calendar_event_status((string) ($event['status'] ?? 'published')),
    'URL:' . calendar_escape_text(calendar_event_url($event)),
];

$location = trim(implode(', ', array_filter([
    trim((string) ($event['location_name'] ?? '')),
    trim((string) ($event['location_address'] ?? '')),
])));

if ($location !== '') {
    $calendarLines[] = 'LOCATION:' . calendar_escape_text($location);
}

$calendarLines[] = 'END:VEVENT';
$calendarLines[] = 'END:VCALENDAR';

$filename = calendar_filename((string) $event['title']) . '.ics';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=300');

echo implode("\r\n", array_map('calendar_fold_line', $calendarLines)) . "\r\n";

function calendar_event_description(array $event): string
{
    $parts = [];

    $description = trim((string) ($event['description'] ?? ''));
    if ($description !== '') {
        $parts[] = $description;
    }

    $locationName = trim((string) ($event['location_name'] ?? ''));
    if ($locationName !== '') {
        $parts[] = 'Location: ' . $locationName;
    }

    $locationAddress = trim((string) ($event['location_address'] ?? ''));
    if ($locationAddress !== '') {
        $parts[] = 'Address: ' . $locationAddress;
    }

    $meetingUrl = trim((string) ($event['meeting_url'] ?? ''));
    if ($meetingUrl !== '') {
        $parts[] = 'Online: ' . $meetingUrl;
    }

    return implode("\n\n", $parts);
}

function calendar_event_url(array $event): string
{
    $meetingUrl = trim((string) ($event['meeting_url'] ?? ''));

    if ($meetingUrl !== '') {
        return $meetingUrl;
    }

    return app_url('community.php', true);
}

function calendar_event_status(string $status): string
{
    return $status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED';
}

function calendar_uid_host(): string
{
    $baseUrl = app_url('', true);
    $host = parse_url($baseUrl, PHP_URL_HOST);

    return is_string($host) && $host !== '' ? $host : 'localhost';
}

function calendar_escape_text(string $value): string
{
    return str_replace(
        ["\\", ";", ",", "\r\n", "\n", "\r"],
        ["\\\\", "\;", "\,", '\n', '\n', '\n'],
        $value
    );
}

function calendar_fold_line(string $line): string
{
    $result = '';

    while (strlen($line) > 75) {
        $result .= substr($line, 0, 75) . "\r\n ";
        $line = substr($line, 75);
    }

    return $result . $line;
}

function calendar_filename(string $title): string
{
    $normalized = strtolower(trim($title));
    $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized) ?? 'event';
    $normalized = trim($normalized, '-');

    return $normalized !== '' ? $normalized : 'event';
}

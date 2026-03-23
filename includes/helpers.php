<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function app_url(string $path = '', bool $absolute = false): string
{
    $trimmedPath = ltrim($path, '/');
    $relativePath = $trimmedPath === '' ? '/' : '/' . $trimmedPath;

    if (!$absolute) {
        return $relativePath;
    }

    $baseUrl = normalized_base_url(BASE_URL);

    if ($baseUrl === '' && is_local_environment()) {
        $baseUrl = current_request_base_url();
    }

    return $baseUrl === '' ? $relativePath : $baseUrl . $relativePath;
}

function app_environment(): string
{
    $configured = strtolower(trim((string) APP_ENV));

    if ($configured !== '') {
        return $configured;
    }

    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));

    if ($host === '' || str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
        return 'local';
    }

    return 'production';
}

function is_local_environment(): bool
{
    return in_array(app_environment(), ['local', 'development', 'dev', 'test'], true);
}

function debug_links_enabled(): bool
{
    $configured = strtolower(trim((string) (getenv('APP_DEBUG_LINKS') ?: '')));

    if ($configured !== '') {
        return in_array($configured, ['1', 'true', 'yes', 'on'], true);
    }

    return is_local_environment();
}

function app_primary_email(): string
{
    return trim((string) APP_PRIMARY_EMAIL);
}

function app_support_email(): string
{
    return trim((string) APP_SUPPORT_EMAIL);
}

function app_info_email(): string
{
    return trim((string) APP_INFO_EMAIL);
}

function app_mail_from_email(): string
{
    return trim((string) APP_MAIL_FROM_EMAIL);
}

function app_mail_from_name(): string
{
    return trim((string) APP_MAIL_FROM_NAME);
}

function normalized_base_url(?string $value): string
{
    $trimmedValue = trim((string) $value);

    if ($trimmedValue === '') {
        return '';
    }

    if (!preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $trimmedValue)) {
        $trimmedValue = 'https://' . ltrim($trimmedValue, '/');
    }

    $parts = parse_url($trimmedValue);

    if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
        return '';
    }

    $baseUrl = strtolower((string) $parts['scheme']) . '://' . $parts['host'];

    if (isset($parts['port'])) {
        $baseUrl .= ':' . (int) $parts['port'];
    }

    $path = trim((string) ($parts['path'] ?? ''), '/');

    if ($path !== '') {
        $baseUrl .= '/' . $path;
    }

    return rtrim($baseUrl, '/');
}

function current_request_base_url(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if ($host === '') {
        return '';
    }

    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';

    return $scheme . '://' . $host;
}

function asset_url(string $path): string
{
    $relativePath = ltrim($path, '/');
    $url = app_url($relativePath);
    $filePath = dirname(__DIR__) . '/' . $relativePath;

    if (!is_file($filePath)) {
        return $url;
    }

    $version = filemtime($filePath);

    if ($version === false) {
        return $url;
    }

    return $url . '?v=' . $version;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function page_title(?string $title): string
{
    return $title ? $title . ' | ' . APP_NAME : APP_NAME;
}

function current_year(): string
{
    return date('Y');
}

function profile_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';

    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : 'WT';
}

function highlight_class(?string $color): string
{
    $allowed = [
        'neon-yellow',
        'neon-green',
        'neon-pink',
        'neon-blue',
        'neon-orange',
    ];

    return in_array($color, $allowed, true) ? $color : 'neon-yellow';
}

function render_verse_text_with_highlights(string $verseText, array $highlights = []): string
{
    if ($highlights === []) {
        return e($verseText);
    }

    $segments = [];

    foreach ($highlights as $highlight) {
        $start = isset($highlight['selection_start']) ? (int) $highlight['selection_start'] : null;
        $end = isset($highlight['selection_end']) ? (int) $highlight['selection_end'] : null;
        $selectedText = trim((string) ($highlight['selected_text'] ?? ''));

        if ($start === null || $end === null || $end <= $start) {
            if ($selectedText === '') {
                continue;
            }

            $matchPosition = mb_stripos($verseText, $selectedText);

            if ($matchPosition === false) {
                continue;
            }

            $start = $matchPosition;
            $end = $matchPosition + mb_strlen($selectedText);
        }

        if ($start < 0 || $end > mb_strlen($verseText) || $end <= $start) {
            continue;
        }

        $segments[] = [
            'start' => $start,
            'end' => $end,
            'class' => highlight_class((string) ($highlight['highlight_color'] ?? 'neon-yellow')),
        ];
    }

    if ($segments === []) {
        return e($verseText);
    }

    usort(
        $segments,
        static fn(array $left, array $right): int => $left['start'] <=> $right['start']
    );

    $output = '';
    $cursor = 0;

    foreach ($segments as $segment) {
        if ($segment['start'] < $cursor) {
            continue;
        }

        $output .= e(mb_substr($verseText, $cursor, $segment['start'] - $cursor));
        $output .= '<mark class="verse-highlight ' . e($segment['class']) . '">'
            . e(mb_substr($verseText, $segment['start'], $segment['end'] - $segment['start']))
            . '</mark>';
        $cursor = $segment['end'];
    }

    $output .= e(mb_substr($verseText, $cursor));

    return $output;
}

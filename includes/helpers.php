<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function app_url(string $path = ''): string
{
    $trimmedBase = rtrim(BASE_URL, '/');
    $trimmedPath = ltrim($path, '/');

    if ($trimmedBase === '') {
        return $trimmedPath === '' ? '/' : '/' . $trimmedPath;
    }

    return $trimmedPath === '' ? $trimmedBase : $trimmedBase . '/' . $trimmedPath;
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

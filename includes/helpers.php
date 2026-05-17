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

function scripture_reference_parts(string $value): array
{
    $parts = preg_split('/[,;\n]+/', $value) ?: [];
    $references = [];

    foreach ($parts as $part) {
        $reference = trim((string) $part);

        if ($reference !== '') {
            $references[] = $reference;
        }
    }

    return array_values(array_unique($references));
}

function scripture_reference_query(string $reference): string
{
    return str_replace(["\u{2013}", "\u{2014}"], '-', trim($reference));
}

function scripture_reference_reader_url(string $reference): string
{
    return app_url('bible.php?q=' . urlencode(scripture_reference_query($reference)));
}

function scripture_analysis_stop_words(): array
{
    return array_fill_keys([
        'the', 'and', 'for', 'that', 'with', 'from', 'into', 'your', 'you', 'are', 'was', 'were',
        'have', 'has', 'had', 'not', 'but', 'all', 'any', 'can', 'his', 'her', 'him', 'our', 'out',
        'who', 'what', 'when', 'where', 'why', 'how', 'let', 'there', 'their', 'them', 'then', 'than',
        'this', 'these', 'those', 'will', 'shall', 'would', 'could', 'should', 'about', 'over', 'under',
        'through', 'after', 'before', 'because', 'been', 'being', 'also', 'unto', 'upon', 'they', 'she',
        'himself', 'herself', 'themselves', 'ourselves', 'which', 'whom', 'whose', 'said', 'says', 'say',
        'did', 'does', 'doing', 'very', 'more', 'most', 'much', 'many', 'each', 'every', 'some', 'such',
        'just', 'like', 'make', 'made', 'its', 'itself', 'again', 'still', 'here', 'only', 'thou', 'thee',
        'thy', 'thine', 'hast', 'hath', 'dost', 'doth', 'ye', 'wherefore', 'wherein', 'wherewith',
        'whence', 'hence', 'behold', 'saying',
    ], true);
}

function scripture_analysis_tokens(string $text): array
{
    $matched = preg_match_all("/[\p{L}][\p{L}'-]*/u", $text, $matches);

    if ($matched === false) {
        return [];
    }

    $tokens = [];

    foreach ($matches[0] ?? [] as $token) {
        $normalized = trim(mb_strtolower((string) $token), "'- ");

        if ($normalized !== '') {
            $tokens[] = $normalized;
        }
    }

    return $tokens;
}

function scripture_focus_terms(string $text, int $limit = 3, int $minimumLength = 4): array
{
    $stopWords = scripture_analysis_stop_words();
    $counts = [];

    foreach (scripture_analysis_tokens($text) as $token) {
        if (mb_strlen($token) < $minimumLength || isset($stopWords[$token])) {
            continue;
        }

        $counts[$token] = ($counts[$token] ?? 0) + 1;
    }

    arsort($counts);

    return array_slice(array_keys($counts), 0, $limit);
}

function page_title(?string $title): string
{
    return $title ? $title . ' | ' . APP_NAME : APP_NAME;
}

function current_year(): string
{
    return date('Y');
}

function app_theme_options(): array
{
    return [
        [
            'value' => 'good-news',
            'label' => 'Good News',
            'meta_color' => '#22333b',
        ],
        [
            'value' => 'spring',
            'label' => 'Spring',
            'meta_color' => '#5f8f52',
        ],
        [
            'value' => 'summer',
            'label' => 'Summer',
            'meta_color' => '#1d6fa3',
        ],
        [
            'value' => 'fall',
            'label' => 'Fall',
            'meta_color' => '#8c4b22',
        ],
        [
            'value' => 'winter',
            'label' => 'Winter',
            'meta_color' => '#496c88',
        ],
        [
            'value' => 'wood-cabin',
            'label' => 'Wood Cabin',
            'meta_color' => '#6b4423',
        ],
        [
            'value' => 'swordsman',
            'label' => 'Swordsman',
            'meta_color' => '#4d6275',
        ],
        [
            'value' => 'dark-mode',
            'label' => 'Dark Mode',
            'meta_color' => '#15181d',
        ],
    ];
}

function normalize_app_theme(?string $theme): string
{
    $normalized = strtolower(trim((string) $theme));
    $allowedThemes = array_column(app_theme_options(), 'value');

    return in_array($normalized, $allowedThemes, true) ? $normalized : 'good-news';
}

function app_theme_meta_color(string $theme): string
{
    $normalizedTheme = normalize_app_theme($theme);

    foreach (app_theme_options() as $option) {
        if (($option['value'] ?? '') === $normalizedTheme) {
            return (string) ($option['meta_color'] ?? '#22333b');
        }
    }

    return '#22333b';
}

function app_cache_path(string $key): string
{
    $safeKey = preg_replace('/[^a-z0-9_.-]/i', '_', $key) ?: hash('sha256', $key);
    $cacheDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'good-news-bible-cache';

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }

    return $cacheDir . DIRECTORY_SEPARATOR . $safeKey . '.cache.php';
}

function app_cache_get(string $key, int $ttlSeconds): mixed
{
    if ($ttlSeconds <= 0) {
        return null;
    }

    $path = app_cache_path($key);

    if (!is_file($path)) {
        return null;
    }

    $payload = @file_get_contents($path);

    if ($payload === false || $payload === '') {
        return null;
    }

    $cached = @unserialize($payload, ['allowed_classes' => false]);

    if (!is_array($cached) || !array_key_exists('created_at', $cached) || !array_key_exists('value', $cached)) {
        return null;
    }

    if ((time() - (int) $cached['created_at']) > $ttlSeconds) {
        @unlink($path);

        return null;
    }

    return $cached['value'];
}

function app_cache_set(string $key, mixed $value): void
{
    $path = app_cache_path($key);
    $payload = serialize([
        'created_at' => time(),
        'value' => $value,
    ]);

    @file_put_contents($path, $payload, LOCK_EX);
}

function app_cache_remember(string $key, int $ttlSeconds, callable $resolver): mixed
{
    $cached = app_cache_get($key, $ttlSeconds);

    if ($cached !== null) {
        return $cached;
    }

    $value = $resolver();
    app_cache_set($key, $value);

    return $value;
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
    $fullVerseClass = null;

    foreach ($highlights as $highlight) {
        $start = isset($highlight['selection_start']) ? (int) $highlight['selection_start'] : null;
        $end = isset($highlight['selection_end']) ? (int) $highlight['selection_end'] : null;
        $selectedText = trim((string) ($highlight['selected_text'] ?? ''));
        $highlightClass = highlight_class((string) ($highlight['highlight_color'] ?? 'neon-yellow'));

        if ($selectedText === '' && $start === null && $end === null) {
            if (!empty($highlight['highlight_color'])) {
                $fullVerseClass = $highlightClass;
            }

            continue;
        }

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
            'class' => $highlightClass,
        ];
    }

    if ($segments === []) {
        if ($fullVerseClass !== null) {
            return '<mark class="verse-highlight ' . e($fullVerseClass) . '">' . e($verseText) . '</mark>';
        }

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

    if ($fullVerseClass !== null) {
        return '<span class="verse-highlight ' . e($fullVerseClass) . '">' . $output . '</span>';
    }

    return $output;
}

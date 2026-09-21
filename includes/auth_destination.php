<?php

declare(strict_types=1);

/** Only app pages that display content may be authentication destinations. */
function auth_destination(mixed $value): string
{
    if (!is_string($value) || preg_match('/[\x00-\x20\\\\]/', $value)) {
        return 'bible.php';
    }

    $parts = parse_url($value);
    $allowedPages = ['index.php', 'bible.php', 'tools.php', 'dictionary.php', 'good-news.php', 'library.php', 'studies.php', 'study.php', 'study-day.php', 'planner.php', 'profile.php', 'community.php', 'friends.php', 'sessions.php', 'sermon-notes.php', 'dashboard.php'];
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || !in_array($parts['path'] ?? '', $allowedPages, true)) {
        return 'bible.php';
    }

    return $value;
}

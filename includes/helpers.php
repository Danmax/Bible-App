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

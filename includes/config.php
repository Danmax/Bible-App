<?php

declare(strict_types=1);

load_env_file(dirname(__DIR__) . '/.env');
load_env_file(dirname(__DIR__) . '/.env.local');

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Good News Bible');
}

if (!defined('BASE_URL')) {
    define('BASE_URL', getenv('APP_BASE_URL') ?: '');
}

if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV') ?: '');
}

if (!defined('APP_PRIMARY_EMAIL')) {
    define('APP_PRIMARY_EMAIL', getenv('APP_PRIMARY_EMAIL') ?: 'goodnews@frowear.com');
}

if (!defined('APP_SUPPORT_EMAIL')) {
    define('APP_SUPPORT_EMAIL', getenv('APP_SUPPORT_EMAIL') ?: APP_PRIMARY_EMAIL);
}

if (!defined('APP_INFO_EMAIL')) {
    define('APP_INFO_EMAIL', getenv('APP_INFO_EMAIL') ?: 'goodnews@frowear.com');
}

if (!defined('APP_MAIL_FROM_EMAIL')) {
    define('APP_MAIL_FROM_EMAIL', getenv('APP_MAIL_FROM_EMAIL') ?: APP_PRIMARY_EMAIL);
}

if (!defined('APP_MAIL_FROM_NAME')) {
    define('APP_MAIL_FROM_NAME', getenv('APP_MAIL_FROM_NAME') ?: APP_NAME);
}

if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
}

if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', getenv('SMTP_PORT') ?: '587');
}

if (!defined('SMTP_USER')) {
    define('SMTP_USER', getenv('SMTP_USER') ?: '');
}

if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
}

if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
}

if (!defined('SMTP_TIMEOUT')) {
    define('SMTP_TIMEOUT', getenv('SMTP_TIMEOUT') ?: '15');
}

if (!defined('APP_DEFAULT_TRANSLATION')) {
    define('APP_DEFAULT_TRANSLATION', getenv('APP_DEFAULT_TRANSLATION') ?: 'MSB');
}

if (!defined('APP_SESSION_IDLE_TIMEOUT')) {
    define('APP_SESSION_IDLE_TIMEOUT', getenv('APP_SESSION_IDLE_TIMEOUT') ?: '7200');
}

if (!defined('APP_SESSION_ABSOLUTE_TIMEOUT')) {
    define('APP_SESSION_ABSOLUTE_TIMEOUT', getenv('APP_SESSION_ABSOLUTE_TIMEOUT') ?: '604800');
}

if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
}

if (!defined('OPENAI_EVENT_MODEL')) {
    define('OPENAI_EVENT_MODEL', getenv('OPENAI_EVENT_MODEL') ?: 'gpt-5-mini');
}

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'hostinger_database_host');
}

if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'hostinger_database_name');
}

if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'hostinger_database_user');
}

if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: 'hostinger_database_password');
}

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmedLine = trim($line);

        if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
            continue;
        }

        $separatorPosition = strpos($trimmedLine, '=');

        if ($separatorPosition === false) {
            continue;
        }

        $name = trim(substr($trimmedLine, 0, $separatorPosition));
        $value = trim(substr($trimmedLine, $separatorPosition + 1));

        if ($name === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, '\'') && str_ends_with($value, '\''))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) !== false) {
            continue;
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

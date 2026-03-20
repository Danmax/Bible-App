<?php

declare(strict_types=1);

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Word Trail Bible App');
}

if (!defined('BASE_URL')) {
    define('BASE_URL', getenv('APP_BASE_URL') ?: '');
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

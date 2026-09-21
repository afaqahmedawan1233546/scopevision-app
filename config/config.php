<?php
/**
 * Application configuration.
 *
 * Database credentials can be overridden with environment variables so the same
 * code runs locally and on the server without editing this file.
 */

declare(strict_types=1);

return [
    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => (int) (getenv('DB_PORT') ?: 3306),
        'name'     => getenv('DB_NAME') ?: 'scopevision_shop',
        'user'     => getenv('DB_USER') ?: 'shop',
        'password' => getenv('DB_PASSWORD') ?: 'shop',
        'charset'  => 'utf8mb4',
    ],
    'admin' => [
        // Simple gate for the import page. Override with ADMIN_PASSWORD.
        'password' => getenv('ADMIN_PASSWORD') ?: 'admin123',
    ],
    'shop' => [
        'per_page' => 12,
    ],
    'uploads' => [
        'dir'            => dirname(__DIR__) . '/uploads',
        'max_bytes'      => 20 * 1024 * 1024,
        'allowed_ext'    => ['xlsx', 'xls'],
    ],
];

<?php

/**
 * Applies database/schema.sql to the configured database. Usage: php database/migrate.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Shop\Config;

$db = Config::get('db');
$name = (string) $db['name'];

if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
    fwrite(STDERR, "Invalid database name '$name'. Use letters, digits and underscores only.\n");
    exit(1);
}

$dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']);
$pdo = new PDO($dsn, $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");
$pdo->exec((string) file_get_contents(__DIR__ . '/schema.sql'));

echo "Schema applied to database '$name'.\n";

<?php

/**
 * Applies database/schema.sql. Usage: php database/migrate.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Shop\Config;

$db = Config::get('db');
$dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']);
$pdo = new PDO($dsn, $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$sql = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec($sql);

echo "Schema applied to database '{$db['name']}'.\n";

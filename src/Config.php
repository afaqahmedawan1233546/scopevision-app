<?php

declare(strict_types=1);

namespace Shop;

final class Config
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        if (self::$config === null) {
            self::$config = require dirname(__DIR__) . '/config/config.php';
        }

        return self::$config[$key] ?? $default;
    }
}

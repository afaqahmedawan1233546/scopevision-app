<?php

declare(strict_types=1);

namespace Shop;

/**
 * Reads config/column_map.php and answers every "which Excel header maps to
 * which database column?" question. No other file knows about Excel headers.
 */
final class ColumnMap
{
    /** @var array<string, mixed>|null */
    private static ?array $map = null;

    /** @return array<string, mixed> */
    private static function map(): array
    {
        if (self::$map === null) {
            self::$map = require dirname(__DIR__) . '/config/column_map.php';
        }

        return self::$map;
    }

    /** Normalise a header so spacing, punctuation and case never matter. */
    public static function normalise(string $header): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', trim($header)));
    }

    /** @return array<int, array<string, mixed>> */
    public static function columns(): array
    {
        return self::map()['columns'];
    }

    /** @return array<int, string> database column names, in mapping order */
    public static function dbColumns(): array
    {
        return array_map(static fn (array $c): string => $c['db'], self::columns());
    }

    /** @return array<string, array<string, mixed>> keyed by database column */
    public static function byDbColumn(): array
    {
        $out = [];
        foreach (self::columns() as $column) {
            $out[$column['db']] = $column;
        }

        return $out;
    }

    public static function label(string $dbColumn): string
    {
        return self::byDbColumn()[$dbColumn]['label'] ?? ucfirst(str_replace('_', ' ', $dbColumn));
    }

    public static function isExcluded(string $header): bool
    {
        $key = self::normalise($header);
        if ($key === '') {
            return false;
        }

        foreach (self::map()['excluded'] as $excluded) {
            if (self::normalise($excluded) === $key) {
                return true;
            }
        }

        return false;
    }

    /** Database column for an Excel header, or null when unmapped/excluded. */
    public static function resolve(string $header): ?string
    {
        if (self::isExcluded($header)) {
            return null;
        }

        $key = self::normalise($header);
        foreach (self::columns() as $column) {
            foreach ($column['aliases'] as $alias) {
                if (self::normalise($alias) === $key) {
                    return $column['db'];
                }
            }
        }

        return null;
    }

    public static function uniqueKey(): string
    {
        return self::map()['unique_key'];
    }

    /** @return array<int, string> */
    public static function fallbackUniqueKey(): array
    {
        return self::map()['fallback_unique_key'];
    }

    /** @return array<int, string> */
    public static function requiredColumns(): array
    {
        $required = [];
        foreach (self::columns() as $column) {
            if (!empty($column['required'])) {
                $required[] = $column['db'];
            }
        }

        return $required;
    }
}

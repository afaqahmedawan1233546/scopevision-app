<?php

declare(strict_types=1);

namespace Shop;

final class Helpers
{
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function money(?float $amount): string
    {
        return '$' . number_format((float) $amount, 2);
    }

    /**
     * Rebuild the current query string with overrides (null removes a key).
     *
     * @param array<string, mixed> $overrides
     */
    public static function queryWith(array $overrides, ?array $base = null): string
    {
        $query = $base ?? $_GET;
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($query[$key]);
            } else {
                $query[$key] = $value;
            }
        }

        $string = http_build_query($query);

        return $string === '' ? '?' : '?' . $string;
    }

    /** Deterministic placeholder artwork - the Excel file carries no images. */
    public static function productImage(array $product): string
    {
        if (!empty($product['image_url'])) {
            return (string) $product['image_url'];
        }

        return 'assets/img/placeholder.svg';
    }

    public static function initials(array $product): string
    {
        $source = trim((string) ($product['manufacturer'] ?? $product['name'] ?? '?'));

        return mb_strtoupper(mb_substr($source, 0, 2));
    }

    /** @return array<string, string> label => value for the visible spec list */
    public static function specs(array $product): array
    {
        $specs = [];
        foreach (['manufacturer', 'device_type', 'storage', 'color', 'carrier', 'grade', 'condition_label', 'status'] as $column) {
            $value = $product[$column] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $specs[ColumnMap::label($column)] = (string) $value;
        }

        if ($product['battery_health'] !== null && $product['battery_health'] !== '') {
            $specs[ColumnMap::label('battery_health')] = rtrim(rtrim(number_format((float) $product['battery_health'], 1), '0'), '.') . '%';
        }

        return $specs;
    }
}

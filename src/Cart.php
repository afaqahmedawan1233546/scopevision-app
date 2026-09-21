<?php

declare(strict_types=1);

namespace Shop;

/**
 * Session cart. The quantity here is the customer's purchase quantity only -
 * it has nothing to do with any inventory quantity (never imported or stored).
 */
final class Cart
{
    private const KEY = 'cart';
    private const MAX_QTY = 10;

    /** @return array<int, int> product id => cart quantity */
    public static function items(): array
    {
        return $_SESSION[self::KEY] ?? [];
    }

    public static function add(int $productId, int $quantity = 1): void
    {
        $items = self::items();
        $items[$productId] = min(self::MAX_QTY, ($items[$productId] ?? 0) + max(1, $quantity));
        $_SESSION[self::KEY] = $items;
    }

    public static function update(int $productId, int $quantity): void
    {
        $items = self::items();
        if ($quantity <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = min(self::MAX_QTY, $quantity);
        }
        $_SESSION[self::KEY] = $items;
    }

    public static function remove(int $productId): void
    {
        $items = self::items();
        unset($items[$productId]);
        $_SESSION[self::KEY] = $items;
    }

    public static function clear(): void
    {
        $_SESSION[self::KEY] = [];
    }

    public static function totalItems(): int
    {
        return array_sum(self::items());
    }

    public static function maxQuantity(): int
    {
        return self::MAX_QTY;
    }
}

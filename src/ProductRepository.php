<?php

declare(strict_types=1);

namespace Shop;

use PDO;

/**
 * All shop reads come from MySQL - the Excel file is never read at request time.
 */
final class ProductRepository
{
    /** Filters exposed on the shop page: request key => database column. */
    public const FILTERS = [
        'manufacturer' => 'manufacturer',
        'type'         => 'device_type',
        'storage'      => 'storage',
        'color'        => 'color',
        'carrier'      => 'carrier',
        'grade'        => 'grade',
        'condition'    => 'condition_label',
    ];

    /** Sort key => ORDER BY clause. */
    public const SORTS = [
        'newest'     => 'p.created_at DESC, p.id DESC',
        'price_asc'  => 'p.price ASC, p.id ASC',
        'price_desc' => 'p.price DESC, p.id DESC',
        'name_asc'   => 'p.name ASC, p.id ASC',
        'name_desc'  => 'p.name DESC, p.id DESC',
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * @param array<string, mixed> $criteria search, filters, sort, page, per_page
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int}
     */
    public function search(array $criteria): array
    {
        $perPage = max(1, (int) ($criteria['per_page'] ?? 12));
        $page = max(1, (int) ($criteria['page'] ?? 1));

        [$where, $params] = $this->buildWhere($criteria);

        $countStatement = $this->pdo->prepare("SELECT COUNT(*) FROM products p $where");
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $orderBy = self::SORTS[$criteria['sort'] ?? ''] ?? self::SORTS['newest'];

        $statement = $this->pdo->prepare("SELECT p.* FROM products p $where ORDER BY $orderBy LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items'    => $statement->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $criteria): array
    {
        $conditions = [];
        $params = [];

        $search = trim((string) ($criteria['search'] ?? ''));
        if ($search !== '') {
            // Native prepares do not allow one named placeholder to be reused.
            $searchable = ['name', 'sku', 'manufacturer', 'color', 'storage', 'device_type'];
            $likes = [];
            foreach ($searchable as $index => $column) {
                $placeholder = ":search_$index";
                $likes[] = "p.`$column` LIKE $placeholder";
                $params[$placeholder] = '%' . $search . '%';
            }
            $conditions[] = '(' . implode(' OR ', $likes) . ')';
        }

        foreach (self::FILTERS as $key => $column) {
            $values = $criteria['filters'][$key] ?? [];
            if (!is_array($values)) {
                $values = [$values];
            }
            $values = array_values(array_filter(array_map('strval', $values), static fn (string $v): bool => $v !== ''));
            if ($values === []) {
                continue;
            }

            $placeholders = [];
            foreach ($values as $index => $value) {
                $placeholder = ":{$key}_{$index}";
                $placeholders[] = $placeholder;
                $params[$placeholder] = $value;
            }
            $conditions[] = "p.`$column` IN (" . implode(', ', $placeholders) . ')';
        }

        if (($criteria['min_price'] ?? '') !== '' && is_numeric($criteria['min_price'] ?? null)) {
            $conditions[] = 'p.price >= :min_price';
            $params[':min_price'] = (float) $criteria['min_price'];
        }
        if (($criteria['max_price'] ?? '') !== '' && is_numeric($criteria['max_price'] ?? null)) {
            $conditions[] = 'p.price <= :max_price';
            $params[':max_price'] = (float) $criteria['max_price'];
        }

        return [$conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions), $params];
    }

    /** @return array<string, array<int, string>> filter key => available values */
    public function filterOptions(): array
    {
        $options = [];
        foreach (self::FILTERS as $key => $column) {
            $statement = $this->pdo->query(
                "SELECT DISTINCT `$column` AS value FROM products WHERE `$column` IS NOT NULL AND `$column` <> '' ORDER BY `$column` ASC"
            );
            $options[$key] = array_map(static fn (array $row): string => (string) $row['value'], $statement->fetchAll());
        }

        return $options;
    }

    /** @return array{min: float, max: float} */
    public function priceRange(): array
    {
        $row = $this->pdo->query('SELECT MIN(price) AS min_price, MAX(price) AS max_price FROM products')->fetch();

        return [
            'min' => (float) ($row['min_price'] ?? 0),
            'max' => (float) ($row['max_price'] ?? 0),
        ];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $id]);

        return $statement->fetch() ?: null;
    }

    /**
     * Variations of the same model: other rows from the same manufacturer
     * sharing the product name but differing in colour / storage / carrier / grade.
     *
     * @return array<int, array<string, mixed>>
     */
    public function variations(array $product): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM products WHERE name = :name AND manufacturer <=> :manufacturer
             ORDER BY price ASC, storage ASC, color ASC'
        );
        $statement->execute([
            ':name'         => $product['name'],
            ':manufacturer' => $product['manufacturer'],
        ]);

        return $statement->fetchAll();
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $statement->execute($ids);

        return $statement->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function recentImports(int $limit = 5): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM import_logs ORDER BY id DESC LIMIT :limit');
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}

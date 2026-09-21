<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Shop\ColumnMap;
use Shop\Config;
use Shop\Helpers;
use Shop\ProductRepository;

$repository = new ProductRepository();

$filters = [];
foreach (array_keys(ProductRepository::FILTERS) as $key) {
    $value = $_GET[$key] ?? [];
    $filters[$key] = is_array($value) ? $value : [$value];
}

$criteria = [
    'search'    => (string) ($_GET['q'] ?? ''),
    'filters'   => $filters,
    'sort'      => (string) ($_GET['sort'] ?? 'newest'),
    'page'      => (int) ($_GET['page'] ?? 1),
    'per_page'  => Config::get('shop')['per_page'],
    'min_price' => $_GET['min_price'] ?? '',
    'max_price' => $_GET['max_price'] ?? '',
];

$results = $repository->search($criteria);
$options = $repository->filterOptions();
$priceRange = $repository->priceRange();

$filterLabels = [
    'manufacturer' => ColumnMap::label('manufacturer'),
    'type'         => ColumnMap::label('device_type'),
    'storage'      => ColumnMap::label('storage'),
    'color'        => ColumnMap::label('color'),
    'carrier'      => ColumnMap::label('carrier'),
    'grade'        => ColumnMap::label('grade'),
    'condition'    => ColumnMap::label('condition_label'),
];

$sortLabels = [
    'newest'     => 'Newest',
    'price_asc'  => 'Price: low to high',
    'price_desc' => 'Price: high to low',
    'name_asc'   => 'Name: A to Z',
    'name_desc'  => 'Name: Z to A',
];

$pageTitle = 'Shop devices | ScopeVision';
$basePath = '';
require dirname(__DIR__) . '/templates/header.php';
?>
<section class="shop-hero">
    <h1>Certified devices</h1>
    <p><?= (int) $results['total'] ?> product<?= $results['total'] === 1 ? '' : 's' ?> available</p>
</section>

<form class="shop-toolbar" method="get" action="index.php">
    <input class="search-input" type="search" name="q" value="<?= Helpers::e($criteria['search']) ?>"
           placeholder="Search by model, brand, SKU, colour&hellip;">
    <select class="sort-select" name="sort" onchange="this.form.submit()">
        <?php foreach ($sortLabels as $value => $label): ?>
            <option value="<?= Helpers::e($value) ?>" <?= $criteria['sort'] === $value ? 'selected' : '' ?>>
                <?= Helpers::e($label) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-primary" type="submit">Search</button>
    <?php foreach ($filters as $key => $values): ?>
        <?php foreach ($values as $value): ?>
            <?php if ($value !== ''): ?>
                <input type="hidden" name="<?= Helpers::e($key) ?>[]" value="<?= Helpers::e((string) $value) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if ($criteria['min_price'] !== ''): ?>
        <input type="hidden" name="min_price" value="<?= Helpers::e((string) $criteria['min_price']) ?>">
    <?php endif; ?>
    <?php if ($criteria['max_price'] !== ''): ?>
        <input type="hidden" name="max_price" value="<?= Helpers::e((string) $criteria['max_price']) ?>">
    <?php endif; ?>
</form>

<div class="shop-layout">
    <aside class="filters">
        <form method="get" action="index.php" id="filter-form">
            <?php if ($criteria['search'] !== ''): ?>
                <input type="hidden" name="q" value="<?= Helpers::e($criteria['search']) ?>">
            <?php endif; ?>
            <input type="hidden" name="sort" value="<?= Helpers::e($criteria['sort']) ?>">

            <div class="filter-group">
                <h2>Price</h2>
                <div class="price-inputs">
                    <input type="number" step="0.01" min="0" name="min_price" placeholder="<?= Helpers::e((string) floor($priceRange['min'])) ?>"
                           value="<?= Helpers::e((string) $criteria['min_price']) ?>">
                    <span>&ndash;</span>
                    <input type="number" step="0.01" min="0" name="max_price" placeholder="<?= Helpers::e((string) ceil($priceRange['max'])) ?>"
                           value="<?= Helpers::e((string) $criteria['max_price']) ?>">
                </div>
            </div>

            <?php foreach ($filterLabels as $key => $label): ?>
                <?php if (empty($options[$key])) { continue; } ?>
                <div class="filter-group">
                    <h2><?= Helpers::e($label) ?></h2>
                    <div class="filter-options">
                        <?php foreach ($options[$key] as $value): ?>
                            <label class="filter-option">
                                <input type="checkbox" name="<?= Helpers::e($key) ?>[]" value="<?= Helpers::e($value) ?>"
                                    <?= in_array($value, $filters[$key], true) ? 'checked' : '' ?>>
                                <span><?= Helpers::e($value) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <button class="btn btn-primary btn-block" type="submit">Apply filters</button>
            <a class="btn btn-link btn-block" href="index.php">Clear all</a>
        </form>
    </aside>

    <section class="results">
        <?php if ($results['items'] === []): ?>
            <p class="empty-state">No products match your search. Try clearing some filters.</p>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($results['items'] as $product): ?>
                    <?php require dirname(__DIR__) . '/templates/product-card.php'; ?>
                <?php endforeach; ?>
            </div>

            <?php if ($results['pages'] > 1): ?>
                <nav class="pagination">
                    <?php if ($results['page'] > 1): ?>
                        <a class="page-link" href="<?= Helpers::e(Helpers::queryWith(['page' => $results['page'] - 1])) ?>">Previous</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $results['pages']; $i++): ?>
                        <a class="page-link <?= $i === $results['page'] ? 'is-active' : '' ?>"
                           href="<?= Helpers::e(Helpers::queryWith(['page' => $i])) ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($results['page'] < $results['pages']): ?>
                        <a class="page-link" href="<?= Helpers::e(Helpers::queryWith(['page' => $results['page'] + 1])) ?>">Next</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>

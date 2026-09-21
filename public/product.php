<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Shop\Cart;
use Shop\Helpers;
use Shop\ProductRepository;

$repository = new ProductRepository();
$product = $repository->find((int) ($_GET['id'] ?? 0));

if ($product === null) {
    http_response_code(404);
    $pageTitle = 'Product not found | ScopeVision';
    $basePath = '';
    require dirname(__DIR__) . '/templates/header.php';
    echo '<p class="empty-state">This product no longer exists. <a href="index.php">Back to the shop</a>.</p>';
    require dirname(__DIR__) . '/templates/footer.php';
    exit;
}

$variations = $repository->variations($product);
$specs = Helpers::specs($product);

$pageTitle = $product['name'] . ' | ScopeVision';
$basePath = '';
require dirname(__DIR__) . '/templates/header.php';
?>
<nav class="breadcrumb"><a href="index.php">Shop</a> / <span><?= Helpers::e($product['name']) ?></span></nav>

<div class="product-detail">
    <div class="detail-media">
        <span class="thumb-initials large"><?= Helpers::e(Helpers::initials($product)) ?></span>
    </div>

    <div class="detail-info">
        <p class="product-brand"><?= Helpers::e($product['manufacturer'] ?? '') ?></p>
        <h1><?= Helpers::e($product['name']) ?></h1>
        <p class="product-sku">SKU <?= Helpers::e($product['sku']) ?></p>
        <p class="price large"><?= Helpers::money((float) $product['price']) ?></p>

        <dl class="spec-list">
            <?php foreach ($specs as $label => $value): ?>
                <div class="spec-row">
                    <dt><?= Helpers::e($label) ?></dt>
                    <dd><?= Helpers::e($value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>

        <form class="add-to-cart" method="post" action="cart.php">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <label for="quantity">Quantity</label>
            <input id="quantity" type="number" name="quantity" value="1" min="1" max="<?= Cart::maxQuantity() ?>">
            <button class="btn btn-primary" type="submit">Add to cart</button>
        </form>
    </div>
</div>

<?php if (count($variations) > 1): ?>
    <section class="variations">
        <h2>Available variations</h2>
        <table class="variation-table">
            <thead>
            <tr>
                <th>SKU</th>
                <th>Storage</th>
                <th>Color</th>
                <th>Network</th>
                <th>Grade</th>
                <th>Condition</th>
                <th>Price</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($variations as $variation): ?>
                <tr class="<?= (int) $variation['id'] === (int) $product['id'] ? 'is-current' : '' ?>">
                    <td><?= Helpers::e($variation['sku']) ?></td>
                    <td><?= Helpers::e($variation['storage'] ?? '') ?></td>
                    <td><?= Helpers::e($variation['color'] ?? '') ?></td>
                    <td><?= Helpers::e($variation['carrier'] ?? '') ?></td>
                    <td><?= Helpers::e($variation['grade'] ?? '') ?></td>
                    <td><?= Helpers::e($variation['condition_label'] ?? '') ?></td>
                    <td><?= Helpers::money((float) $variation['price']) ?></td>
                    <td>
                        <?php if ((int) $variation['id'] === (int) $product['id']): ?>
                            <span class="tag">Viewing</span>
                        <?php else: ?>
                            <a class="btn btn-link" href="product.php?id=<?= (int) $variation['id'] ?>">View</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>

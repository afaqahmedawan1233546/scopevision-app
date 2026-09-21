<?php

use Shop\Helpers;

/** @var array<string, mixed> $product */
$basePath = $basePath ?? '';
?>
<article class="product-card">
    <a class="product-thumb" href="<?= $basePath ?>product.php?id=<?= (int) $product['id'] ?>">
        <span class="thumb-initials"><?= Helpers::e(Helpers::initials($product)) ?></span>
        <?php if (!empty($product['grade'])): ?>
            <span class="badge grade-badge">Grade <?= Helpers::e($product['grade']) ?></span>
        <?php endif; ?>
    </a>
    <div class="product-body">
        <p class="product-brand"><?= Helpers::e($product['manufacturer'] ?? '') ?></p>
        <h3 class="product-name">
            <a href="<?= $basePath ?>product.php?id=<?= (int) $product['id'] ?>"><?= Helpers::e($product['name']) ?></a>
        </h3>
        <ul class="product-meta">
            <?php if (!empty($product['storage'])): ?><li><?= Helpers::e($product['storage']) ?></li><?php endif; ?>
            <?php if (!empty($product['color'])): ?><li><?= Helpers::e($product['color']) ?></li><?php endif; ?>
            <?php if (!empty($product['carrier'])): ?><li><?= Helpers::e($product['carrier']) ?></li><?php endif; ?>
            <?php if (!empty($product['condition_label'])): ?><li><?= Helpers::e($product['condition_label']) ?></li><?php endif; ?>
        </ul>
        <div class="product-footer">
            <span class="price"><?= Helpers::money((float) $product['price']) ?></span>
            <form method="post" action="<?= $basePath ?>cart.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <input type="hidden" name="quantity" value="1">
                <button class="btn btn-primary" type="submit">Add to cart</button>
            </form>
        </div>
        <p class="product-sku">SKU <?= Helpers::e($product['sku']) ?></p>
    </div>
</article>

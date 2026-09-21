<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Shop\Cart;
use Shop\Helpers;
use Shop\ProductRepository;

$repository = new ProductRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(400);
        $_SESSION['flash'] = 'Your session expired. Please try again.';
        header('Location: cart.php');
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $productId = (int) ($_POST['product_id'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 1);

    if ($action === 'add' && $repository->find($productId) !== null) {
        Cart::add($productId, $quantity);
        $_SESSION['flash'] = 'Product added to your cart.';
    } elseif ($action === 'update' && $repository->find($productId) !== null) {
        Cart::update($productId, $quantity);
    } elseif ($action === 'remove') {
        Cart::remove($productId);
    } elseif ($action === 'clear') {
        Cart::clear();
    }

    header('Location: cart.php');
    exit;
}

$items = Cart::items();
$products = $repository->findMany(array_keys($items));
$total = 0.0;
foreach ($products as $product) {
    $total += (float) $product['price'] * (int) $items[$product['id']];
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Your cart | ScopeVision';
$basePath = '';
require dirname(__DIR__) . '/templates/header.php';
?>
<h1>Your cart</h1>

<?php if ($flash !== null): ?>
    <p class="flash"><?= Helpers::e($flash) ?></p>
<?php endif; ?>

<?php if ($products === []): ?>
    <p class="empty-state">Your cart is empty. <a href="index.php">Browse the shop</a>.</p>
<?php else: ?>
    <table class="cart-table">
        <thead>
        <tr>
            <th>Product</th>
            <th>SKU</th>
            <th>Price</th>
            <th>Quantity</th>
            <th>Subtotal</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $product): ?>
            <?php $quantity = (int) $items[$product['id']]; ?>
            <tr>
                <td>
                    <a href="product.php?id=<?= (int) $product['id'] ?>"><?= Helpers::e($product['name']) ?></a>
                    <small><?= Helpers::e(trim(($product['storage'] ?? '') . ' ' . ($product['color'] ?? ''))) ?></small>
                </td>
                <td><?= Helpers::e($product['sku']) ?></td>
                <td><?= Helpers::money((float) $product['price']) ?></td>
                <td>
                    <form class="qty-form" method="post" action="cart.php">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <input type="number" name="quantity" value="<?= $quantity ?>" min="0" max="<?= Cart::maxQuantity() ?>"
                               aria-label="Cart quantity">
                        <button class="btn btn-link" type="submit">Update</button>
                    </form>
                </td>
                <td><?= Helpers::money((float) $product['price'] * $quantity) ?></td>
                <td>
                    <form method="post" action="cart.php">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="csrf_token" value="<?= Helpers::e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <button class="btn btn-link" type="submit">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="cart-summary">
        <form method="post" action="cart.php">
            <input type="hidden" name="action" value="clear">
            <input type="hidden" name="csrf_token" value="<?= Helpers::e($_SESSION['csrf_token']) ?>">
            <button class="btn btn-link" type="submit">Clear cart</button>
        </form>
        <p class="cart-total">Total <strong><?= Helpers::money($total) ?></strong></p>
    </div>
<?php endif; ?>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>

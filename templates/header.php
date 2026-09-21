<?php

use Shop\Cart;
use Shop\Helpers;

/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'ScopeVision Shop';
$basePath = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Helpers::e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= $basePath ?>assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= $basePath ?>index.php">Scope<span>Vision</span></a>
        <nav class="nav">
            <a href="<?= $basePath ?>index.php">Shop</a>
            <a href="<?= $basePath ?>admin/import-products.php">Admin Import</a>
            <a class="cart-link" href="<?= $basePath ?>cart.php">
                Cart <span class="cart-count"><?= (int) Cart::totalItems() ?></span>
            </a>
        </nav>
    </div>
</header>
<main class="container">

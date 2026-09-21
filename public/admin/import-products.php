<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Shop\Config;
use Shop\ExcelImporter;
use Shop\Helpers;
use Shop\ImportResult;
use Shop\ProductRepository;

$uploadConfig = Config::get('uploads');
$adminPassword = Config::get('admin')['password'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrfToken = $_SESSION['csrf_token'];

$errors = [];
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (hash_equals((string) $adminPassword, (string) ($_POST['password'] ?? ''))) {
        $_SESSION['is_admin'] = true;
    } else {
        $errors[] = 'Incorrect password.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    unset($_SESSION['is_admin']);
}

$isAdmin = !empty($_SESSION['is_admin']);

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Invalid session token. Please try again.';
    } elseif (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = match ($_FILES['excel_file']['error'] ?? UPLOAD_ERR_NO_FILE) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file is too large.',
            UPLOAD_ERR_NO_FILE                        => 'Please choose an Excel file to upload.',
            default                                   => 'The file could not be uploaded. Please try again.',
        };
    } else {
        $file = $_FILES['excel_file'];
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $uploadConfig['allowed_ext'], true)) {
            $errors[] = 'Only .xlsx and .xls files are allowed.';
        } elseif ($file['size'] > $uploadConfig['max_bytes']) {
            $errors[] = 'The uploaded file exceeds the maximum allowed size.';
        } else {
            if (!is_dir($uploadConfig['dir'])) {
                mkdir($uploadConfig['dir'], 0775, true);
            }
            $target = rtrim($uploadConfig['dir'], '/') . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

            if (!move_uploaded_file($file['tmp_name'], $target)) {
                $errors[] = 'The uploaded file could not be stored on the server.';
            } else {
                try {
                    $result = (new ExcelImporter())->import($target, (string) $file['name']);
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

$repository = new ProductRepository();
$pageTitle = 'Import products | ScopeVision Admin';
$basePath = '../';
require dirname(__DIR__, 2) . '/templates/header.php';
?>
<h1>Import products from Excel</h1>
<p class="muted">
    Products are imported into MySQL and the shop reads from the database only.
    Cost, IMEI / Serial, Purchase Date, Supplier, Test By, Notes and Quantity are always ignored.
</p>

<?php foreach ($errors as $error): ?>
    <p class="alert alert-error"><?= Helpers::e($error) ?></p>
<?php endforeach; ?>

<?php if (!$isAdmin): ?>
    <form class="card login-form" method="post">
        <input type="hidden" name="action" value="login">
        <label for="password">Admin password</label>
        <input id="password" type="password" name="password" required>
        <button class="btn btn-primary" type="submit">Sign in</button>
    </form>
<?php else: ?>
    <form class="card upload-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import">
        <input type="hidden" name="csrf_token" value="<?= Helpers::e($csrfToken) ?>">
        <label for="excel_file">Upload Excel file (.xlsx, .xls)</label>
        <input id="excel_file" type="file" name="excel_file" accept=".xlsx,.xls" required>
        <button class="btn btn-primary" type="submit">Import products</button>
    </form>

    <?php if ($result instanceof ImportResult): ?>
        <section class="card import-summary">
            <h2>Import completed</h2>
            <ul class="summary-grid">
                <li><span>Total Excel rows</span><strong><?= $result->totalRows ?></strong></li>
                <li><span>Imported</span><strong><?= $result->inserted ?></strong></li>
                <li><span>Updated</span><strong><?= $result->updated ?></strong></li>
                <li><span>Skipped</span><strong><?= $result->skipped ?></strong></li>
                <li><span>Errors</span><strong><?= $result->errorCount() ?></strong></li>
            </ul>

            <h3>Column mapping used</h3>
            <table class="mapping-table">
                <thead><tr><th>Excel column</th><th>Database column</th></tr></thead>
                <tbody>
                <?php foreach ($result->mappedHeaders as $header => $dbColumn): ?>
                    <tr><td><?= Helpers::e($header) ?></td><td><code><?= Helpers::e($dbColumn) ?></code></td></tr>
                <?php endforeach; ?>
                <?php foreach ($result->excludedHeaders as $header): ?>
                    <tr class="is-ignored"><td><?= Helpers::e($header) ?></td><td>Excluded &mdash; not imported</td></tr>
                <?php endforeach; ?>
                <?php foreach ($result->unmappedHeaders as $header): ?>
                    <tr class="is-ignored"><td><?= Helpers::e($header) ?></td><td>No mapping &mdash; ignored</td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($result->errors !== []): ?>
                <h3>Rows with errors</h3>
                <table class="mapping-table">
                    <thead><tr><th>Excel row</th><th>SKU</th><th>Reason</th></tr></thead>
                    <tbody>
                    <?php foreach ($result->errors as $error): ?>
                        <tr>
                            <td><?= (int) $error['row'] ?></td>
                            <td><?= Helpers::e($error['sku']) ?></td>
                            <td><?= Helpers::e($error['reason']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($result->skippedRows !== []): ?>
                <h3>Skipped rows</h3>
                <table class="mapping-table">
                    <thead><tr><th>Excel row</th><th>SKU</th><th>Reason</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($result->skippedRows, 0, 50) as $skipped): ?>
                        <tr>
                            <td><?= (int) $skipped['row'] ?></td>
                            <td><?= Helpers::e($skipped['sku']) ?></td>
                            <td><?= Helpers::e($skipped['reason']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p><a class="btn btn-primary" href="../index.php">View products on the shop</a></p>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Database</h2>
        <p><?= $repository->count() ?> products currently stored.</p>
        <h3>Recent imports</h3>
        <table class="mapping-table">
            <thead><tr><th>File</th><th>Total</th><th>Imported</th><th>Updated</th><th>Skipped</th><th>Errors</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($repository->recentImports() as $log): ?>
                <tr>
                    <td><?= Helpers::e($log['filename']) ?></td>
                    <td><?= (int) $log['total_rows'] ?></td>
                    <td><?= (int) $log['inserted_rows'] ?></td>
                    <td><?= (int) $log['updated_rows'] ?></td>
                    <td><?= (int) $log['skipped_rows'] ?></td>
                    <td><?= (int) $log['error_rows'] ?></td>
                    <td><?= Helpers::e($log['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <form method="post" class="logout-form">
        <input type="hidden" name="action" value="logout">
        <button class="btn btn-link" type="submit">Sign out</button>
    </form>
<?php endif; ?>
<?php require dirname(__DIR__, 2) . '/templates/footer.php'; ?>

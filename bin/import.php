<?php

/**
 * Command line importer. Usage: php bin/import.php /path/to/inventory.xlsx
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Shop\ExcelImporter;

$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Usage: php bin/import.php <path-to-excel-file>\n");
    exit(1);
}

$result = (new ExcelImporter())->import($path, basename($path));

printf("Import completed\n");
printf("  Total Excel rows : %d\n", $result->totalRows);
printf("  Imported         : %d\n", $result->inserted);
printf("  Updated          : %d\n", $result->updated);
printf("  Skipped          : %d\n", $result->skipped);
printf("  Errors           : %d\n", $result->errorCount());

printf("\nMapped columns:\n");
foreach ($result->mappedHeaders as $header => $column) {
    printf("  %-22s -> %s\n", $header, $column);
}
printf("\nIgnored (excluded) columns:\n");
foreach ($result->excludedHeaders as $header) {
    printf("  %s\n", $header);
}
if ($result->unmappedHeaders !== []) {
    printf("\nIgnored (unmapped) columns:\n");
    foreach ($result->unmappedHeaders as $header) {
        printf("  %s\n", $header);
    }
}
foreach ($result->errors as $error) {
    printf("Row %d (SKU %s): %s\n", $error['row'], $error['sku'], $error['reason']);
}
foreach (array_slice($result->skippedRows, 0, 20) as $skipped) {
    printf("Skipped row %d (SKU %s): %s\n", $skipped['row'], $skipped['sku'], $skipped['reason']);
}

exit($result->errorCount() > 0 ? 2 : 0);

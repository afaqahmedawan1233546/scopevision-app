<?php

declare(strict_types=1);

namespace Shop;

use PDO;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

/**
 * Reads an .xlsx/.xls file, maps its headers through ColumnMap, validates and
 * normalises every value, then inserts or updates rows in `products`.
 *
 * Excluded columns (Cost, IMEI/Serial, Purchase Date, Supplier, Test By, Notes,
 * Quantity) are dropped while reading - they never reach the database layer.
 */
final class ExcelImporter
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    public function import(string $filePath, string $originalName = ''): ImportResult
    {
        if (!is_readable($filePath)) {
            throw new RuntimeException('Excel file could not be read.');
        }

        $result = new ImportResult();

        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($filePath)->getActiveSheet();

        $rows = $sheet->toArray(null, true, false, false);
        if ($rows === []) {
            throw new RuntimeException('The Excel file is empty.');
        }

        $headerRow = array_shift($rows);
        $columnIndex = $this->mapHeaders($headerRow, $result);

        if ($columnIndex === []) {
            throw new RuntimeException('No known product columns were found in the Excel headers.');
        }

        $missingRequired = array_diff(ColumnMap::requiredColumns(), array_values($columnIndex));
        if ($missingRequired !== []) {
            throw new RuntimeException(
                'The Excel file is missing required column(s): ' . implode(', ', array_map(
                    static fn (string $db): string => ColumnMap::label($db),
                    $missingRequired
                ))
            );
        }

        $seenKeys = [];
        $rowNumber = 1; // header row

        foreach ($rows as $row) {
            $rowNumber++;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $result->totalRows++;

            try {
                $data = $this->extractRow($row, $columnIndex);
            } catch (ValidationException $e) {
                $skuIndex = array_search(ColumnMap::uniqueKey(), $columnIndex, true);
                $result->addError($rowNumber, $skuIndex === false ? '' : (string) ($row[$skuIndex] ?? ''), $e->getMessage());
                continue;
            }

            $key = $this->duplicateKey($data);
            if (isset($seenKeys[$key])) {
                $result->addSkipped($rowNumber, (string) $data['sku'], 'Duplicate of row ' . $seenKeys[$key] . ' in the same file.');
                continue;
            }
            $seenKeys[$key] = $rowNumber;

            try {
                $outcome = $this->upsert($data);
            } catch (Throwable $e) {
                $result->addError($rowNumber, (string) $data['sku'], 'Database error: ' . $e->getMessage());
                continue;
            }

            if ($outcome === 'inserted') {
                $result->inserted++;
            } elseif ($outcome === 'updated') {
                $result->updated++;
            } else {
                $result->addSkipped($rowNumber, (string) $data['sku'], 'Product already up to date.');
            }
        }

        $this->logImport($originalName !== '' ? $originalName : basename($filePath), $result);

        return $result;
    }

    /**
     * @param array<int, mixed> $headerRow
     * @return array<int, string> excel column index => database column
     */
    private function mapHeaders(array $headerRow, ImportResult $result): array
    {
        $columnIndex = [];

        foreach ($headerRow as $index => $header) {
            $header = trim((string) $header);
            if ($header === '') {
                continue;
            }

            if (ColumnMap::isExcluded($header)) {
                $result->excludedHeaders[] = $header;
                continue;
            }

            $dbColumn = ColumnMap::resolve($header);
            if ($dbColumn === null) {
                $result->unmappedHeaders[] = $header;
                continue;
            }

            if (in_array($dbColumn, $columnIndex, true)) {
                continue; // first matching header wins
            }

            $columnIndex[$index] = $dbColumn;
            $result->mappedHeaders[$header] = $dbColumn;
        }

        return $columnIndex;
    }

    /** @param array<int, mixed> $row */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, mixed>  $row
     * @param array<int, string> $columnIndex
     * @return array<string, mixed>
     */
    private function extractRow(array $row, array $columnIndex): array
    {
        $definitions = ColumnMap::byDbColumn();
        $data = array_fill_keys(ColumnMap::dbColumns(), null);

        foreach ($columnIndex as $index => $dbColumn) {
            $raw = $row[$index] ?? null;
            $data[$dbColumn] = $this->normalise($raw, $definitions[$dbColumn]);
        }

        foreach (ColumnMap::requiredColumns() as $required) {
            if ($data[$required] === null || $data[$required] === '') {
                throw new ValidationException(sprintf('Missing required value for "%s".', ColumnMap::label($required)));
            }
        }

        $unique = ColumnMap::uniqueKey();
        if ($data[$unique] === null || $data[$unique] === '') {
            $data[$unique] = $this->derivedUniqueKey($data);
        }

        return $data;
    }

    /**
     * @param mixed                $value
     * @param array<string, mixed> $definition
     * @return string|float|null
     */
    private function normalise($value, array $definition)
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $string = trim((string) $value);
        $string = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $string);
        $string = (string) preg_replace('/\s+/u', ' ', $string);

        if ($string === '' || (strcasecmp($string, 'n/a') === 0 && $definition['type'] !== 'string')) {
            return null;
        }

        switch ($definition['type']) {
            case 'decimal':
                $numeric = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $string));
                if ($numeric === '' || !is_numeric($numeric)) {
                    throw new ValidationException(sprintf('"%s" is not a valid number for "%s".', $string, $definition['label']));
                }
                $number = round((float) $numeric, 2);
                if ($number < 0) {
                    throw new ValidationException(sprintf('"%s" cannot be negative.', $definition['label']));
                }

                return $number;

            case 'ratio':
                $numeric = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $string));
                if ($numeric === '' || !is_numeric($numeric)) {
                    return null;
                }
                $number = (float) $numeric;
                if ($number > 0 && $number <= 1) {
                    $number *= 100;
                }
                if ($number < 0 || $number > 100) {
                    return null;
                }

                return round($number, 2);

            case 'string':
            default:
                if (is_float($value) && floor($value) === $value) {
                    $string = (string) (int) $value;
                }
                $max = (int) ($definition['max'] ?? 255);

                return mb_substr($string, 0, $max);
        }
    }

    /**
     * Stable identifier for sheets without a SKU column, built from the
     * configured fallback key so re-imports update instead of duplicating.
     *
     * @param array<string, mixed> $data
     */
    private function derivedUniqueKey(array $data): string
    {
        $parts = [];
        foreach (ColumnMap::fallbackUniqueKey() as $column) {
            $parts[] = strtolower(trim((string) ($data[$column] ?? '')));
        }

        if (implode('', $parts) === '') {
            throw new ValidationException('Row has no SKU and no values to identify it by.');
        }

        return 'AUTO-' . substr(sha1(implode('|', $parts)), 0, 24);
    }

    /** @param array<string, mixed> $data */
    private function duplicateKey(array $data): string
    {
        return strtolower((string) $data[ColumnMap::uniqueKey()]);
    }

    /**
     * @param array<string, mixed> $data
     * @return string inserted|updated|unchanged
     */
    private function upsert(array $data): string
    {
        $existing = $this->findExisting($data);

        if ($existing === null) {
            $columns = ColumnMap::dbColumns();
            $sql = sprintf(
                'INSERT INTO products (%s) VALUES (%s)',
                implode(', ', array_map(static fn (string $c): string => "`$c`", $columns)),
                implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
            );
            $statement = $this->pdo->prepare($sql);
            foreach ($columns as $column) {
                $statement->bindValue(':' . $column, $data[$column]);
            }
            $statement->execute();

            return 'inserted';
        }

        $changed = [];
        foreach (ColumnMap::dbColumns() as $column) {
            $new = $data[$column];
            $old = $existing[$column];
            if (is_numeric($old) && is_numeric($new)) {
                if ((float) $old !== (float) $new) {
                    $changed[$column] = $new;
                }
                continue;
            }
            if ((string) $old !== (string) $new) {
                $changed[$column] = $new;
            }
        }

        if ($changed === []) {
            return 'unchanged';
        }

        $assignments = implode(', ', array_map(static fn (string $c): string => "`$c` = :$c", array_keys($changed)));
        $statement = $this->pdo->prepare("UPDATE products SET $assignments WHERE id = :id");
        foreach ($changed as $column => $value) {
            $statement->bindValue(':' . $column, $value);
        }
        $statement->bindValue(':id', $existing['id'], PDO::PARAM_INT);
        $statement->execute();

        return 'updated';
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function findExisting(array $data): ?array
    {
        $unique = ColumnMap::uniqueKey();
        $statement = $this->pdo->prepare("SELECT * FROM products WHERE `$unique` = :value LIMIT 1");
        $statement->execute([':value' => $data[$unique]]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    private function logImport(string $filename, ImportResult $result): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO import_logs (filename, total_rows, inserted_rows, updated_rows, skipped_rows, error_rows)
             VALUES (:filename, :total, :inserted, :updated, :skipped, :errors)'
        );
        $statement->execute([
            ':filename' => mb_substr($filename, 0, 255),
            ':total'    => $result->totalRows,
            ':inserted' => $result->inserted,
            ':updated'  => $result->updated,
            ':skipped'  => $result->skipped,
            ':errors'   => $result->errorCount(),
        ]);
    }
}

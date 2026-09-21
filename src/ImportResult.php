<?php

declare(strict_types=1);

namespace Shop;

final class ImportResult
{
    public int $totalRows = 0;
    public int $inserted = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /** @var array<int, array{row:int, sku:string, reason:string}> */
    public array $errors = [];

    /** @var array<int, array{row:int, sku:string, reason:string}> */
    public array $skippedRows = [];

    /** @var array<string, string> Excel header => database column */
    public array $mappedHeaders = [];

    /** @var array<int, string> Excel headers ignored because they are excluded */
    public array $excludedHeaders = [];

    /** @var array<int, string> Excel headers with no mapping target */
    public array $unmappedHeaders = [];

    public function errorCount(): int
    {
        return count($this->errors);
    }

    public function addError(int $row, string $sku, string $reason): void
    {
        $this->errors[] = ['row' => $row, 'sku' => $sku, 'reason' => $reason];
    }

    public function addSkipped(int $row, string $sku, string $reason): void
    {
        $this->skipped++;
        $this->skippedRows[] = ['row' => $row, 'sku' => $sku, 'reason' => $reason];
    }
}

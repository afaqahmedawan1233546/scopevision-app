<?php
/**
 * SINGLE SOURCE OF TRUTH for Excel -> database column mapping.
 *
 * Every Excel header alias, database column name, data type and display label
 * lives here. If the Excel format changes, only this file needs updating -
 * no column names are hard coded anywhere else in the application.
 *
 * Header matching is done on a normalised key (lowercase, alphanumeric only),
 * so "IMEI / Serial", "imei/serial" and "IMEISERIAL" all collapse to "imeiserial".
 */

declare(strict_types=1);

return [
    /**
     * Columns imported from the Excel file.
     *
     *  db      => database column in `products`
     *  aliases => accepted Excel headers (matched case/space/punctuation insensitively)
     *  type    => string | decimal | ratio  (ratio = 0..1 or 0..100 battery health)
     *  label   => human readable label used by the frontend
     */
    'columns' => [
        [
            'db'       => 'sku',
            'aliases'  => ['SKU', 'Sku Code', 'Product ID', 'Item ID'],
            'type'     => 'string',
            'required' => true,
            'max'      => 64,
            'label'    => 'SKU',
        ],
        [
            'db'       => 'category',
            'aliases'  => ['Category'],
            'type'     => 'string',
            'required' => false,
            'max'      => 100,
            'label'    => 'Category',
        ],
        [
            'db'       => 'device_type',
            'aliases'  => ['Type', 'Device Type'],
            'type'     => 'string',
            'required' => false,
            'max'      => 100,
            'label'    => 'Type',
        ],
        [
            'db'       => 'manufacturer',
            'aliases'  => ['Manufacture', 'Manufacturer', 'Brand', 'Make'],
            'type'     => 'string',
            'required' => false,
            'max'      => 100,
            'label'    => 'Manufacturer',
        ],
        [
            'db'       => 'name',
            'aliases'  => ['Name', 'Product Name', 'Model', 'Description'],
            'type'     => 'string',
            'required' => true,
            'max'      => 255,
            'label'    => 'Model',
        ],
        [
            'db'       => 'color',
            'aliases'  => ['Color', 'Colour'],
            'type'     => 'string',
            'required' => false,
            'max'      => 100,
            'label'    => 'Color',
        ],
        [
            'db'       => 'storage',
            'aliases'  => ['Storage', 'Capacity'],
            'type'     => 'string',
            'required' => false,
            'max'      => 50,
            'label'    => 'Storage',
        ],
        [
            'db'       => 'carrier',
            'aliases'  => ['Carrier', 'Network'],
            'type'     => 'string',
            'required' => false,
            'max'      => 100,
            'label'    => 'Network',
        ],
        [
            'db'       => 'grade',
            'aliases'  => ['Grade'],
            'type'     => 'string',
            'required' => false,
            'max'      => 20,
            'label'    => 'Grade',
        ],
        [
            'db'       => 'condition_label',
            'aliases'  => ['Condition'],
            'type'     => 'string',
            'required' => false,
            'max'      => 50,
            'label'    => 'Condition',
        ],
        [
            'db'       => 'battery_health',
            'aliases'  => ['BH', 'Battery Health', 'Battery'],
            'type'     => 'ratio',
            'required' => false,
            'label'    => 'Battery Health',
        ],
        [
            'db'       => 'price',
            'aliases'  => ['Price', 'Sale Price', 'Retail Price'],
            'type'     => 'decimal',
            'required' => true,
            'label'    => 'Price',
        ],
        [
            'db'       => 'status',
            'aliases'  => ['Status', 'Availability'],
            'type'     => 'string',
            'required' => false,
            'max'      => 50,
            'label'    => 'Status',
        ],
        [
            'db'       => 'image_url',
            'aliases'  => ['Image', 'Image URL', 'Product Image', 'Photo'],
            'type'     => 'string',
            'required' => false,
            'max'      => 500,
            'label'    => 'Image',
        ],
    ],

    /**
     * Columns that must NEVER be imported, stored, displayed or used anywhere.
     * Matched on the same normalised key, so capitalisation/spacing/punctuation
     * variations are all covered.
     */
    'excluded' => [
        'Cost', 'Unit Cost', 'Purchase Cost',
        'IMEI', 'IMEI/Serial', 'IMEI / Serial', 'IMEI SERIAL', 'Serial', 'Serial Number', 'Serial No',
        'Purch Date', 'Purchase Date', 'Purchased On',
        'Supplier', 'Vendor',
        'Test By', 'Tested By',
        'Notes', 'Note', 'Remarks',
        'Qty', 'Quantity', 'Quantity in Stock', 'Stock', 'Stock Qty', 'In Stock',
    ],

    /**
     * Unique key used to decide between INSERT and UPDATE.
     * Falls back to the composite key when the primary one is empty in a row.
     */
    'unique_key'          => 'sku',
    'fallback_unique_key' => ['name', 'color', 'storage', 'carrier', 'grade'],
];

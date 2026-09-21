# ScopeVision Shop

Core PHP (no framework) device shop. Product data comes from an Excel inventory
file that is **imported into MySQL**; the storefront only ever reads the database.

```
Excel file -> Excel import -> validate & map -> MySQL -> Core PHP backend -> shop frontend -> cart
```

## Requirements

- PHP 8.0+ with `pdo_mysql`, `zip`, `xml`, `mbstring`, `gd`
- MySQL / MariaDB
- Composer (for [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet))

## Setup

```bash
composer install
php database/migrate.php          # creates the database, products and import_logs tables
php -S 127.0.0.1:8080 -t public   # or point your vhost document root at public/
```

Connection details and the admin password are read from environment variables
with local defaults in `config/config.php`:
`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `ADMIN_PASSWORD`.

`ADMIN_PASSWORD` has no default: the import page refuses to sign anyone in until
it is set. The login is rate limited (5 attempts, then a 15 minute lockout) and
all POST forms are CSRF protected.

## Importing products

Web: `/admin/import-products.php` — sign in with the admin password, upload an
`.xlsx`/`.xls` file and read the import summary (total rows, imported, updated,
skipped, errors, plus the exact column mapping that was applied).

CLI: `php bin/import.php /path/to/inventory.xlsx`

Imports are idempotent: rows are matched on `SKU`, so re-importing the same file
updates changed products instead of creating duplicates. Rows without a SKU get a
stable derived key (`AUTO-<hash>`) built from model + color + storage + carrier +
grade, so they also update rather than duplicate on re-import. Uploaded workbooks
are deleted once the import finishes.

## Column mapping

`config/column_map.php` is the single source of truth for Excel → database
mapping. Header matching ignores case, spacing and punctuation, so `IMEI / Serial`,
`imei/serial` and `IMEISERIAL` are all the same header.

| Excel column        | Database column   | Shown as        |
| ------------------- | ----------------- | --------------- |
| SKU                 | `sku`             | SKU             |
| Category            | `category`        | Category        |
| Type                | `device_type`     | Type            |
| Manufacture         | `manufacturer`    | Manufacturer    |
| Name                | `name`            | Model           |
| Color               | `color`           | Color           |
| Storage             | `storage`         | Storage         |
| Carrier             | `carrier`         | Network         |
| Grade               | `grade`           | Grade           |
| Condition           | `condition_label` | Condition       |
| BH                  | `battery_health`  | Battery Health  |
| Price               | `price`           | Price           |
| Status              | `status`          | Status          |

### Never imported

`Cost`, `IMEI / Serial`, `Purchase Date`, `Supplier`, `Test By`, `Notes` and
`Quantity` (including variations such as `Qty`, `Quantity in Stock`, `Serial
Number`, `Purch Date`, `Vendor`) are dropped while the header row is read. No
database column exists for them and they appear nowhere in the UI.

The quantity input in the cart is the customer's purchase quantity only; it has
no connection to inventory quantity.

## Layout

```
config/column_map.php   Excel <-> database mapping (single source of truth)
config/config.php       database / admin / shop settings
database/schema.sql     MySQL structure
src/                    Config, Database, ColumnMap, ExcelImporter, ImportResult,
                        ProductRepository, Cart, Helpers
public/index.php        shop: search, filters, sorting, pagination
public/product.php      product details + variations of the same model
public/cart.php         cart add / update / remove / clear
public/admin/           import-products.php upload page
bin/import.php          command line importer
```

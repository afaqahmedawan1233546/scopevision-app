-- Database structure derived from the actual Excel columns.
-- Excluded by requirement (no columns exist for them):
--   Cost, IMEI/Serial, Purch Date, Supplier, Test By, Notes, Quantity in Stock.

-- The target database is created and selected by database/migrate.php from the
-- configured DB_NAME, so no database name is hard coded here.

CREATE TABLE IF NOT EXISTS `products` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku`             VARCHAR(64)  NOT NULL COMMENT 'Excel SKU, or a derived key when the sheet has no SKU',
    `category`        VARCHAR(100)     NULL,
    `device_type`     VARCHAR(100)     NULL,
    `manufacturer`    VARCHAR(100)     NULL,
    `name`            VARCHAR(255) NOT NULL,
    `color`           VARCHAR(100)     NULL,
    `storage`         VARCHAR(50)      NULL,
    `carrier`         VARCHAR(100)     NULL,
    `grade`           VARCHAR(20)      NULL,
    `condition_label` VARCHAR(50)      NULL,
    `battery_health`  DECIMAL(5,2)     NULL COMMENT 'Battery health percentage (Excel column "BH")',
    `price`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status`          VARCHAR(50)      NULL,
    `image_url`       VARCHAR(500)     NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sku` (`sku`),
    KEY `idx_manufacturer` (`manufacturer`),
    KEY `idx_device_type` (`device_type`),
    KEY `idx_name` (`name`),
    KEY `idx_price` (`price`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `import_logs` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `filename`       VARCHAR(255) NOT NULL,
    `total_rows`     INT UNSIGNED NOT NULL DEFAULT 0,
    `inserted_rows`  INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_rows`   INT UNSIGNED NOT NULL DEFAULT 0,
    `skipped_rows`   INT UNSIGNED NOT NULL DEFAULT 0,
    `error_rows`     INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

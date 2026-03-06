-- Migration: carton pricing by category (no hardcoded rules in code)

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) Keep cart line mode (unit vs carton)
-- Compatible with MySQL/MariaDB versions that do not support ADD COLUMN IF NOT EXISTS
SET @has_sale_mode := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cart_items'
    AND COLUMN_NAME = 'sale_mode'
);
SET @sql_add_sale_mode := IF(
  @has_sale_mode = 0,
  'ALTER TABLE cart_items ADD COLUMN sale_mode ENUM(''unit'',''carton'') NOT NULL DEFAULT ''unit'' AFTER quantity',
  'SELECT 1'
);
PREPARE stmt_add_sale_mode FROM @sql_add_sale_mode;
EXECUTE stmt_add_sale_mode;
DEALLOCATE PREPARE stmt_add_sale_mode;

-- 2) Category-level tiers used for all products in a category (ex: boisson)
CREATE TABLE IF NOT EXISTS carton_category_pricing_tiers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id BIGINT UNSIGNED NOT NULL,
  min_cartons INT UNSIGNED NOT NULL,
  max_cartons INT UNSIGNED NULL,
  price_per_carton_cents INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_carton_category_tiers_category (category_id),
  KEY idx_carton_category_tiers_range (category_id, min_cartons, max_cartons),
  UNIQUE KEY uq_carton_category_tiers_range (category_id, min_cartons, max_cartons),
  CONSTRAINT fk_carton_category_tiers_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- Optional seed example (replace values with your business settings)
-- ----------------------------------------------------------------------
-- SET @boisson_category_id := (
--   SELECT id FROM categories WHERE LOWER(slug) = 'boisson' OR LOWER(name) = 'boisson' LIMIT 1
-- );
-- DELETE FROM carton_category_pricing_tiers WHERE category_id = @boisson_category_id;
-- INSERT INTO carton_category_pricing_tiers (category_id, min_cartons, max_cartons, price_per_carton_cents) VALUES
-- (@boisson_category_id, 1, 4, 230000),
-- (@boisson_category_id, 5, 14, 200000),
-- (@boisson_category_id, 15, NULL, 195000);

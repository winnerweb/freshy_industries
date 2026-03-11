-- Migration: dynamic product formats support (admin + storefront)
-- Safe for existing databases (conditional ALTERs).

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 1) Add product_variants.contenance
SET @has_contenance := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_variants'
    AND COLUMN_NAME = 'contenance'
);
SET @sql_add_contenance := IF(
  @has_contenance = 0,
  'ALTER TABLE product_variants ADD COLUMN contenance VARCHAR(120) NULL AFTER sku',
  'SELECT 1'
);
PREPARE stmt_add_contenance FROM @sql_add_contenance;
EXECUTE stmt_add_contenance;
DEALLOCATE PREPARE stmt_add_contenance;

-- 2) Add product_variants.visible_site
SET @has_visible_site := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_variants'
    AND COLUMN_NAME = 'visible_site'
);
SET @sql_add_visible_site := IF(
  @has_visible_site = 0,
  'ALTER TABLE product_variants ADD COLUMN visible_site TINYINT(1) NOT NULL DEFAULT 1 AFTER currency',
  'SELECT 1'
);
PREPARE stmt_add_visible_site FROM @sql_add_visible_site;
EXECUTE stmt_add_visible_site;
DEALLOCATE PREPARE stmt_add_visible_site;

-- 3) Add product_variants.sort_order
SET @has_sort_order := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_variants'
    AND COLUMN_NAME = 'sort_order'
);
SET @sql_add_sort_order := IF(
  @has_sort_order = 0,
  'ALTER TABLE product_variants ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER visible_site',
  'SELECT 1'
);
PREPARE stmt_add_sort_order FROM @sql_add_sort_order;
EXECUTE stmt_add_sort_order;
DEALLOCATE PREPARE stmt_add_sort_order;

-- 4) Add index product+sort (if missing)
SET @has_idx_product_sort := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_variants'
    AND INDEX_NAME = 'idx_variants_product_sort'
);
SET @sql_add_idx_product_sort := IF(
  @has_idx_product_sort = 0,
  'ALTER TABLE product_variants ADD KEY idx_variants_product_sort (product_id, sort_order, id)',
  'SELECT 1'
);
PREPARE stmt_add_idx_product_sort FROM @sql_add_idx_product_sort;
EXECUTE stmt_add_idx_product_sort;
DEALLOCATE PREPARE stmt_add_idx_product_sort;

-- 5) Optional unique constraint on (product_id, label)
-- This is only added if no duplicate labels already exist.
SET @duplicates_count := (
  SELECT COUNT(*)
  FROM (
    SELECT product_id, label, COUNT(*) AS c
    FROM product_variants
    GROUP BY product_id, label
    HAVING COUNT(*) > 1
  ) d
);
SET @has_uq_product_label := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product_variants'
    AND INDEX_NAME = 'uq_variants_product_label'
);
SET @sql_add_uq_product_label := IF(
  @has_uq_product_label = 0 AND @duplicates_count = 0,
  'ALTER TABLE product_variants ADD UNIQUE KEY uq_variants_product_label (product_id, label)',
  'SELECT 1'
);
PREPARE stmt_add_uq_product_label FROM @sql_add_uq_product_label;
EXECUTE stmt_add_uq_product_label;
DEALLOCATE PREPARE stmt_add_uq_product_label;

-- Optional visibility check
SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_variants' AND COLUMN_NAME = 'contenance') AS has_contenance,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_variants' AND COLUMN_NAME = 'visible_site') AS has_visible_site,
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_variants' AND COLUMN_NAME = 'sort_order') AS has_sort_order,
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_variants' AND INDEX_NAME = 'idx_variants_product_sort') AS has_idx_product_sort,
  (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_variants' AND INDEX_NAME = 'uq_variants_product_label') AS has_uq_product_label;


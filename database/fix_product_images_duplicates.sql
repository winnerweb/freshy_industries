-- Safe cleanup for duplicated product images (primary/decorative)
-- Goal:
-- 1) Keep only one row per (product_id, is_primary)
-- 2) Backup removed rows
-- 3) Add unique constraint to prevent future duplicates

SET NAMES utf8mb4;
SET time_zone = '+00:00';

START TRANSACTION;

-- 0) Safety backup table (all columns copied)
CREATE TABLE IF NOT EXISTS product_images_backup LIKE product_images;

-- 1) Backup only rows that would be deleted (duplicates rank > 1)
INSERT IGNORE INTO product_images_backup (
  id, product_id, image_url, is_primary, sort_order, created_at
)
SELECT
  d.id, d.product_id, d.image_url, d.is_primary, d.sort_order, d.created_at
FROM (
  SELECT
    pi.*,
    ROW_NUMBER() OVER (
      PARTITION BY pi.product_id, pi.is_primary
      ORDER BY pi.sort_order ASC, pi.id ASC
    ) AS rn
  FROM product_images pi
) AS d
WHERE d.rn > 1;

-- 2) Delete duplicated rows, keep rn = 1
DELETE pi
FROM product_images pi
JOIN (
  SELECT id
  FROM (
    SELECT
      pi.id,
      ROW_NUMBER() OVER (
        PARTITION BY pi.product_id, pi.is_primary
        ORDER BY pi.sort_order ASC, pi.id ASC
      ) AS rn
    FROM product_images pi
  ) z
  WHERE z.rn > 1
) dup ON dup.id = pi.id;

-- 3) Add uniqueness guard to avoid recurrence
-- If this fails, rollback and inspect remaining duplicates.
SET @has_uq := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'product_images'
    AND index_name = 'uq_product_images_product_primary'
);
SET @sql_uq := IF(
  @has_uq = 0,
  'ALTER TABLE product_images ADD UNIQUE KEY uq_product_images_product_primary (product_id, is_primary)',
  'SELECT 1'
);
PREPARE stmt_uq FROM @sql_uq;
EXECUTE stmt_uq;
DEALLOCATE PREPARE stmt_uq;

COMMIT;

-- Verification queries (run after COMMIT)
-- A) Must be zero rows:
-- SELECT product_id, is_primary, COUNT(*) AS c
-- FROM product_images
-- GROUP BY product_id, is_primary
-- HAVING COUNT(*) > 1;
--
-- B) Backup rows copied:
-- SELECT COUNT(*) AS backed_up_rows FROM product_images_backup;

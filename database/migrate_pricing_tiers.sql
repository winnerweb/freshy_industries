-- Migration: dynamic carton pricing tiers
-- Run once on existing databases.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS pricing_tiers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  variant_id BIGINT UNSIGNED NOT NULL,
  min_cartons INT UNSIGNED NOT NULL,
  max_cartons INT UNSIGNED NULL,
  price_per_carton_cents INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pricing_tiers_variant (variant_id),
  KEY idx_pricing_tiers_range (variant_id, min_cartons, max_cartons),
  UNIQUE KEY uq_pricing_tiers_variant_range (variant_id, min_cartons, max_cartons),
  CONSTRAINT fk_pricing_tiers_variant
    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------
-- Example seed for one variant (replace :variant_id with real ID).
-- Prices are stored in cents (XOF x 100).
--
-- 1 à 4 cartons   -> 2300 FCFA = 230000 cents
-- 5 à 14 cartons  -> 2000 FCFA = 200000 cents
-- 15+ cartons     -> 1950 FCFA = 195000 cents
-- ----------------------------------------------------------------------
--
-- DELETE FROM pricing_tiers WHERE variant_id = :variant_id;
-- INSERT INTO pricing_tiers (variant_id, min_cartons, max_cartons, price_per_carton_cents) VALUES
-- (:variant_id, 1, 4, 230000),
-- (:variant_id, 5, 14, 200000),
-- (:variant_id, 15, NULL, 195000);


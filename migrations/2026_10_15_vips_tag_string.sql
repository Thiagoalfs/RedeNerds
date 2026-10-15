-- ====================================================================
-- MIGRAÇÃO: FORMATO DE COR DA TAG (COLORWHEEL vs STRING MINECRAFT)
-- Data: 2026-10-15
-- Compatível com MySQL 5.7 (produção) e idempotente.
-- ====================================================================

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vips' AND COLUMN_NAME = 'cor_tipo') = 0,
  'ALTER TABLE `vips` ADD COLUMN `cor_tipo` VARCHAR(20) NOT NULL DEFAULT ''colorwheel'' AFTER `cor2`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vips' AND COLUMN_NAME = 'cor_string') = 0,
  'ALTER TABLE `vips` ADD COLUMN `cor_string` TEXT NULL AFTER `cor_tipo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

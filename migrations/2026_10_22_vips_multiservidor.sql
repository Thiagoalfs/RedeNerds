-- ====================================================================
-- MIGRAÇÃO: Permitir VIPs Multiservidor e Custom PackageId
-- Data: 2026-10-22
-- 1. Permite que pacotes VIP sejam globais para toda a rede (servidor_id NULL).
-- 2. Converte packageId de coluna gerada para regular VARCHAR(100).
-- ====================================================================

ALTER TABLE `vips` MODIFY COLUMN `servidor_id` INT NULL DEFAULT NULL;
ALTER TABLE `vips` MODIFY COLUMN `packageId` VARCHAR(100) NULL DEFAULT NULL;

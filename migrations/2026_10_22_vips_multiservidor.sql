-- ====================================================================
-- MIGRAÇÃO: Permitir VIPs Multiservidor (servidor_id NULL / 0)
-- Data: 2026-10-22
-- Permite que pacotes VIP sejam globais para toda a rede (servidor_id NULL).
-- ====================================================================

ALTER TABLE `vips` MODIFY COLUMN `servidor_id` INT NULL DEFAULT NULL;

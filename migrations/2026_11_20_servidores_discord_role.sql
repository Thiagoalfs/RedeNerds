-- ====================================================================
-- MIGRAÇÃO: Cargo do Discord por Servidor para Menção em Webhooks
-- Data: 2026-11-20
-- 1. Adiciona servidores.discord_role_id (snowflake do cargo no Discord).
-- ====================================================================

ALTER TABLE `servidores`
    ADD COLUMN `discord_role_id` VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;

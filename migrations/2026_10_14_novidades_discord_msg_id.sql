-- ====================================================================
-- MIGRAÇÃO: COLUNA discord_msg_id EM novidades
-- Data: 2026-10-14
-- Armazena o ID da mensagem enviada ao Discord para permitir edição e
-- exclusão sincronizadas do anúncio (webhook_helper.php).
-- ====================================================================

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'novidades' AND COLUMN_NAME = 'discord_msg_id') = 0,
  'ALTER TABLE `novidades` ADD COLUMN `discord_msg_id` VARCHAR(32) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

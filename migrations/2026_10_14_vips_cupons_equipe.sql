-- ====================================================================
-- MIGRAÇÃO: COLUNAS DE vips/cupons E TABELA equipe_cargos
-- Data: 2026-10-14
-- Consolida o DDL que antes era executado em runtime pelas páginas do
-- painel admin (admin/vips/*, admin/cupons/*, admin/api/equipe/cargo_criar.php).
--
-- Compatível com MySQL 5.7 (produção) e idempotente: cada ALTER só
-- executa se a coluna realmente não existir. Servidor de produção:
-- 5.7.32-35-log.
-- ====================================================================

-- 1. Cores do gradiente das tags nos pacotes VIP
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vips' AND COLUMN_NAME = 'cor1') = 0,
  'ALTER TABLE `vips` ADD COLUMN `cor1` VARCHAR(20) NULL DEFAULT ''#ffffff''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vips' AND COLUMN_NAME = 'cor2') = 0,
  'ALTER TABLE `vips` ADD COLUMN `cor2` VARCHAR(20) NULL DEFAULT ''#ffffff''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Servidor aplicável do cupom (0 = global)
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cupons' AND COLUMN_NAME = 'servidor_id') = 0,
  'ALTER TABLE `cupons` ADD COLUMN `servidor_id` INT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Tabela de hierarquia de cargos da equipe
CREATE TABLE IF NOT EXISTS `equipe_cargos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL UNIQUE,
  `cor` VARCHAR(20) NOT NULL DEFAULT '#27acff',
  `ordem` INT NOT NULL DEFAULT 0,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ====================================================================
-- MIGRAÇÃO: SISTEMA DE LOGS DE AUDITORIA DO PAINEL ADMIN
-- Data: 2026-11-15
-- ====================================================================

CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NULL,
  `usuario_nome` VARCHAR(100) NOT NULL,
  `cargo` VARCHAR(100) NULL DEFAULT NULL,
  `acao` VARCHAR(40) NOT NULL,
  `modulo` VARCHAR(50) NOT NULL,
  `alvo_id` INT NULL DEFAULT NULL,
  `descricao` VARCHAR(500) NOT NULL,
  `detalhes` TEXT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_admin_logs_criado` (`criado_em`),
  INDEX `idx_admin_logs_modulo_acao` (`modulo`, `acao`),
  INDEX `idx_admin_logs_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

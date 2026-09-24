-- ====================================================================
-- MIGRAÇÃO: SISTEMA DE PACOTES DE CHAVES E QUANTIDADE EM PEDIDOS
-- Data: 2026-09-22
-- ====================================================================

-- 1. Criação da tabela de chaves
CREATE TABLE IF NOT EXISTS `chaves` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `servidor_id` INT NOT NULL DEFAULT 0,
  `servidor` VARCHAR(100) NOT NULL DEFAULT '',
  `nome` VARCHAR(100) NOT NULL,
  `imagem` VARCHAR(255) NULL DEFAULT NULL,
  `packageId` VARCHAR(100) NOT NULL,
  `preco` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `destaque` TINYINT(1) NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `cor1` VARCHAR(20) NOT NULL DEFAULT '#FFD700',
  `cor2` VARCHAR(20) NOT NULL DEFAULT '#FFA500',
  `vantagens` TEXT NULL,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_srv (`servidor_id`, `ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Alteração na tabela de pedidos para registrar quantidade e tipo de produto
ALTER TABLE `pedidos_vip` 
  ADD COLUMN IF NOT EXISTS `quantidade` INT NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `tipo_produto` VARCHAR(20) NOT NULL DEFAULT 'vip',
  ADD COLUMN IF NOT EXISTS `chave_id` INT NULL DEFAULT NULL;

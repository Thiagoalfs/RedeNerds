-- ==============================================================================
-- Migration: 2026_09_pagamentos_internacional.sql
-- Objetivo: Suporte a Checkout Pro Internacional, status 'recusado', 
--           idempotência de cupons e rate limit por e-mail.
--
-- ⚠️ INSTRUÇÕES DE SEGURANÇA:
-- 1. Realize backup completo do banco de dados antes de executar em produção.
-- 2. Teste previamente em ambiente de staging / cópia do banco.
-- 3. As tabelas utilizam ENGINE=InnoDB com transações seguras.
-- 4. PÓS-DEPLOY: Execute novamente o comando de backfill abaixo logo após a 
--    subida do código caso tenham ocorrido transações durante o processo.
-- ==============================================================================

-- 1. Atualização da tabela pedidos_vip
ALTER TABLE `pedidos_vip`
  MODIFY COLUMN `status` enum('pendente','pago','cancelado','expirado','recusado') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  MODIFY COLUMN `metodo_pagamento` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'pix',
  MODIFY COLUMN `payer_cpf` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  ADD COLUMN `cupom_computado` tinyint(1) NOT NULL DEFAULT 0 AFTER `desconto_aplicado`,
  ADD COLUMN `pais_ip` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `payer_cpf`;

-- 2. Atualização da tabela rate_limits_loja para suporte a e-mail
ALTER TABLE `rate_limits_loja`
  ADD COLUMN `email` varchar(150) DEFAULT NULL AFTER `cpf`,
  ADD KEY `idx_email_tempo` (`email`, `tentativa_em`);

-- 3. Backfill inicial: marca todos os pedidos existentes como cupom já computado
--    para evitar que confirmações tardias de pedidos antigos incrementem cupons indevidamente.
UPDATE `pedidos_vip` SET `cupom_computado` = 1;

-- ==============================================================================
-- Fim da Migration
-- ==============================================================================

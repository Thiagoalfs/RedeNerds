-- ==============================================================================
-- Migration: 2026_09_pagamentos_internacional.sql
-- Objetivo: Suporte a Checkout Pro Internacional, status 'recusado', 
--           idempotência de cupons e rate limit por e-mail.
--
-- ⚠️ INSTRUÇÕES DE SEGURANÇA E DEPLOY:
-- 1. Execute esta migration ANTES do deploy do código em produção.
-- 2. Realize backup completo (mysqldump) do banco de dados antes de executar.
-- 3. Teste previamente em ambiente de staging / cópia do banco.
-- 4. Confirme que as tabelas utilizam ENGINE=InnoDB com transações seguras.
-- 5. PÓS-DEPLOY: Execute a query de backfill da Seção 3 informando a data/hora 
--    exata em que o novo código entrou no ar para marcar pedidos legados.
-- ==============================================================================

-- 1. Atualização da tabela pedidos_vip
-- Preserva NOT NULL, defaults e collation originais
ALTER TABLE `pedidos_vip`
  MODIFY COLUMN `status` enum('pendente','pago','cancelado','expirado','recusado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  MODIFY COLUMN `metodo_pagamento` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pix',
  MODIFY COLUMN `payer_cpf` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  ADD COLUMN `cupom_computado` tinyint(1) NOT NULL DEFAULT 0 AFTER `desconto_aplicado`,
  ADD COLUMN `pais_ip` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `payer_cpf`;

-- 2. Atualização da tabela rate_limits_loja para suporte a e-mail
ALTER TABLE `rate_limits_loja`
  ADD COLUMN `email` varchar(150) DEFAULT NULL AFTER `cpf`,
  ADD KEY `idx_email_tempo` (`email`, `tentativa_em`);

-- 3. Backfill pós-deploy:
-- Marca pedidos anteriores ao deploy como cupom_computado = 1 para evitar
-- que pagamentos antigos confirmados tardiamente incrementem cupons indevidamente.
-- NOTA: Substitua '2026-09-20 00:00:00' pela data/hora exata do deploy.
UPDATE `pedidos_vip` 
SET `cupom_computado` = 1 
WHERE `criado_em` < '2026-09-20 00:00:00';

-- ==============================================================================
-- Fim da Migration
-- ==============================================================================

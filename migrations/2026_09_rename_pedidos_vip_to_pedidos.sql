-- ==============================================================================
-- MIGRAÇÃO: RENOMEAR TABELA PEDIDOS_VIP PARA PEDIDOS
-- Data: 2026-09
-- Descrição: Unifica a entidade de pedidos para atender tanto VIPs quanto Chaves.
-- ==============================================================================

-- 1. Renomeia a tabela principal caso exista com o nome legado
RENAME TABLE `pedidos_vip` TO `pedidos`;

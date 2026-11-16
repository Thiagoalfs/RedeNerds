-- ====================================================================
-- MIGRAÇÃO: Índices de Performance para Consultas Críticas
-- Data: 2026-11-15
-- 1. pedidos: fila de entrega pendente, agregação por método e busca por e-mail.
-- 2. wiki_artigos: listagem pública ordenada por categoria/publicado.
-- 3. vips: catálogo ativo ordenado por preço/servidor.
-- 4. cupons: contagem/listagem de cupons ativos não expirados.
-- ====================================================================

ALTER TABLE `pedidos`
    ADD KEY `idx_pedidos_entrega_pendente` (`status`, `entregue`, `id`),
    ADD KEY `idx_pedidos_status_metodo` (`status`, `metodo_pagamento`),
    ADD KEY `idx_pedidos_payer_email` (`payer_email`);

ALTER TABLE `wiki_artigos`
    ADD KEY `idx_wiki_artigos_cat_pub_ordem` (`categoria_id`, `publicado`, `ordem`);

ALTER TABLE `vips`
    ADD KEY `idx_vips_ativo_srv_preco` (`ativo`, `servidor_id`, `preco`);

ALTER TABLE `cupons`
    ADD KEY `idx_cupons_ativo_expira` (`ativo`, `expira_em`);

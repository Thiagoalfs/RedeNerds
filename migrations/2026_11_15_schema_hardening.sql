-- ====================================================================
-- MIGRAÇÃO: Endurecimento de Modelagem, Collations, FKs e Índices (Escopo 1)
-- Data: 2026-11-15
-- 1. Padroniza charset/collation para utf8mb4_unicode_ci em todas as tabelas.
-- 2. Substitui sentinela 0 por NULL em chaves.servidor_id e cupons.servidor_id.
-- 3. Adiciona FKs de integridade referencial (chaves, cupons, equipe, pedidos, admin_logs).
-- 4. Padroniza pedidos.tipo_produto, novidades.autor e índices de consulta.
-- 5. Converte colunas TIMESTAMP legadas para DATETIME.
-- ====================================================================

-- 1. Padronização de Charset / Collation
ALTER TABLE `equipe_cargos` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `equipe` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `chaves` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `cupons` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `rate_limits_loja` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Sentinela 0 -> NULL e FKs para servidores(id)
ALTER TABLE `chaves`
    MODIFY COLUMN `servidor_id` INT NULL DEFAULT NULL;

UPDATE `chaves` SET `servidor_id` = NULL WHERE `servidor_id` = 0;
UPDATE `chaves` c
    LEFT JOIN `servidores` s ON c.`servidor_id` = s.`id`
    SET c.`servidor_id` = NULL
    WHERE c.`servidor_id` IS NOT NULL AND s.`id` IS NULL;

ALTER TABLE `chaves`
    ADD CONSTRAINT `fk_chaves_servidor`
    FOREIGN KEY (`servidor_id`) REFERENCES `servidores` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `cupons`
    MODIFY COLUMN `servidor_id` INT NULL DEFAULT NULL;

UPDATE `cupons` SET `servidor_id` = NULL WHERE `servidor_id` = 0;
UPDATE `cupons` c
    LEFT JOIN `servidores` s ON c.`servidor_id` = s.`id`
    SET c.`servidor_id` = NULL
    WHERE c.`servidor_id` IS NOT NULL AND s.`id` IS NULL;

ALTER TABLE `cupons`
    ADD KEY `idx_cupons_servidor` (`servidor_id`),
    ADD CONSTRAINT `fk_cupons_servidor`
    FOREIGN KEY (`servidor_id`) REFERENCES `servidores` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- 3. FKs em equipe -> equipe_cargos e admin_logs -> usuarios
ALTER TABLE `equipe`
    ADD KEY `idx_equipe_cargo` (`cargo`),
    ADD CONSTRAINT `fk_equipe_cargo`
    FOREIGN KEY (`cargo`) REFERENCES `equipe_cargos` (`nome`)
    ON DELETE RESTRICT ON UPDATE CASCADE;

UPDATE `admin_logs` l
    LEFT JOIN `usuarios` u ON l.`usuario_id` = u.`id`
    SET l.`usuario_id` = NULL
    WHERE l.`usuario_id` IS NOT NULL AND u.`id` IS NULL;

ALTER TABLE `admin_logs`
    ADD CONSTRAINT `fk_admin_logs_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- 4. Integridade, tipagem e índices em pedidos
UPDATE `pedidos` p
    LEFT JOIN `vips` v ON p.`vip_id` = v.`id`
    SET p.`vip_id` = NULL
    WHERE p.`vip_id` IS NOT NULL AND v.`id` IS NULL;

UPDATE `pedidos` p
    LEFT JOIN `chaves` c ON p.`chave_id` = c.`id`
    SET p.`chave_id` = NULL
    WHERE p.`chave_id` IS NOT NULL AND c.`id` IS NULL;

UPDATE `pedidos` p
    LEFT JOIN `servidores` s ON p.`servidor_id` = s.`id`
    SET p.`servidor_id` = NULL
    WHERE p.`servidor_id` IS NOT NULL AND s.`id` IS NULL;

ALTER TABLE `pedidos`
    MODIFY COLUMN `tipo_produto` ENUM('vip', 'chave') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vip',
    ADD KEY `idx_pedidos_chave_id` (`chave_id`),
    ADD KEY `idx_pedidos_mp_payment_id` (`mp_payment_id`),
    ADD KEY `idx_pedidos_cupom_codigo` (`cupom_codigo`),
    ADD CONSTRAINT `fk_pedidos_vip`
    FOREIGN KEY (`vip_id`) REFERENCES `vips` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_pedidos_chave`
    FOREIGN KEY (`chave_id`) REFERENCES `chaves` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- 5. Melhorias em novidades, rate_limits_loja e tentativas_login
ALTER TABLE `novidades`
    MODIFY COLUMN `autor` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    ADD KEY `idx_novidades_criado` (`criado_em`),
    ADD KEY `idx_novidades_cat_criado` (`category`, `criado_em`);

ALTER TABLE `rate_limits_loja`
    ADD KEY `idx_rate_endpoint_ip_tempo` (`endpoint`, `ip`, `tentativa_em`),
    ADD KEY `idx_rate_tempo` (`tentativa_em`);

ALTER TABLE `tentativas_login`
    ADD KEY `idx_tentativas_ultima` (`ultima_tentativa`);

-- 6. Padronização TIMESTAMP -> DATETIME
ALTER TABLE `chaves`
    MODIFY COLUMN `criado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `equipe`
    MODIFY COLUMN `criado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `equipe_cargos`
    MODIFY COLUMN `criado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `novidades`
    MODIFY COLUMN `criado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `parceiros`
    MODIFY COLUMN `criado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `pedidos`
    MODIFY COLUMN `criado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `servidores`
    MODIFY COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `site_cache_versions`
    MODIFY COLUMN `atualizado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `wiki_artigos`
    MODIFY COLUMN `criado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY COLUMN `atualizado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `wiki_categorias`
    MODIFY COLUMN `criado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

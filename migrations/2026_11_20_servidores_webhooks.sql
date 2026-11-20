-- ====================================================================
-- MIGRAÇÃO: Webhooks do Discord por Servidor e Categoria
-- Data: 2026-11-20
-- 1. Armazena URLs de webhook por servidor (Anúncios e Atualizações).
-- 2. Cascata ao excluir servidor para não deixar registros órfãos.
-- ====================================================================

CREATE TABLE IF NOT EXISTS `servidores_webhooks` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `servidor_id` INT NOT NULL,
    `categoria` ENUM('Anúncios', 'Atualizações') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `webhook_url` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `atualizado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_srv_webhook_cat` (`servidor_id`, `categoria`),
    CONSTRAINT `fk_srv_webhooks_servidor`
        FOREIGN KEY (`servidor_id`) REFERENCES `servidores` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

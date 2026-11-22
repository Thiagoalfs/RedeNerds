-- ====================================================================
-- MIGRAÇÃO: Biblioteca de Uploads Genéricos em assets/
-- Data: 2026-11-22
-- 1. Registra imagens enviadas pelo painel em subpastas de assets/.
-- 2. Armazena nome, pasta, caminho público, autor e data de envio.
-- ====================================================================

CREATE TABLE IF NOT EXISTS `site_uploads` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nome` VARCHAR(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `pasta` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `caminho_publico` VARCHAR(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `tamanho_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
    `usuario_id` INT NULL DEFAULT NULL,
    `autor_nome` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `criado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_upload_caminho` (`caminho_publico`),
    KEY `idx_uploads_pasta_criado` (`pasta`, `criado_em`),
    KEY `idx_uploads_criado` (`criado_em`),
    CONSTRAINT `fk_site_uploads_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

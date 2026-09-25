-- Migração: Criação da tabela de controle de versões de cache (site_cache_versions)
CREATE TABLE IF NOT EXISTS `site_cache_versions` (
    `chave` VARCHAR(50) NOT NULL PRIMARY KEY,
    `versao` VARCHAR(64) NOT NULL,
    `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserção das chaves iniciais com timestamps únicos
INSERT INTO `site_cache_versions` (`chave`, `versao`) VALUES
('parceiros', MD5(CONCAT('parceiros_', UNIX_TIMESTAMP()))),
('equipe', MD5(CONCAT('equipe_', UNIX_TIMESTAMP()))),
('servidores', MD5(CONCAT('servidores_', UNIX_TIMESTAMP()))),
('novidades', MD5(CONCAT('novidades_', UNIX_TIMESTAMP())))
ON DUPLICATE KEY UPDATE `versao` = VALUES(`versao`);

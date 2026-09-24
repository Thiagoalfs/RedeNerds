-- Migração: Criação da tabela de parceiros e inserção de dados iniciais
CREATE TABLE IF NOT EXISTS parceiros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  
ome VARCHAR(100) NOT NULL,
  oto VARCHAR(255) NULL DEFAULT NULL,
  youtube VARCHAR(255) NULL DEFAULT NULL,
  	iktok VARCHAR(255) NULL DEFAULT NULL,
  instagram VARCHAR(255) NULL DEFAULT NULL,
  	witch VARCHAR(255) NULL DEFAULT NULL,
  kick VARCHAR(255) NULL DEFAULT NULL,
  tivo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ativo_nome (tivo, 
ome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserção dos parceiros atuais em ordem alfabética
INSERT INTO parceiros (
ome, oto, youtube, 	iktok, instagram, 	witch, kick, tivo) VALUES
('Danrique', '/assets/images/danrique.webp', 'https://www.youtube.com/@Danrique', NULL, NULL, NULL, NULL, 1),
('Matuzzo', '/assets/images/matuzzo.jpg', 'https://www.youtube.com/@Matuzzo-MTZ', 'https://www.tiktok.com/@matuzzo.mtz', 'https://www.instagram.com/matuzzo.mtz', NULL, NULL, 1),
('Xonera Mil Grau', '/assets/images/xonera.png', 'https://www.youtube.com/@XoneraMilGrau', 'https://www.tiktok.com/@xoneramilgrau', NULL, NULL, NULL, 1);
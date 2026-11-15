-- Migração: vincula usuarios à equipe e adiciona e-mail corporativo para login e auto-login no RNMail.
ALTER TABLE usuarios
    ADD COLUMN equipe_id INT NULL AFTER id,
    ADD COLUMN email VARCHAR(255) NULL AFTER usuario,
    ADD COLUMN email_recuperacao VARCHAR(255) NULL AFTER email,
    ADD COLUMN reset_token_hash VARCHAR(64) NULL AFTER admin,
    ADD COLUMN reset_expira_em DATETIME NULL AFTER reset_token_hash,
    ADD UNIQUE KEY uq_usuarios_equipe_id (equipe_id),
    ADD UNIQUE KEY uq_usuarios_email (email),
    ADD KEY idx_usuarios_reset_token (reset_token_hash),
    ADD CONSTRAINT fk_usuarios_equipe
        FOREIGN KEY (equipe_id) REFERENCES equipe(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE;

-- Vincula automaticamente contas existentes pelo nick na tabela equipe.
UPDATE usuarios u
INNER JOIN equipe e ON LOWER(e.nick) COLLATE utf8mb4_unicode_ci = LOWER(u.usuario) COLLATE utf8mb4_unicode_ci
SET u.equipe_id = e.id
WHERE u.equipe_id IS NULL;

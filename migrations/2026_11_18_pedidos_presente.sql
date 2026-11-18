-- Adiciona coluna destinatario (NULL = entrega para o proprio comprador em pedidos.nick)
ALTER TABLE pedidos
    ADD COLUMN destinatario VARCHAR(32) NULL DEFAULT NULL AFTER nick,
    ADD INDEX idx_pedidos_destinatario (destinatario);

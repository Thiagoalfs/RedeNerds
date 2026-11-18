<?php
/**
 * expiracao_helper.php
 * Helper para expirar em lote pedidos pendentes que ultrapassaram o tempo limite.
 */

if (!function_exists('expirarPedidosPendentes')) {
    /**
     * Marca como 'expirado' os pedidos 'pendente' que excederam o tempo limite:
     * - 15 minutos (900s) para PIX e demais métodos
     * - 2 horas (7200s) para checkout_pro
     */
    function expirarPedidosPendentes(PDO $pdo, int $limitePadraoSeg = 900, int $limiteCheckoutProSeg = 7200): int
    {
        $sql = "
            UPDATE pedidos
            SET status = 'expirado'
            WHERE status = 'pendente'
              AND (
                (LOWER(COALESCE(metodo_pagamento, 'pix')) = 'checkout_pro' AND TIMESTAMPDIFF(SECOND, criado_em, NOW()) >= :lim_cp)
                OR
                (LOWER(COALESCE(metodo_pagamento, 'pix')) <> 'checkout_pro' AND TIMESTAMPDIFF(SECOND, criado_em, NOW()) >= :lim_padrao)
              )
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':lim_cp' => $limiteCheckoutProSeg,
            ':lim_padrao' => $limitePadraoSeg,
        ]);
        return $stmt->rowCount();
    }
}

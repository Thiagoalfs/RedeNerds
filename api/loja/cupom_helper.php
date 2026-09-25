<?php
/**
 * cupom_helper.php
 * Gerenciamento idempotente do cômputo de cupons de desconto.
 */

declare(strict_types=1);

/**
 * Incrementa o contador de uso do cupom exclusivamente quando o pedido estiver 'pago'
 * e ainda não tiver tido o cupom computado (idempotente).
 *
 * @param PDO $pdo Instância ativa do PDO
 * @param string $txid Identificador da transação
 * @return bool Retorna true se o cupom foi computado nesta chamada, ou false se já estava computado / pedido não pago.
 */
function registrarUsoCupomSePago(PDO $pdo, string $txid): bool {
    $txid = trim($txid);
    if (empty($txid)) {
        return false;
    }

    try {
        $pdo->beginTransaction();

        // 1. Busca dados do pedido bloqueando a linha se necessário
        $stmtSel = $pdo->prepare("
            SELECT cupom_codigo, status, cupom_computado 
            FROM pedidos 
            WHERE txid = :txid 
            LIMIT 1
        ");
        $stmtSel->execute([':txid' => $txid]);
        $pedido = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if (!$pedido || strtolower((string)$pedido['status']) !== 'pago' || (int)($pedido['cupom_computado'] ?? 0) === 1) {
            $pdo->rollBack();
            return false;
        }

        // 2. Marca cupom_computado = 1 de forma atômica
        $stmtUp = $pdo->prepare("
            UPDATE pedidos 
            SET cupom_computado = 1 
            WHERE txid = :txid AND status = 'pago' AND (cupom_computado = 0 OR cupom_computado IS NULL)
        ");
        $stmtUp->execute([':txid' => $txid]);

        if ($stmtUp->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }

        // 3. Se havia um cupom aplicado, incrementa usos_total
        $cupomCodigo = trim((string)($pedido['cupom_codigo'] ?? ''));
        if (!empty($cupomCodigo)) {
            $stmtCupom = $pdo->prepare("
                UPDATE cupons 
                SET usos_total = usos_total + 1 
                WHERE codigo = :codigo
            ");
            $stmtCupom->execute([':codigo' => $cupomCodigo]);
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("ERRO CUPOM_HELPER: Falha ao registrar uso do cupom para txid {$txid}: " . $e->getMessage());
        return false;
    }
}

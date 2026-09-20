<?php
/**
 * webhook_atomicidade_test.php
 * Testes unitários para a transição atômica no webhook e checar_status contra concorrência/duplicações.
 */

declare(strict_types=1);

require_once __DIR__ . "/test_bootstrap.php";

$pdo = criarBancoTestes();
TestSpy::reset();

echo "==> Executando testes de Transição Atômica (Webhook & Checar Status)...\n";

$txid = 'NERD-TEST-WEBHOOK-01';
$pdo->prepare("
    INSERT INTO pedidos_vip (txid, nick, servidor, vip_id, vip_nome, cupom_codigo, valor, status, cupom_computado)
    VALUES (:txid, :nick, :servidor, :vip_id, :vip_nome, :cupom_codigo, :valor, :status, :cupom_computado)
")->execute([
    ':txid'            => $txid,
    ':nick'            => 'Alex',
    ':servidor'        => 'Survival',
    ':vip_id'          => 1,
    ':vip_nome'        => 'VIP Ouro',
    ':cupom_codigo'    => 'PROMO10',
    ':valor'           => 45.00,
    ':status'          => 'pendente',
    ':cupom_computado' => 0
]);

// Simulação de 2 workers concorrentes executando a query atômica
function simularWorkerWebhook(PDO $pdo, string $txid): bool {
    $up = $pdo->prepare("
        UPDATE pedidos_vip 
        SET status = 'pago', pago_em = datetime('now') 
        WHERE txid = :txid AND status <> 'pago'
    ");
    $up->execute([':txid' => $txid]);
    $transicaoOcorreu = ($up->rowCount() === 1);

    if ($transicaoOcorreu) {
        $stmt = $pdo->prepare("SELECT * FROM pedidos_vip WHERE txid = :txid LIMIT 1");
        $stmt->execute([':txid' => $txid]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        registrarUsoCupomSePago($pdo, $txid);
        enviarNotificacaoCompraDiscord($pedido['nick'], 'original', $pedido['servidor'], $pedido['vip_nome'], (float)$pedido['valor'], $txid);
        enviarEntregaVip($pedido, $pdo);
        return true;
    }

    return false;
}

// Worker 1 processa
$res1 = simularWorkerWebhook($pdo, $txid);
assert($res1 === true, "Falha: Worker 1 deveria ter realizado a transição atômica");
assert(count(TestSpy::$entregasVip) === 1, "Falha: deve haver exatamente 1 entrega de VIP");
assert(count(TestSpy::$discordNotificacoes) === 1, "Falha: deve haver exatamente 1 notificação no Discord");
echo "  [PASS] Worker 1 realizou a transição e disparou entrega e notificação.\n";

// Worker 2 tenta processar a mesma notificação duplicada/concorrente
$res2 = simularWorkerWebhook($pdo, $txid);
assert($res2 === false, "Falha: Worker 2 não deve realizar a transição (rowCount === 0)");
assert(count(TestSpy::$entregasVip) === 1, "Falha: Worker 2 não pode disparar entrega duplicada");
assert(count(TestSpy::$discordNotificacoes) === 1, "Falha: Worker 2 não pode disparar notificação duplicada");
echo "  [PASS] Worker 2 ignorou a transição duplicada sem gerar efeitos colaterais.\n";

echo "✔ Todos os testes de transição atômica passaram com sucesso!\n\n";

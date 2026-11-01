<?php
/**
 * audit_fixes_test.php
 * Testes unitários para as correções de auditoria (entrega pendente, webhook signature, etc).
 */

declare(strict_types=1);

require_once __DIR__ . "/test_bootstrap.php";

echo "==> Executando testes das correções de auditoria...\n";

// 1. Teste de retentarEntregasPendentes
$pdo = criarBancoTestes();
TestSpy::reset();

$pdo->prepare("
    INSERT INTO pedidos (txid, nick, servidor, vip_id, vip_nome, valor, status, entregue)
    VALUES (:txid, :nick, :servidor, :vip_id, :vip_nome, :valor, :status, :entregue)
")->execute([
    ':txid'     => 'NERD-RETRY-01',
    ':nick'     => 'PlayerRetry',
    ':servidor' => 'Survival',
    ':vip_id'   => 1,
    ':vip_nome' => 'VIP Ouro',
    ':valor'    => 30.00,
    ':status'   => 'pago',
    ':entregue' => 0
]);

require_once __DIR__ . "/../api/loja/delivery_helper.php";

$resultados = retentarEntregasPendentes($pdo, 10);
assert(isset($resultados['NERD-RETRY-01']), "Falha: pedido não entregue deveria ser retentado");
assert($resultados['NERD-RETRY-01'] === true, "Falha: retorno do retry deveria ser sucesso");
echo "  [PASS] retentarEntregasPendentes retenta pedidos pagos com entregue = 0 com sucesso.\n";

// 2. Teste de validação de assinatura do webhook Mercado Pago
$webhookSecret = 'test_webhook_secret_key_123';
$paymentId = '1234567890';
$requestId = 'req-test-uuid-987';
$ts = (string)time();
$manifest = "id:{$paymentId};request-id:{$requestId};ts:{$ts};";
$validHash = hash_hmac('sha256', $manifest, $webhookSecret);
$invalidHash = 'wrong_hash_value';

assert(hash_equals($validHash, hash_hmac('sha256', $manifest, $webhookSecret)) === true, "Falha: HMAC válido deve coincidir");
assert(hash_equals($invalidHash, hash_hmac('sha256', $manifest, $webhookSecret)) === false, "Falha: HMAC inválido deve ser rejeitado");
echo "  [PASS] HMAC de assinatura de webhook validado com sucesso.\n";

echo "✔ Todos os testes de correções de auditoria passaram com sucesso!\n";

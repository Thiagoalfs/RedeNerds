<?php
/**
 * cupom_idempotencia_test.php
 * Testes unitários para a idempotência de cômputo de cupons.
 */

declare(strict_types=1);

require_once __DIR__ . "/test_bootstrap.php";

$pdo = criarBancoTestes();

echo "==> Executando testes de Idempotência de Cupom...\n";

// Insere pedido pendente com cupom PROMO10
$txid = 'NERD-TEST-CUPOM-01';
$pdo->prepare("
    INSERT INTO pedidos (txid, nick, servidor, vip_id, vip_nome, cupom_codigo, valor, status, cupom_computado)
    VALUES (:txid, :nick, :servidor, :vip_id, :vip_nome, :cupom_codigo, :valor, :status, :cupom_computado)
")->execute([
    ':txid'            => $txid,
    ':nick'            => 'Steve',
    ':servidor'        => 'Survival',
    ':vip_id'          => 1,
    ':vip_nome'        => 'VIP Ouro',
    ':cupom_codigo'    => 'PROMO10',
    ':valor'           => 45.00,
    ':status'          => 'pendente',
    ':cupom_computado' => 0
]);

// 1. Tentar computar cupom em pedido pendente -> Deve retornar false e não alterar usos_total
$resPendente = registrarUsoCupomSePago($pdo, $txid);
assert($resPendente === false, "Falha: cupom não deve ser computado em pedido pendente");

$usos = (int)$pdo->query("SELECT usos_total FROM cupons WHERE codigo = 'PROMO10'")->fetchColumn();
assert($usos === 0, "Falha: usos_total deveria ser 0 após tentativa em pedido pendente");
echo "  [PASS] Pedido pendente não incrementa cupom.\n";

// 2. Muda status para 'pago' e executa registrarUsoCupomSePago
$pdo->prepare("UPDATE pedidos SET status = 'pago', pago_em = datetime('now') WHERE txid = :txid")
    ->execute([':txid' => $txid]);

$resPago1 = registrarUsoCupomSePago($pdo, $txid);
assert($resPago1 === true, "Falha: primeira chamada para pedido pago deveria retornar true");

$usos1 = (int)$pdo->query("SELECT usos_total FROM cupons WHERE codigo = 'PROMO10'")->fetchColumn();
assert($usos1 === 1, "Falha: usos_total deveria ser 1 após primeira confirmação de pagamento");

$computado = (int)$pdo->query("SELECT cupom_computado FROM pedidos WHERE txid = '{$txid}'")->fetchColumn();
assert($computado === 1, "Falha: cupom_computado deveria ser 1");
echo "  [PASS] Primeira confirmação de pagamento incrementa cupom exatamente 1 vez.\n";

// 3. Segunda chamada subsequente (simulando webhook duplicado ou polling concorrente)
$resPago2 = registrarUsoCupomSePago($pdo, $txid);
assert($resPago2 === false, "Falha: segunda chamada subsequente deve retornar false (idempotente)");

$usos2 = (int)$pdo->query("SELECT usos_total FROM cupons WHERE codigo = 'PROMO10'")->fetchColumn();
assert($usos2 === 1, "Falha: usos_total deve permanecer 1 e não ser duplicado");
echo "  [PASS] Segunda chamada subsequente é ignorada (idempotência garantida).\n";

echo "✔ Todos os testes de cupom idempotente passaram com sucesso!\n\n";

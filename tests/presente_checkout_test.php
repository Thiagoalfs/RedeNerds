<?php
/**
 * presente_checkout_test.php
 * Testes unitários para fluxo de compra como presente (destinatario).
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/loja/config_loja.php';

echo "==> Executando testes de compra como presente...\n";

class PresenteMockStmt extends PDOStatement {
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function execute(?array $params = null): bool { return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        return $this->rows[0] ?? false;
    }
    public function fetchColumn(int $column = 0): mixed {
        if (empty($this->rows[0])) return false;
        $vals = array_values($this->rows[0]);
        return $vals[$column] ?? false;
    }
}

class PresenteMockPDO extends PDO {
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (strpos($query, 'FROM vips') !== false) {
            return new PresenteMockStmt([['id' => 1, 'nome' => 'VIP Guerreiro', 'preco' => 29.90, 'servidor_id' => 1]]);
        }
        if (strpos($query, 'FROM servidores') !== false) {
            return new PresenteMockStmt([['id' => 1, 'servername' => 'Survival', 'nome' => 'survival', 'enabled' => 1]]);
        }
        return new PresenteMockStmt([]);
    }
}

$pdo = new PresenteMockPDO();

// 1. Presente válido: mantém nick do comprador e preserva destinatario
$res1 = prepararCheckoutLoja($pdo, [
    'nick' => 'DoadorPrincipal',
    'destinatario' => 'AmigoFeliz',
    'servidor' => 'Survival',
    'tipo_produto' => 'vip',
    'vip_id' => 1,
]);
assert($res1['ok'] === true, 'Checkout com destinatário válido deve passar.');
assert($res1['nick'] === 'DoadorPrincipal', 'nick deve permanecer o comprador principal.');
assert($res1['destinatario'] === 'AmigoFeliz', 'destinatario deve preservar o recebedor.');

// 2. Auto-presente (mesmo nick, case-insensitive) deve normalizar para null
$res2 = prepararCheckoutLoja($pdo, [
    'nick' => 'DoadorPrincipal',
    'destinatario' => 'doadorprincipal',
    'servidor' => 'Survival',
    'tipo_produto' => 'vip',
    'vip_id' => 1,
]);
assert($res2['ok'] === true, 'Auto-presente deve ser aceito.');
assert($res2['destinatario'] === null, 'Auto-presente deve normalizar destinatario para null.');

// 3. Destinatário inválido deve falhar com status 400
$res3 = prepararCheckoutLoja($pdo, [
    'nick' => 'DoadorPrincipal',
    'destinatario' => 'nick invalido!',
    'servidor' => 'Survival',
    'tipo_produto' => 'vip',
    'vip_id' => 1,
]);
assert($res3['ok'] === false && ($res3['status'] ?? 0) === 400, 'Destinatário inválido deve retornar erro 400.');

// 4. Extração de metadata do Mercado Pago preserva destinatario
$ext = extrairDadosDescricaoPagamento('Rede Nerd - VIP (Survival) - Jogador: DoadorPrincipal', [
    'metadata' => [
        'nick' => 'DoadorPrincipal',
        'destinatario' => 'AmigoFeliz',
        'servidor' => 'Survival',
        'item_nome' => 'VIP Guerreiro',
        'tipo_produto' => 'vip',
        'quantidade' => 1,
    ],
]);
assert($ext['nick'] === 'DoadorPrincipal' && $ext['destinatario'] === 'AmigoFeliz', 'Metadata deve preservar nick e destinatario.');

echo "   [OK] Todos os testes de compra como presente passaram!\n";

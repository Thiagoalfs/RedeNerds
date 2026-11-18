<?php
/**
 * expiracao_pedidos_test.php
 * Teste unitário para expirarPedidosPendentes() sem dependência de MySQL externo.
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/loja/expiracao_helper.php';

echo "==> Executando testes de expiração de pedidos pendentes...\n";

class ExpiracaoMockStmt {
    public string $sql;
    public array $executedParams = [];
    private int $affected;

    public function __construct(string $sql, int $affected) {
        $this->sql = $sql;
        $this->affected = $affected;
    }

    public function execute(array $params = []): bool {
        $this->executedParams = $params;
        return true;
    }

    public function rowCount(): int {
        return $this->affected;
    }
}

class ExpiracaoMockPDO extends PDO {
    public ?ExpiracaoMockStmt $lastStmt = null;
    private int $affected;

    public function __construct(int $affected = 3) {
        $this->affected = $affected;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->lastStmt = new ExpiracaoMockStmt($query, $this->affected);
        // Retorna wrapper compatível via anonymous class ou guardamos referência
        return new class($this->lastStmt) extends PDOStatement {
            private ExpiracaoMockStmt $inner;
            public function __construct(ExpiracaoMockStmt $inner) {
                $this->inner = $inner;
            }
            public function execute(?array $params = null): bool {
                return $this->inner->execute($params ?? []);
            }
            public function rowCount(): int {
                return $this->inner->rowCount();
            }
        };
    }
}

$pdo = new ExpiracaoMockPDO(4);
$expirados = expirarPedidosPendentes($pdo);

assert($expirados === 4, "Deve retornar a contagem de pedidos expirados (esperado 4, obtido {$expirados}).");
assert($pdo->lastStmt !== null, "Deve preparar a query de expiração.");
assert($pdo->lastStmt->executedParams === [':lim_cp' => 7200, ':lim_padrao' => 900], "Os limites padrão devem ser 900s (15 min) e 7200s (2h).");
assert(strpos($pdo->lastStmt->sql, "status = 'expirado'") !== false, "A query deve atualizar status para 'expirado'.");
assert(strpos($pdo->lastStmt->sql, "status = 'pendente'") !== false, "A query deve filtrar apenas status = 'pendente'.");

// Teste com limites customizados
$pdoCustom = new ExpiracaoMockPDO(0);
$expCustom = expirarPedidosPendentes($pdoCustom, 600, 3600);
assert($expCustom === 0, "Deve retornar 0 quando nenhum pedido expirou.");
assert($pdoCustom->lastStmt->executedParams === [':lim_cp' => 3600, ':lim_padrao' => 600], "Deve repassar limites customizados.");

echo "   [OK] Todos os testes de expiração de pedidos passaram!\n";

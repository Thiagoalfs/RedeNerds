<?php
/**
 * test_bootstrap.php
 * Ambiente de testes 100% isolado utilizando MockPDO em memória.
 * NUNCA carrega o config.php de produção nem conecta ao MySQL externo.
 */

declare(strict_types=1);

if ((int)ini_get('zend.assertions') !== 1) {
    fwrite(STDERR, "ERRO: Os testes requerem que zend.assertions esteja ativado (= 1).\n");
    exit(1);
}

// Configurações e constantes simuladas para teste
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://test.redenerds.com.br');
}
if (!defined('MERCADO_PAGO_ACCESS_TOKEN')) {
    define('MERCADO_PAGO_ACCESS_TOKEN', 'TEST-TOKEN-123456');
}
if (!defined('MERCADO_PAGO_WEBHOOK_SECRET')) {
    define('MERCADO_PAGO_WEBHOOK_SECRET', 'test_secret_webhook');
}

// Armazenamento de chamadas de stubs
class TestSpy {
    public static array $entregasVip = [];
    public static array $discordNotificacoes = [];

    public static function reset(): void {
        self::$entregasVip = [];
        self::$discordNotificacoes = [];
    }
}

// Stubs para funções de entrega e notificação
if (!function_exists('enviarEntregaVip')) {
    function enviarEntregaVip($pedido, $pdo = null): array {
        TestSpy::$entregasVip[] = $pedido;
        return ['success' => true, 'http_code' => 200, 'response' => 'ok', 'error' => null];
    }
}

if (!function_exists('enviarNotificacaoCompraDiscord')) {
    function enviarNotificacaoCompraDiscord($nick, $tipoConta, $servidor, $vipNome, $valor, $txid, $cor = '#7DB9DF', $metodo = 'pix', $parcelas = 1, $total = null, $cupom = null, $desconto = 0.0): bool {
        TestSpy::$discordNotificacoes[] = [
            'nick' => $nick,
            'txid' => $txid,
            'valor' => $valor,
            'cupom' => $cupom
        ];
        return true;
    }
}

// Implementação pura de MockPDO para testes em memória
class MockPDOStatement extends PDOStatement {
    private MockPDO $db;
    private string $sql;
    private array $results = [];
    private int $affectedRows = 0;
    private int $cursor = 0;

    public function __construct(MockPDO $db, string $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(?array $params = null): bool {
        $res = $this->db->handleQuery($this->sql, $params ?? []);
        $this->results = $res['rows'] ?? [];
        $this->affectedRows = $res['affected'] ?? 0;
        $this->cursor = 0;
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        if ($this->cursor < count($this->results)) {
            return $this->results[$this->cursor++];
        }
        return false;
    }

    public function fetchColumn(int $column = 0): mixed {
        $row = $this->fetch();
        if ($row === false) return false;
        $values = array_values($row);
        return $values[$column] ?? false;
    }

    public function rowCount(): int {
        return $this->affectedRows;
    }
}

class MockPDO extends PDO {
    public array $pedidos = [];
    public array $pedidos_vip = []; // Alias retrocompatível
    public array $cupons = [];
    public array $rate_limits_loja = [];
    private bool $inTransaction = false;
    private array $snapshot = [];

    public function __construct() {
        $this->pedidos_vip =& $this->pedidos;
        $this->cupons['PROMO10'] = [
            'id' => 1,
            'codigo' => 'PROMO10',
            'porcentagem_desconto' => 10.0,
            'expira_em' => date('Y-m-d H:i:s', time() + 86400),
            'ativo' => 1,
            'usos_total' => 0,
            'servidor_id' => null
        ];
    }

    public function beginTransaction(): bool {
        $this->inTransaction = true;
        $this->snapshot = [
            'pedidos' => $this->pedidos,
            'cupons' => $this->cupons,
            'rate_limits_loja' => $this->rate_limits_loja
        ];
        return true;
    }

    public function commit(): bool {
        $this->inTransaction = false;
        $this->snapshot = [];
        return true;
    }

    public function rollBack(): bool {
        if ($this->inTransaction && !empty($this->snapshot)) {
            $this->pedidos = $this->snapshot['pedidos'];
            $this->pedidos_vip =& $this->pedidos;
            $this->cupons = $this->snapshot['cupons'];
            $this->rate_limits_loja = $this->snapshot['rate_limits_loja'];
        }
        $this->inTransaction = false;
        $this->snapshot = [];
        return true;
    }

    public function inTransaction(): bool {
        return $this->inTransaction;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false {
        return new MockPDOStatement($this, $query);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $stmt = new MockPDOStatement($this, $query);
        $stmt->execute([]);
        return $stmt;
    }

    public function exec(string $statement): int|false {
        $res = $this->handleQuery($statement, []);
        return $res['affected'] ?? 0;
    }

    public function handleQuery(string $sql, array $params): array {
        $normalized = preg_replace('/\s+/', ' ', trim($sql));

        // SELECT ... FROM pedidos / pedidos_vip WHERE txid = ...
        if (stripos($normalized, 'SELECT') === 0 && (stripos($normalized, 'FROM pedidos') !== false || stripos($normalized, 'FROM pedidos_vip') !== false)) {
            $txid = $params[':txid'] ?? ($params[0] ?? '');
            if (empty($txid) && preg_match("/txid\s*=\s*'([^']+)'/i", $normalized, $m)) {
                $txid = $m[1];
            }
            if (!empty($txid) && isset($this->pedidos[$txid])) {
                $row = $this->pedidos[$txid];
                if (preg_match('/SELECT\s+cupom_computado\s+FROM/i', $normalized)) {
                    return ['rows' => [['cupom_computado' => $row['cupom_computado']]], 'affected' => 1];
                }
                return ['rows' => [$row], 'affected' => 1];
            }
            return ['rows' => [], 'affected' => 0];
        }

        // SELECT usos_total FROM cupons WHERE codigo = ...
        if (stripos($normalized, 'SELECT') === 0 && stripos($normalized, 'FROM cupons') !== false) {
            $codigo = $params[':codigo'] ?? ($params[0] ?? '');
            if (empty($codigo) && preg_match("/codigo\s*=\s*'([^']+)'/i", $normalized, $m)) {
                $codigo = $m[1];
            }
            if (!empty($codigo) && isset($this->cupons[$codigo])) {
                $row = $this->cupons[$codigo];
                if (preg_match('/SELECT\s+usos_total\s+FROM/i', $normalized)) {
                    return ['rows' => [['usos_total' => $row['usos_total']]], 'affected' => 1];
                }
                return ['rows' => [$row], 'affected' => 1];
            }
            return ['rows' => [], 'affected' => 0];
        }

        // INSERT INTO pedidos / pedidos_vip
        if (stripos($normalized, 'INSERT INTO pedidos') === 0 || stripos($normalized, 'INSERT INTO pedidos_vip') === 0) {
            $txid = $params[':txid'] ?? ('NERD-' . uniqid());
            $this->pedidos[$txid] = [
                'id' => count($this->pedidos) + 1,
                'txid' => $txid,
                'nick' => $params[':nick'] ?? 'TestPlayer',
                'servidor' => $params[':servidor'] ?? 'Survival',
                'vip_id' => $params[':vip_id'] ?? 1,
                'vip_nome' => $params[':vip_nome'] ?? 'VIP Ouro',
                'cupom_codigo' => $params[':cupom_codigo'] ?? null,
                'valor' => $params[':valor'] ?? 50.00,
                'status' => $params[':status'] ?? 'pendente',
                'cupom_computado' => (int)($params[':cupom_computado'] ?? 0),
                'pago_em' => $params[':pago_em'] ?? null
            ];
            return ['rows' => [], 'affected' => 1];
        }

        // UPDATE pedidos / pedidos_vip SET cupom_computado = 1 WHERE txid = :txid AND status = 'pago' AND ...
        if (stripos($normalized, 'UPDATE pedidos SET cupom_computado = 1') !== false || stripos($normalized, 'UPDATE pedidos_vip SET cupom_computado = 1') !== false) {
            $txid = $params[':txid'] ?? '';
            if (!empty($txid) && isset($this->pedidos[$txid])) {
                if ($this->pedidos[$txid]['status'] === 'pago' && empty($this->pedidos[$txid]['cupom_computado'])) {
                    $this->pedidos[$txid]['cupom_computado'] = 1;
                    return ['rows' => [], 'affected' => 1];
                }
            }
            return ['rows' => [], 'affected' => 0];
        }

        // UPDATE pedidos / pedidos_vip SET status = 'pago' ... WHERE txid = :txid AND status <> 'pago'
        if ((stripos($normalized, 'UPDATE pedidos') === 0 || stripos($normalized, 'UPDATE pedidos_vip') === 0) && stripos($normalized, "status = 'pago'") !== false && stripos($normalized, "status <> 'pago'") !== false) {
            $txid = $params[':txid'] ?? '';
            if (!empty($txid) && isset($this->pedidos[$txid])) {
                if ($this->pedidos[$txid]['status'] !== 'pago') {
                    $this->pedidos[$txid]['status'] = 'pago';
                    $this->pedidos[$txid]['pago_em'] = date('Y-m-d H:i:s');
                    if (isset($params[':mp_id'])) {
                        $this->pedidos[$txid]['mp_payment_id'] = $params[':mp_id'];
                    }
                    return ['rows' => [], 'affected' => 1];
                }
            }
            return ['rows' => [], 'affected' => 0];
        }

        // UPDATE pedidos / pedidos_vip SET status = 'pago'
        if ((stripos($normalized, 'UPDATE pedidos') === 0 || stripos($normalized, 'UPDATE pedidos_vip') === 0) && stripos($normalized, "status = 'pago'") !== false) {
            $txid = $params[':txid'] ?? '';
            if (!empty($txid) && isset($this->pedidos[$txid])) {
                $this->pedidos[$txid]['status'] = 'pago';
                $this->pedidos[$txid]['pago_em'] = date('Y-m-d H:i:s');
                return ['rows' => [], 'affected' => 1];
            }
            return ['rows' => [], 'affected' => 0];
        }

        // UPDATE cupons SET usos_total = usos_total + 1 WHERE codigo = :codigo
        if (stripos($normalized, 'UPDATE cupons SET usos_total = usos_total + 1') !== false) {
            $codigo = $params[':codigo'] ?? '';
            if (!empty($codigo) && isset($this->cupons[$codigo])) {
                $this->cupons[$codigo]['usos_total']++;
                return ['rows' => [], 'affected' => 1];
            }
            return ['rows' => [], 'affected' => 0];
        }

        return ['rows' => [], 'affected' => 0];
    }
}

function criarBancoTestes(): PDO {
    return new MockPDO();
}

// Carrega os helpers da aplicação
require_once __DIR__ . "/../api/loja/ip_helper.php";
require_once __DIR__ . "/../api/loja/geo_helper.php";
require_once __DIR__ . "/../api/loja/cupom_helper.php";
require_once __DIR__ . "/../api/loja/config_loja.php";

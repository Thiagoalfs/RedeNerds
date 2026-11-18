<?php
/**
 * ranking_doadores_test.php
 * Testes unitários para o ranking de maiores doadores da loja.
 */

declare(strict_types=1);

if ((int)ini_get('zend.assertions') !== 1) {
    fwrite(STDERR, "ERRO: Os testes requerem que zend.assertions esteja ativado (= 1).\n");
    exit(1);
}

require_once __DIR__ . '/../api/loja/ranking_helper.php';

echo "==> Executando testes do Ranking de Maiores Doadores...\n";

class RankingMockPDO extends PDO
{
    public array $pedidos = [];
    public array $equipe = [];

    public function __construct() {}

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $pdo = $this;
        return new class($pdo, $query) extends PDOStatement {
            private RankingMockPDO $db;
            private string $sql;

            public function __construct(RankingMockPDO $db, string $sql)
            {
                $this->db = $db;
                $this->sql = $sql;
            }

            public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
            {
                if (stripos($this->sql, 'FROM equipe') !== false) {
                    return array_column($this->db->equipe, 'nick');
                }

                // Simula exatamente a query SQL: WHERE status = 'pago', GROUP BY LOWER(TRIM(nick)), ORDER BY total DESC
                $grupos = [];
                foreach ($this->db->pedidos as $p) {
                    if (($p['status'] ?? '') !== 'pago') {
                        continue;
                    }
                    $nick = trim((string)($p['nick'] ?? ''));
                    if ($nick === '') {
                        continue;
                    }
                    $key = mb_strtolower($nick, 'UTF-8');
                    if (!isset($grupos[$key])) {
                        $grupos[$key] = [
                            'nick_key' => $key,
                            'nick' => $nick,
                            'total_doado' => 0.0,
                            'max_id' => (int)($p['id'] ?? 0),
                        ];
                    }
                    $grupos[$key]['total_doado'] += (float)$p['valor'];
                    $grupos[$key]['max_id'] = max($grupos[$key]['max_id'], (int)($p['id'] ?? 0));
                }

                $lista = array_values($grupos);
                usort($lista, static function ($a, $b) {
                    if ($b['total_doado'] !== $a['total_doado']) {
                        return $b['total_doado'] <=> $a['total_doado'];
                    }
                    return $a['max_id'] <=> $b['max_id'];
                });
                return $lista;
            }
        };
    }
}

$mock = new RankingMockPDO();
$mock->equipe = [
    ['nick' => 'safiroko'],
    ['nick' => 'Pern4Long4'],
];
$mock->pedidos = [
    // Equipe (não deve entrar mesmo pago)
    ['id' => 1, 'nick' => 'Safiroko', 'valor' => 9999.00, 'status' => 'pago'],
    ['id' => 2, 'nick' => 'pern4long4', 'valor' => 500.00, 'status' => 'pago'],

    // Pendentes / Cancelados / Expirados / Recusados (não devem entrar)
    ['id' => 3, 'nick' => 'Caloteiro', 'valor' => 1000.00, 'status' => 'pendente'],
    ['id' => 4, 'nick' => 'Cancelado', 'valor' => 800.00, 'status' => 'cancelado'],
    ['id' => 5, 'nick' => 'Expirado', 'valor' => 700.00, 'status' => 'expirado'],
    ['id' => 6, 'nick' => 'Recusado', 'valor' => 600.00, 'status' => 'recusado'],

    // Jogadores válidos (com soma e case-insensitivity)
    ['id' => 7, 'nick' => 'Jogador1', 'valor' => 100.00, 'status' => 'pago'],
    ['id' => 8, 'nick' => 'jogador1', 'valor' => 150.50, 'status' => 'pago'],
    ['id' => 9, 'nick' => 'Jogador1', 'valor' => 40.00, 'status' => 'pendente'], // pendente não soma
    ['id' => 10, 'nick' => 'Jogador2', 'valor' => 200.00, 'status' => 'pago'],
    ['id' => 11, 'nick' => 'Jogador3', 'valor' => 150.00, 'status' => 'pago'],
    ['id' => 12, 'nick' => 'Jogador4', 'valor' => 80.00, 'status' => 'pago'],
    ['id' => 13, 'nick' => 'Jogador5', 'valor' => 50.00, 'status' => 'pago'],
    ['id' => 14, 'nick' => 'Jogador6', 'valor' => 10.00, 'status' => 'pago'], // 6º lugar fica fora do Top 5
];

$ranking = calcularRankingDoadores($mock, 5);

assert(count($ranking) === 5, 'Deve retornar exatamente 5 posições no máximo.');
assert($ranking[0]['posicao'] === 1 && strtolower($ranking[0]['nick']) === 'jogador1' && $ranking[0]['total'] === 250.50, '1º lugar deve somar apenas pedidos pagos.');
assert($ranking[1]['posicao'] === 2 && $ranking[1]['nick'] === 'Jogador2' && $ranking[1]['total'] === 200.00, '2º lugar incorreto.');
assert($ranking[2]['posicao'] === 3 && $ranking[2]['nick'] === 'Jogador3' && $ranking[2]['total'] === 150.00, '3º lugar incorreto.');
assert($ranking[3]['posicao'] === 4 && $ranking[3]['nick'] === 'Jogador4' && $ranking[3]['total'] === 80.00, '4º lugar incorreto.');
assert($ranking[4]['posicao'] === 5 && $ranking[4]['nick'] === 'Jogador5' && $ranking[4]['total'] === 50.00, '5º lugar incorreto.');

foreach ($ranking as $r) {
    assert(!in_array(strtolower($r['nick']), ['safiroko', 'pern4long4'], true), 'Membro da equipe não pode aparecer no ranking.');
}

echo "   [OK] Todos os testes de ranking de doadores passaram!\n";

<?php
/**
 * concorrencia_test.php
 * Testes unitários e estruturais para optimistic locking (concorrência) em criar/editar/toggle do painel admin.
 */

declare(strict_types=1);

if ((int)ini_get('zend.assertions') !== 1) {
    fwrite(STDERR, "ERRO: Os testes requerem que zend.assertions esteja ativado (= 1).\n");
    exit(1);
}

require_once __DIR__ . '/../admin/includes/concorrencia.php';

echo "==> Executando testes de Concorrência (Criar / Editar / Toggle)...\n";

class ConcorrenciaMockStmt extends PDOStatement
{
    private array $rows = [];
    private int $affected = 0;

    public function __construct(array $rows = [], int $affected = 0)
    {
        $this->rows = $rows;
        $this->affected = $affected;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->rows[0] ?? false;
    }

    public function rowCount(): int
    {
        return $this->affected;
    }
}

class ConcorrenciaMockPDO extends PDO
{
    public array $tables = [];
    public bool $simularRaceToggle = false;
    private bool $tx = false;

    public function __construct() {}

    public function beginTransaction(): bool { $this->tx = true; return true; }
    public function commit(): bool { $this->tx = false; return true; }
    public function rollBack(): bool { $this->tx = false; return true; }
    public function inTransaction(): bool { return $this->tx; }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $pdoRef = $this;
        return new class($pdoRef, $query) extends PDOStatement {
            private ConcorrenciaMockPDO $db;
            private string $sql;
            private array $resultRows = [];
            private int $affected = 0;

            public function __construct(ConcorrenciaMockPDO $db, string $sql)
            {
                $this->db = $db;
                $this->sql = $sql;
            }

            public function execute(?array $params = null): bool
            {
                $params = $params ?? [];
                if (preg_match('/SELECT\s+.*?\s+FROM\s+`?(\w+)`?\s+WHERE\s+id\s*=\s*:id/is', $this->sql, $m)) {
                    $tabela = $m[1];
                    $id = (int)($params[':id'] ?? 0);
                    $row = $this->db->tables[$tabela][$id] ?? null;
                    $this->resultRows = $row ? [$row] : [];
                    return true;
                }
                if (preg_match('/UPDATE\s+`?(\w+)`?\s+SET\s+`?(\w+)`?\s*=\s*:novo\s+WHERE\s+id\s*=\s*:id\s+AND\s+`?(\w+)`?\s*=\s*:atual/is', $this->sql, $m)) {
                    $tabela = $m[1];
                    $col = $m[2];
                    $id = (int)($params[':id'] ?? 0);
                    $novo = $params[':novo'];
                    $atual = $params[':atual'];

                    if ($this->db->simularRaceToggle) {
                        $this->affected = 0;
                        return true;
                    }

                    if (isset($this->db->tables[$tabela][$id]) && (string)$this->db->tables[$tabela][$id][$col] === (string)$atual) {
                        $this->db->tables[$tabela][$id][$col] = $novo;
                        $this->affected = 1;
                    } else {
                        $this->affected = 0;
                    }
                    return true;
                }
                return true;
            }

            public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
            {
                return $this->resultRows[0] ?? false;
            }

            public function rowCount(): int
            {
                return $this->affected;
            }
        };
    }
}

// 1. Teste: Bug de cupons (_status_info array) não deve quebrar nem mudar o hash
$cupomDb = [
    'id' => 10,
    'codigo' => 'NERD10',
    'porcentagem_desconto' => '10.00',
    'servidor_id' => null,
    'expira_em' => '2026-12-31 23:59:00',
    'ativo' => 1,
    'usos_total' => 5,
    'criado_em' => '2026-11-01 12:00:00',
];
$cupomComUi = $cupomDb;
$cupomComUi['_status_info'] = ['label' => 'Ativo', 'badge' => 'badge-status ativo', 'ativo_real' => true];
$cupomComUi['_versao_hash'] = 'ignorado';

$hashDb = gerarHashRegistro($cupomDb);
$hashUi = gerarHashRegistro($cupomComUi);
assert($hashDb === $hashUi, "Hash com array _status_info deve ser idêntico ao hash puro do banco!");

// 2. Teste: Fluxo completo Criar -> Editar -> Toggle -> Editar no MockPDO
$mockPdo = new ConcorrenciaMockPDO();
$mockPdo->tables['cupons'][10] = $cupomDb;

// 2a. Editar com hash válido -> OK
$resOk = verificarConcorrenciaEdicao($mockPdo, 'cupons', 10, $hashUi);
assert($resOk['ok'] === true, "Edição com hash válido deve ser aceita.");

// 2b. Editar com hash antigo após alteração concorrente -> 409 Conflict
$mockPdo->tables['cupons'][10]['porcentagem_desconto'] = '15.00';
$resConflict = verificarConcorrenciaEdicao($mockPdo, 'cupons', 10, $hashUi);
assert($resConflict['ok'] === false && $resConflict['status'] === 409, "Edição com hash obsoleto deve retornar 409.");

// 2c. Editar registro inexistente -> 404
$res404 = verificarConcorrenciaEdicao($mockPdo, 'cupons', 999, $hashUi);
assert($res404['ok'] === false && $res404['status'] === 404, "Registro deletado deve retornar 404.");

// 3. Teste: Toggle atômico + atualização de hash para edição subsequente
$hashAntesToggle = obterHashAtualRegistro($mockPdo, 'cupons', 10);
$toggleOk = executarToggleAtomico($mockPdo, 'cupons', 'ativo', 10, 'codigo', 1);
assert($toggleOk['ok'] === true && $toggleOk['novo'] === 0, "Toggle com estado esperado=1 deve mudar para 0.");

$hashDepoisToggle = obterHashAtualRegistro($mockPdo, 'cupons', 10);
assert($hashDepoisToggle !== '' && $hashDepoisToggle !== $hashAntesToggle, "Hash deve mudar após toggle.");

// Edição com hash antigo (antes do toggle) deve falhar; com o novo hash retornado pelo toggle deve passar
$resAposToggleAntigo = verificarConcorrenciaEdicao($mockPdo, 'cupons', 10, $hashAntesToggle);
assert($resAposToggleAntigo['ok'] === false && $resAposToggleAntigo['status'] === 409, "Hash pré-toggle deve dar 409.");
$resAposToggleNovo = verificarConcorrenciaEdicao($mockPdo, 'cupons', 10, $hashDepoisToggle);
assert($resAposToggleNovo['ok'] === true, "Hash pós-toggle retornado pela API deve permitir editar sem recarregar.");

// 3b. Toggle com estado_esperado desatualizado -> 409
$toggleStale = executarToggleAtomico($mockPdo, 'cupons', 'ativo', 10, 'codigo', 1);
assert($toggleStale['ok'] === false && $toggleStale['status'] === 409, "Toggle com estado_esperado errado deve dar 409.");

// 3c. Simulação de race condition entre SELECT e UPDATE do toggle -> 409
$mockPdo->simularRaceToggle = true;
$toggleRace = executarToggleAtomico($mockPdo, 'cupons', 'ativo', 10, 'codigo', 0);
assert($toggleRace['ok'] === false && $toggleRace['status'] === 409, "Race condition no UPDATE do toggle deve dar 409.");
$mockPdo->simularRaceToggle = false;

// 4. Teste: Todos os módulos mapeados em camposConcorrenciaTabela filtram campos extras de JOIN
foreach (['servidores', 'equipe', 'equipe_cargos', 'wiki_artigos', 'wiki_categorias', 'usuarios', 'novidades'] as $tabela) {
    $campos = camposConcorrenciaTabela($tabela);
    assert(is_array($campos) && count($campos) > 0, "Mapa da tabela {$tabela} deve existir.");
    $base = array_fill_keys($campos, 'valor');
    $comExtra = $base + ['campo_join_extra' => 'nao_deve_afetar_hash'];
    assert(
        gerarHashRegistro($base, [], $tabela) === gerarHashRegistro($comExtra, [], $tabela),
        "Tabela {$tabela} deve ignorar campos extras de JOIN."
    );
}

// 5. Teste estrutural: garantir que todos os criar/editar/toggle possuem os hooks de concorrência
$root = dirname(__DIR__);
$modulosCrud = ['chaves', 'cupons', 'equipe', 'noticias', 'parceiros', 'servidores', 'vips', 'wiki'];
foreach ($modulosCrud as $mod) {
    $criarSrc = file_get_contents("{$root}/admin/api/{$mod}/criar.php");
    $editarSrc = file_get_contents("{$root}/admin/api/{$mod}/editar.php");
    assert(strpos($criarSrc, '_versao_hash') !== false, "admin/api/{$mod}/criar.php deve retornar _versao_hash.");
    assert(
        strpos($editarSrc, 'exigirConcorrenciaEdicao') !== false || strpos($editarSrc, 'verificarConcorrenciaEdicao') !== false,
        "admin/api/{$mod}/editar.php deve validar concorrência."
    );
}
foreach (['chaves', 'cupons', 'parceiros', 'servidores', 'vips', 'wiki'] as $mod) {
    $toggleSrc = file_get_contents("{$root}/admin/api/{$mod}/toggle.php");
    assert(strpos($toggleSrc, 'executarToggleAtomico') !== false, "admin/api/{$mod}/toggle.php deve usar executarToggleAtomico.");
    assert(strpos($toggleSrc, 'obterHashAtualRegistro') !== false, "admin/api/{$mod}/toggle.php deve retornar _versao_hash atualizado.");
}

echo "   [OK] Todos os testes de concorrência passaram!\n";

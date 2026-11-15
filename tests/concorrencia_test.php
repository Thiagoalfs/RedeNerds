<?php
require_once __DIR__ . '/../admin/includes/concorrencia.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE cupons (id INTEGER PRIMARY KEY, codigo TEXT, porcentagem INTEGER, ativo INTEGER)');
$pdo->exec("INSERT INTO cupons (id, codigo, porcentagem, ativo) VALUES (1, 'NERD10', 10, 1)");

$row = $pdo->query('SELECT * FROM cupons WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
$hashInicial = gerarHashRegistro($row);
assert(strlen($hashInicial) === 24, 'Hash deve ter 24 caracteres');
assert($hashInicial === gerarHashRegistro(['ativo' => '1', 'porcentagem' => '10', 'codigo' => 'NERD10', 'id' => '1']), 'Hash deve ser determinístico');

// 1. Edição sem conflito passa
$ok = verificarConcorrenciaEdicao($pdo, 'cupons', 1, $hashInicial);
assert($ok['ok'] === true, 'Edição com hash atual deve passar');

// 2. Outro admin altera o registro -> hash antigo deve falhar com 409
$pdo->exec("UPDATE cupons SET porcentagem = 20 WHERE id = 1");
$conflito = verificarConcorrenciaEdicao($pdo, 'cupons', 1, $hashInicial);
assert($conflito['ok'] === false && $conflito['status'] === 409, 'Hash desatualizado deve retornar conflito 409');

// 3. Outro admin deleta o registro -> deve falhar com 404
$pdo->exec("DELETE FROM cupons WHERE id = 99");
$ausente = verificarConcorrenciaEdicao($pdo, 'cupons', 99, $hashInicial);
assert($ausente['ok'] === false && $ausente['status'] === 404, 'Registro ausente deve retornar 404');

// 4. Toggle com estado_esperado desatualizado falha com 409; com estado atual funciona
$toggleStale = executarToggleAtomico($pdo, 'cupons', 'ativo', 1, 'codigo', 0);
assert($toggleStale['ok'] === false && $toggleStale['status'] === 409, 'Toggle com estado desatualizado deve retornar 409');

$toggleOk = executarToggleAtomico($pdo, 'cupons', 'ativo', 1, 'codigo', 1);
assert($toggleOk['ok'] === true && $toggleOk['novo'] === 0, 'Toggle com estado esperado correto deve alternar para 0');

echo "concorrencia_test.php OK\n";

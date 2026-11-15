<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/includes/permissoes.php';
require_once __DIR__ . '/../admin/includes/logger.php';

// 1. Permissões de acesso à aba Logs
assert(normalizarCargoAdmin('Fundador') === 'fundador');
assert(normalizarCargoAdmin('Co-Fundador') === 'fundador');
assert(normalizarCargoAdmin('Diretor') === 'diretor');
assert(usuarioPodeAcessar('logs', 'fundador') === true, 'Fundador/Co-Fundador deve acessar logs');
assert(usuarioPodeAcessar('logs', 'diretor') === true, 'Diretor deve acessar logs');
assert(usuarioPodeAcessar('logs', 'coordenador') === false, 'Coordenador não pode acessar logs');
assert(usuarioPodeAcessar('logs', 'desenvolvedor') === false, 'Desenvolvedor não pode acessar logs');
assert(usuarioPodeAcessar('logs', null) === false, 'Cargo nulo não pode acessar logs');

// 2. Gravação em banco e filtragem de campos sensíveis
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("
    CREATE TABLE admin_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        usuario_id INTEGER NULL,
        usuario_nome TEXT NOT NULL,
        cargo TEXT NULL,
        acao TEXT NOT NULL,
        modulo TEXT NOT NULL,
        alvo_id INTEGER NULL,
        descricao TEXT NOT NULL,
        detalhes TEXT NULL,
        ip TEXT NOT NULL,
        criado_em TEXT DEFAULT CURRENT_TIMESTAMP
    )
");

$_SERVER['REMOTE_ADDR'] = '187.10.20.30';
$_SESSION = [
    'usuario_id' => 7,
    'usuario_nome' => 'Thiago',
    'cargo' => 'Fundador',
];

registrarLogAdmin($pdo, 'editar', 'usuarios', 'Editou usuário teste', 12, [
    'email' => 'teste@redenerds.com.br',
    'senha' => 'segredo123',
    'password' => 'abc',
    'reset_token' => 'xyz',
]);

$row = $pdo->query("SELECT * FROM admin_logs WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
assert($row !== false, 'Log deve ser inserido');
assert((int)$row['usuario_id'] === 7);
assert($row['usuario_nome'] === 'Thiago');
assert($row['cargo'] === 'Fundador');
assert($row['acao'] === 'editar');
assert($row['modulo'] === 'usuarios');
assert((int)$row['alvo_id'] === 12);
assert($row['ip'] === '187.10.20.30');

$det = json_decode((string)$row['detalhes'], true);
assert($det === ['email' => 'teste@redenerds.com.br'], 'Chaves sensíveis devem ser removidas de detalhes');

echo "admin_logs_test.php OK\n";

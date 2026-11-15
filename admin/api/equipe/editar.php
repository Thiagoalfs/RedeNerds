<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "ID de membro inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

$nick = trim($_POST['nick'] ?? '');
$cargo = trim($_POST['cargo'] ?? '');

if (empty($nick)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "O campo Nick é obrigatório."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^[A-Za-z0-9_]{2,16}$/', $nick)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Nick inválido. Use entre 2 e 16 caracteres alfanuméricos."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($cargo)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Selecione um cargo."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Conexão com o banco de dados indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM equipe WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $membro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$membro) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Membro não encontrado."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $atorNivel = $_SESSION['cargo_nivel'] ?? null;
    if (!coordenadorPodeGerenciarCargo($atorNivel, $membro['cargo']) || !coordenadorPodeGerenciarCargo($atorNivel, $cargo)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Seu cargo não pode alterar membros ou atribuir cargos da Diretoria/Fundadores."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $versaoHash = trim($_POST['_versao_hash'] ?? '');
    $pdo->beginTransaction();
    exigirConcorrenciaEdicao($pdo, 'equipe', $id, $versaoHash, [], true);

    $upd = $pdo->prepare("UPDATE equipe SET nick = :nick, cargo = :cargo WHERE id = :id");
    $upd->execute([':nick' => $nick, ':cargo' => $cargo, ':id' => $id]);

    // Mantém o nome de usuário sincronizado caso o nick do membro seja alterado
    $pdo->prepare("UPDATE usuarios SET usuario = :nick WHERE equipe_id = :id")
        ->execute([':nick' => $nick, ':id' => $id]);
    $pdo->commit();
    registrarLogAdmin($pdo, 'editar', 'equipe', "Editou membro '{$nick}' ({$cargo}, #{$id})", $id);

    require_once __DIR__ . "/../../../api/cache_helper.php";
    invalidarCache('equipe');

    $busca = $pdo->prepare("SELECT * FROM equipe WHERE id = :id LIMIT 1");
    $busca->execute([':id' => $id]);
    $membroAtualizado = $busca->fetch(PDO::FETCH_ASSOC);
    if ($membroAtualizado) {
        $membroAtualizado['_versao_hash'] = gerarHashRegistro($membroAtualizado, [], 'equipe');
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => true,
        "mensagem" => "Membro '{$nick}' atualizado com sucesso!",
        "membro" => $membroAtualizado
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("Erro ao atualizar membro da equipe: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Erro ao atualizar no banco de dados."], JSON_UNESCAPED_UNICODE);
    exit;
}

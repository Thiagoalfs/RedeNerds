<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();

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
    $stmt = $pdo->prepare("INSERT INTO equipe (nick, cargo) VALUES (:nick, :cargo)");
    $stmt->execute([':nick' => $nick, ':cargo' => $cargo]);
    $novoId = (int)$pdo->lastInsertId();

    require_once __DIR__ . "/../../../api/cache_helper.php";
    invalidarCache('equipe');

    $busca = $pdo->prepare("SELECT * FROM equipe WHERE id = :id LIMIT 1");
    $busca->execute([':id' => $novoId]);
    $membro = $busca->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => true,
        "mensagem" => "Membro '{$nick}' adicionado com sucesso!",
        "membro" => $membro
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("Erro ao salvar membro da equipe: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Erro ao salvar no banco de dados."], JSON_UNESCAPED_UNICODE);
    exit;
}

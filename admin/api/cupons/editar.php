<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "ID de cupom inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Conexão com o banco de dados indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM cupons WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$cupom = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cupom) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Cupom não encontrado."], JSON_UNESCAPED_UNICODE);
    exit;
}

$codigo = strtoupper(trim($_POST['codigo'] ?? ''));
$codigo = preg_replace('/[^A-Z0-9_-]/', '', $codigo);
$porcentagem = (float)str_replace(',', '.', $_POST['porcentagem'] ?? '0');
$servidor_id = (int)($_POST['servidor_id'] ?? 0);
$expira_em = trim($_POST['expira_em'] ?? '');
$ativo = !empty($_POST['ativo']) ? 1 : 0;

if (empty($codigo) || strlen($codigo) < 2 || strlen($codigo) > 50) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "O código do cupom deve ter entre 2 e 50 caracteres."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($porcentagem <= 0 || $porcentagem > 100) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "A porcentagem de desconto deve ser entre 0.1% e 100%."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($expira_em)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Informe a data e hora de expiração do cupom."], JSON_UNESCAPED_UNICODE);
    exit;
}

$expiraSql = date('Y-m-d H:i:s', strtotime($expira_em));

try {
    $check = $pdo->prepare("SELECT id FROM cupons WHERE codigo = :codigo AND id != :id LIMIT 1");
    $check->execute([':codigo' => $codigo, ':id' => $id]);
    if ($check->fetch()) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Já existe outro cupom cadastrado com o código '{$codigo}'."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $upd = $pdo->prepare("
        UPDATE cupons 
        SET codigo = :codigo, porcentagem_desconto = :porcentagem, servidor_id = :servidor_id, expira_em = :expira_em, ativo = :ativo
        WHERE id = :id
    ");
    $upd->execute([
        ':codigo' => $codigo,
        ':porcentagem' => $porcentagem,
        ':servidor_id' => $servidor_id > 0 ? $servidor_id : 0,
        ':expira_em' => $expiraSql,
        ':ativo' => $ativo,
        ':id' => $id
    ]);

    $busca = $pdo->prepare("SELECT * FROM cupons WHERE id = :id LIMIT 1");
    $busca->execute([':id' => $id]);
    $cupomAtualizado = $busca->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => true,
        "mensagem" => "Cupom '{$codigo}' atualizado com sucesso!",
        "cupom" => $cupomAtualizado
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("Erro ao atualizar cupom via API: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Erro ao atualizar no banco de dados."], JSON_UNESCAPED_UNICODE);
    exit;
}

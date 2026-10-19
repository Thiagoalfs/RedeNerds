<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();

$codigo = strtoupper(trim($_POST['codigo'] ?? ''));
$codigo = preg_replace('/[^A-Z0-9_-]/', '', $codigo);
$porcentagem = (float)str_replace(',', '.', $_POST['porcentagem'] ?? '0');
$servidor_id = (int)($_POST['servidor_id'] ?? 0);
$expira_em = trim($_POST['expira_em'] ?? '');
$ativo = !empty($_POST['ativo']) ? 1 : 0;

if (empty($codigo) || strlen($codigo) < 2 || strlen($codigo) > 50) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "O código do cupom deve ter entre 2 e 50 caracteres (apenas letras, números, hífens ou underlines)."], JSON_UNESCAPED_UNICODE);
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

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Conexão com o banco de dados indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $check = $pdo->prepare("SELECT id FROM cupons WHERE codigo = :codigo LIMIT 1");
    $check->execute([':codigo' => $codigo]);
    if ($check->fetch()) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Já existe um cupom cadastrado com o código '{$codigo}'."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO cupons (codigo, porcentagem_desconto, servidor_id, expira_em, ativo, usos_total, criado_em)
        VALUES (:codigo, :porcentagem, :servidor_id, :expira_em, :ativo, 0, NOW())
    ");
    $stmt->execute([
        ':codigo' => $codigo,
        ':porcentagem' => $porcentagem,
        ':servidor_id' => $servidor_id > 0 ? $servidor_id : 0,
        ':expira_em' => $expiraSql,
        ':ativo' => $ativo
    ]);

    $novoId = (int)$pdo->lastInsertId();

    $busca = $pdo->prepare("SELECT * FROM cupons WHERE id = :id LIMIT 1");
    $busca->execute([':id' => $novoId]);
    $cupom = $busca->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => true,
        "mensagem" => "Cupom '{$codigo}' criado com sucesso!",
        "cupom" => $cupom
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("Erro ao salvar cupom via API: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Erro interno no banco de dados."], JSON_UNESCAPED_UNICODE);
    exit;
}

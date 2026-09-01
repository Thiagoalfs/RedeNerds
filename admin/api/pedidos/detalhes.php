<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../../config.php";

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$txid = trim($_GET['txid'] ?? '');

if ($id <= 0 && empty($txid)) {
    http_response_code(400);
    echo json_encode(["erro" => "Identificador do pedido não informado"]);
    exit;
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM pedidos_vip WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM pedidos_vip WHERE txid = :txid LIMIT 1");
        $stmt->execute([':txid' => $txid]);
    }

    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        http_response_code(404);
        echo json_encode(["erro" => "Pedido não encontrado"]);
        exit;
    }

    echo json_encode(["success" => true, "pedido" => $pedido], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro ao consultar banco de dados"]);
}
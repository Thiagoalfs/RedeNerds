<?php
/**
 * cancelar_pedido.php
 * Invalida e marca um pedido PIX como 'cancelado' ou 'expirado' no banco de dados.
 */

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$configPaths = [
    __DIR__ . "/../../../config.php",
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];

$configPath = null;
foreach ($configPaths as $cp) {
    if (file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}

if (!$configPath) {
    echo json_encode(["erro" => "Arquivo config.php não encontrado no servidor."], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $configPath;
require_once __DIR__ . "/../auth_api.php";
verificarAcessoApi();

// Lê os dados recebidos via JSON, POST ou GET
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$txid = trim($data['txid'] ?? ($_GET['txid'] ?? ''));
$statusAlvo = strtolower(trim($data['status'] ?? ($_GET['status'] ?? 'cancelado')));

if (!in_array($statusAlvo, ['cancelado', 'expirado'])) {
    $statusAlvo = 'cancelado';
}

if (empty($txid)) {
    http_response_code(400);
    echo json_encode(["erro" => "txid não informado."], JSON_UNESCAPED_UNICODE);
    exit;
}

$atualizado = false;

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        // Apenas cancela se estiver 'pendente' (não cancela pedidos que já foram pagos)
        $stmt = $pdo->prepare("
            UPDATE pedidos_vip 
            SET status = :novoStatus
            WHERE txid = :txid AND status = 'pendente'
        ");
        $stmt->execute([
            ':novoStatus' => $statusAlvo,
            ':txid' => $txid
        ]);
        $atualizado = ($stmt->rowCount() > 0);
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $stmt = $conn->prepare("
            UPDATE pedidos_vip 
            SET status = ? 
            WHERE txid = ? AND status = 'pendente'
        ");
        if ($stmt) {
            $stmt->bind_param("ss", $statusAlvo, $txid);
            $stmt->execute();
            $atualizado = ($stmt->affected_rows > 0);
        }
    }
} catch (Exception $e) {
    error_log("Erro ao cancelar pedido {$txid}: " . $e->getMessage());
}

echo json_encode([
    "success" => true,
    "txid" => $txid,
    "status" => $statusAlvo,
    "atualizado" => $atualizado,
    "mensagem" => $statusAlvo === 'expirado' ? "Tempo limite esgotado. Cobrança PIX invalidada." : "Pedido cancelado com sucesso."
], JSON_UNESCAPED_UNICODE);

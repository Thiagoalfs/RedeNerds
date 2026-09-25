<?php
/**
 * cancelar_pedido.php
 * Invalida e marca um pedido como 'cancelado' ou 'expirado' no banco de dados.
 */

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', '0');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config_loja.php";
aplicarCorsLoja();

require_once __DIR__ . "/../rate_limiter.php";
exigirRateLimit('loja_cancelar_pedido', 10, 60);

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

$txid = trim((string)($data['txid'] ?? ($_GET['txid'] ?? '')));
$statusAlvo = strtolower(trim((string)($data['status'] ?? ($_GET['status'] ?? 'cancelado'))));

if (!in_array($statusAlvo, ['cancelado', 'expirado'], true)) {
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
            UPDATE pedidos 
            SET status = :novoStatus
            WHERE txid = :txid AND status = 'pendente'
        ");
        $stmt->execute([
            ':novoStatus' => $statusAlvo,
            ':txid' => $txid
        ]);
        $atualizado = ($stmt->rowCount() > 0);
    }
} catch (Exception $e) {
    error_log("Erro ao cancelar pedido {$txid}: " . $e->getMessage());
}

echo json_encode([
    "success" => true,
    "txid" => $txid,
    "status" => $statusAlvo,
    "atualizado" => $atualizado,
    "mensagem" => $atualizado ? "Pedido cancelado com sucesso." : "Pedido não localizado ou já finalizado."
], JSON_UNESCAPED_UNICODE);

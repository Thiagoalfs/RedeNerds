<?php
/**
 * checar_status.php
 * Verifica o status de pagamento do pedido (usado no polling pelo modal).
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

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
require_once __DIR__ . "/discord_loja_helper.php";
verificarAcessoApi();

$txid = trim($_GET['txid'] ?? '');

if (empty($txid)) {
    http_response_code(400);
    echo json_encode(["erro" => "Identificador de transação (txid) não informado."], JSON_UNESCAPED_UNICODE);
    exit;
}

$pedido = null;

// 1. Busca o pedido no banco de dados local
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT * FROM pedidos_vip WHERE txid = :txid LIMIT 1");
        $stmt->execute([':txid' => $txid]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $stmt = $conn->prepare("SELECT * FROM pedidos_vip WHERE txid = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $txid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                $pedido = $res->fetch_assoc();
            }
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar status do pedido: " . $e->getMessage());
}

if (!$pedido) {
    // Se não encontrou no banco, pode ser simulação
    echo json_encode([
        "success" => true,
        "txid" => $txid,
        "status" => "pendente",
        "aprovado" => false
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Se já estiver marcado como 'pago' no banco
if (strtolower($pedido['status'] ?? '') === 'pago') {
    echo json_encode([
        "success" => true,
        "txid" => $txid,
        "status" => "pago",
        "aprovado" => true,
        "pago_em" => $pedido['pago_em'] ?? date('c'),
        "nick" => $pedido['nick'],
        "servidor" => $pedido['servidor'],
        "vip_nome" => $pedido['vip_nome']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Se ainda estiver pendente, consulta o Mercado Pago em tempo real
$mpAccessToken = defined('MERCADO_PAGO_ACCESS_TOKEN') ? trim(MERCADO_PAGO_ACCESS_TOKEN) : '';
$isLiveMpToken = (!empty($mpAccessToken) && strpos($mpAccessToken, 'APP_USR-SEU-ACCESS-TOKEN') === false);

if ($isLiveMpToken) {
    $searchUrl = "https://api.mercadopago.com/v1/payments/search?external_reference=" . urlencode($txid);

    $ch = curl_init($searchUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$mpAccessToken}"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 8
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 && $response) {
        $searchData = json_decode($response, true);
        $results = $searchData['results'] ?? [];

        if (!empty($results)) {
            $paymentInfo = $results[0];
            $mpStatus = strtolower($paymentInfo['status'] ?? '');

            if ($mpStatus === 'approved') {
                // Atualiza no banco para 'pago'
                try {
                    if (isset($pdo) && $pdo instanceof PDO) {
                        $upStmt = $pdo->prepare("UPDATE pedidos_vip SET status = 'pago', pago_em = NOW() WHERE txid = :txid");
                        $upStmt->execute([':txid' => $txid]);
                    } elseif (isset($conn) && $conn instanceof mysqli) {
                        $upStmt = $conn->prepare("UPDATE pedidos_vip SET status = 'pago', pago_em = NOW() WHERE txid = ?");
                        if ($upStmt) {
                            $upStmt->bind_param("s", $txid);
                            $upStmt->execute();
                        }
                    }
                } catch (Exception $e) {
                    error_log("Erro ao atualizar status pago: " . $e->getMessage());
                }

                // Dispara o Webhook para o Discord
                enviarNotificacaoCompraDiscord(
                    $pedido['nick'],
                    $pedido['tipo_conta'] ?? 'original',
                    $pedido['servidor'],
                    $pedido['vip_nome'],
                    $pedido['valor'],
                    $txid
                );

                echo json_encode([
                    "success" => true,
                    "txid" => $txid,
                    "status" => "pago",
                    "aprovado" => true,
                    "pago_em" => date('c'),
                    "nick" => $pedido['nick'],
                    "servidor" => $pedido['servidor'],
                    "vip_nome" => $pedido['vip_nome']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
}

// Continua pendente
echo json_encode([
    "success" => true,
    "txid" => $txid,
    "status" => "pendente",
    "aprovado" => false
], JSON_UNESCAPED_UNICODE);

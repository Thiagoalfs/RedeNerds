<?php
/**
 * checar_status.php
 * Verifica o status de pagamento do pedido (usado no polling pelo modal).
 * Atualiza automaticamente pedidos pendentes com mais de 15 minutos para 'expirado'.
 */

date_default_timezone_set('America/Sao_Paulo');
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

// Auto-limpeza de pedidos pendentes com mais de 15 minutos
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->query("UPDATE pedidos_vip SET status = 'expirado' WHERE status = 'pendente' AND TIMESTAMPDIFF(MINUTE, criado_em, NOW()) >= 15");
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $conn->query("UPDATE pedidos_vip SET status = 'expirado' WHERE status = 'pendente' AND TIMESTAMPDIFF(MINUTE, criado_em, NOW()) >= 15");
    }
} catch (Exception $e) {
    // Ignora erro de limpeza silenciosamente
}

$txid = trim($_GET['txid'] ?? '');

if (empty($txid)) {
    http_response_code(400);
    echo json_encode(["erro" => "Identificador de transação (txid) não informado."], JSON_UNESCAPED_UNICODE);
    exit;
}

$pedido = null;

// 1. Busca o pedido no banco de dados local com cálculo exato de segundos decorridos
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT *, TIMESTAMPDIFF(SECOND, criado_em, NOW()) as segundos_desde_criacao FROM pedidos_vip WHERE txid = :txid LIMIT 1");
        $stmt->execute([':txid' => $txid]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $stmt = $conn->prepare("SELECT *, TIMESTAMPDIFF(SECOND, criado_em, NOW()) as segundos_desde_criacao FROM pedidos_vip WHERE txid = ? LIMIT 1");
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
    echo json_encode([
        "success" => true,
        "txid" => $txid,
        "status" => "pendente",
        "aprovado" => false
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$statusAtual = strtolower($pedido['status'] ?? 'pendente');

// 2. Se já estiver marcado como 'pago' no banco
if ($statusAtual === 'pago') {
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

// 3. Se estiver marcado como 'cancelado' ou 'expirado'
if (in_array($statusAtual, ['cancelado', 'expirado'])) {
    echo json_encode([
        "success" => true,
        "txid" => $txid,
        "status" => $statusAtual,
        "aprovado" => false,
        "expirado" => true,
        "mensagem" => "O tempo limite de 15 minutos para este PIX se esgotou."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Se o pedido foi criado há mais de 15 minutos (900 segundos calculados pelo banco)
$segundosDecorridos = isset($pedido['segundos_desde_criacao']) ? (int)$pedido['segundos_desde_criacao'] : 0;
if ($segundosDecorridos >= 900) {
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $upStmt = $pdo->prepare("UPDATE pedidos_vip SET status = 'expirado' WHERE txid = :txid AND status = 'pendente'");
            $upStmt->execute([':txid' => $txid]);
        } elseif (isset($conn) && $conn instanceof mysqli) {
            $upStmt = $conn->prepare("UPDATE pedidos_vip SET status = 'expirado' WHERE txid = ? AND status = 'pendente'");
            if ($upStmt) {
                $upStmt->bind_param("s", $txid);
                $upStmt->execute();
            }
        }
    } catch (Exception $e) {}

    echo json_encode([
        "success" => true,
        "txid" => $txid,
        "status" => "expirado",
        "aprovado" => false,
        "expirado" => true,
        "mensagem" => "Tempo limite de 15 minutos esgotado."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 5. Se ainda estiver dentro dos 15 minutos, consulta o Mercado Pago em tempo real
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
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
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
            } elseif (in_array($mpStatus, ['cancelled', 'rejected'])) {
                // Marca como cancelado no banco
                try {
                    if (isset($pdo) && $pdo instanceof PDO) {
                        $upStmt = $pdo->prepare("UPDATE pedidos_vip SET status = 'cancelado' WHERE txid = :txid");
                        $upStmt->execute([':txid' => $txid]);
                    }
                } catch (Exception $e) {}

                echo json_encode([
                    "success" => true,
                    "txid" => $txid,
                    "status" => "cancelado",
                    "aprovado" => false,
                    "expirado" => true,
                    "mensagem" => "Cobrança cancelada pelo gateway de pagamento."
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
}

// Continua pendente normalmente enquanto o timer estiver correndo
echo json_encode([
    "success" => true,
    "txid" => $txid,
    "status" => "pendente",
    "aprovado" => false,
    "segundos_restantes" => max(0, 900 - $segundosDecorridos)
], JSON_UNESCAPED_UNICODE);

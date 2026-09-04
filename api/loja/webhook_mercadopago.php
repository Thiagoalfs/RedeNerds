<?php
/**
 * webhook_mercadopago.php
 * Endpoint de recebimento de notificações IPN/Webhook do Mercado Pago.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

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
    http_response_code(500);
    echo json_encode(["erro" => "config.php não encontrado."]);
    exit;
}

require_once $configPath;
require_once __DIR__ . "/discord_loja_helper.php";
require_once __DIR__ . "/delivery_helper.php";

// Lê payload JSON ou parâmetros GET
$rawInput = file_get_contents('php://input');
$bodyData = json_decode($rawInput, true);

$paymentId = $_GET['data_id'] 
    ?? ($_GET['data']['id'] 
    ?? ($_GET['id'] 
    ?? ($bodyData['data']['id'] 
    ?? ($bodyData['id'] ?? null))));

$type = $_GET['type'] ?? ($bodyData['type'] ?? ($bodyData['action'] ?? 'payment'));

if (!$paymentId || (strpos($type, 'payment') === false)) {
    // Notificação de outro recurso ou ping vazio: responde 200 para o Mercado Pago
    http_response_code(200);
    echo json_encode(["status" => "ignored"]);
    exit;
}

// Validação de Assinatura Criptográfica (x-signature / x-request-id)
$webhookSecret = defined('MERCADO_PAGO_WEBHOOK_SECRET') ? trim(MERCADO_PAGO_WEBHOOK_SECRET) : '';
$headers = function_exists('getallheaders') ? getallheaders() : [];

$xSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? ($headers['x-signature'] ?? ($headers['X-Signature'] ?? ''));
$xRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? ($headers['x-request-id'] ?? ($headers['X-Request-Id'] ?? ''));

if (!empty($webhookSecret) && !empty($xSignature)) {
    $parts = explode(',', $xSignature);
    $ts = null;
    $v1 = null;
    foreach ($parts as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) === 2) {
            if ($kv[0] === 'ts') $ts = $kv[1];
            if ($kv[0] === 'v1') $v1 = $kv[1];
        }
    }

    if ($ts && $v1) {
        $manifest = "id:{$paymentId};request-id:{$xRequestId};ts:{$ts};";
        $computedHash = hash_hmac('sha256', $manifest, $webhookSecret);
        if (!hash_equals($computedHash, $v1)) {
            http_response_code(401);
            echo json_encode(["erro" => "Assinatura do webhook inválida."]);
            exit;
        }
    }
}

$mpAccessToken = defined('MERCADO_PAGO_ACCESS_TOKEN') ? trim(MERCADO_PAGO_ACCESS_TOKEN) : '';

if (empty($mpAccessToken) || strpos($mpAccessToken, 'APP_USR-SEU-ACCESS-TOKEN') !== false) {
    http_response_code(200);
    echo json_encode(["erro" => "MERCADO_PAGO_ACCESS_TOKEN não configurado."]);
    exit;
}

// Consulta os dados completos do pagamento na API do Mercado Pago
$mpUrl = "https://api.mercadopago.com/v1/payments/" . urlencode($paymentId);

$ch = curl_init($mpUrl);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$mpAccessToken}"
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 300 || !$response) {
    http_response_code(200);
    echo json_encode(["erro" => "Não foi possível validar o pagamento com o Mercado Pago."]);
    exit;
}

$paymentInfo = json_decode($response, true);
$status = strtolower($paymentInfo['status'] ?? '');
$externalRef = trim($paymentInfo['external_reference'] ?? '');

if ($status === 'approved' && !empty($externalRef)) {
    $pedido = null;

    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->prepare("SELECT * FROM pedidos_vip WHERE txid = :txid LIMIT 1");
            $stmt->execute([':txid' => $externalRef]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif (isset($conn) && $conn instanceof mysqli) {
            $stmt = $conn->prepare("SELECT * FROM pedidos_vip WHERE txid = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $externalRef);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) $pedido = $res->fetch_assoc();
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao consultar pedido no webhook MP: " . $e->getMessage());
    }

    if ($pedido && strtolower($pedido['status'] ?? '') !== 'pago') {
        // Atualiza para pago
        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                $up = $pdo->prepare("UPDATE pedidos_vip SET status = 'pago', pago_em = NOW() WHERE txid = :txid");
                $up->execute([':txid' => $externalRef]);
            } elseif (isset($conn) && $conn instanceof mysqli) {
                $up = $conn->prepare("UPDATE pedidos_vip SET status = 'pago', pago_em = NOW() WHERE txid = ?");
                if ($up) {
                    $up->bind_param("s", $externalRef);
                    $up->execute();
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao atualizar pedido no webhook MP: " . $e->getMessage());
        }

        // Dispara notificação no Discord
        enviarNotificacaoCompraDiscord(
            $pedido['nick'],
            $pedido['tipo_conta'] ?? 'original',
            $pedido['servidor'],
            $pedido['vip_nome'],
            $pedido['valor'],
            $externalRef,
            '#7DB9DF',
            $pedido['metodo_pagamento'] ?? 'pix',
            (int)($pedido['parcelas'] ?? 1),
            $pedido['valor_total'] ?? null
        );

        // Dispara entrega do VIP na API de Entregas
        try {
            enviarEntregaVip($pedido, $pdo);
        } catch (Exception $e) {
            error_log("Erro ao disparar entrega VIP no webhook: " . $e->getMessage());
        }
    }
}

http_response_code(200);
echo json_encode(["status" => "processed"]);

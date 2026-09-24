<?php
/**
 * webhook_mercadopago.php
 * Endpoint de recebimento de notificações IPN/Webhook do Mercado Pago.
 * Implementa transição atômica e idempotente para evitar duplicações de entrega/notificações.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
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
require_once __DIR__ . "/config_loja.php";
require_once __DIR__ . "/discord_loja_helper.php";
require_once __DIR__ . "/delivery_helper.php";
require_once __DIR__ . "/cupom_helper.php";

// Lê payload JSON ou parâmetros GET
$rawInput = file_get_contents('php://input');
$bodyData = json_decode($rawInput, true);

$paymentId = $_GET['data_id'] 
    ?? ($_GET['data']['id'] 
    ?? ($_GET['id'] 
    ?? ($bodyData['data']['id'] 
    ?? ($bodyData['id'] ?? null))));

$type = $_GET['type'] ?? ($bodyData['type'] ?? ($bodyData['action'] ?? 'payment'));

if (!$paymentId || (strpos((string)$type, 'payment') === false)) {
    // Notificação de outro recurso ou ping vazio: responde 200 para o Mercado Pago
    http_response_code(200);
    echo json_encode(["status" => "ignored"]);
    exit;
}

// Validação de Assinatura Criptográfica (x-signature / x-request-id)
$webhookSecret = defined('MERCADO_PAGO_WEBHOOK_SECRET') ? trim((string)MERCADO_PAGO_WEBHOOK_SECRET) : '';
$headers = function_exists('getallheaders') ? getallheaders() : [];

$xSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? ($headers['x-signature'] ?? ($headers['X-Signature'] ?? ''));
$xRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? ($headers['x-request-id'] ?? ($headers['X-Request-Id'] ?? ''));

if (!empty($webhookSecret) && !empty($xSignature)) {
    $parts = explode(',', (string)$xSignature);
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

$mpAccessToken = obterMercadoPagoAccessToken();

if (!$mpAccessToken) {
    http_response_code(200);
    echo json_encode(["erro" => "MERCADO_PAGO_ACCESS_TOKEN não configurado."]);
    exit;
}

// Consulta os dados completos do pagamento na API do Mercado Pago
$mpUrl = "https://api.mercadopago.com/v1/payments/" . urlencode((string)$paymentId);

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

$paymentInfo = json_decode((string)$response, true);
$status = strtolower((string)($paymentInfo['status'] ?? ''));
$externalRef = trim((string)($paymentInfo['external_reference'] ?? ''));

if ($status === 'approved' && !empty($externalRef)) {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        http_response_code(500);
        echo json_encode(["erro" => "Banco de dados indisponível."]);
        exit;
    }

    garantirSchemaTabelaPedidos($pdo);

    // 1. Executa a transição atômica de status
    $transicaoOcorreu = false;
    try {
        $up = $pdo->prepare("
            UPDATE pedidos_vip 
            SET status = 'pago', pago_em = NOW(), mp_payment_id = :mp_id 
            WHERE txid = :txid AND status <> 'pago'
        ");
        $up->execute([
            ':txid' => $externalRef,
            ':mp_id' => (string)$paymentId
        ]);
        $transicaoOcorreu = ($up->rowCount() === 1);
    } catch (Exception $e) {
        error_log("Erro na transição atômica do webhook MP: " . $e->getMessage());
    }

    // Caso o pedido não tenha sido encontrado para update, tenta auto-recuperação resiliente
    if (!$transicaoOcorreu) {
        $transicaoOcorreu = autoRecuperarPedidoMercadoPago($pdo, $externalRef, $paymentId, $paymentInfo);
    }

    // 2. Dispara efeitos colaterais SOMENTE se esta execução foi a responsável pela mudança para 'pago'
    if ($transicaoOcorreu) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM pedidos_vip WHERE txid = :txid LIMIT 1");
            $stmt->execute([':txid' => $externalRef]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($pedido) {
                // Cômputo do cupom de forma idempotente
                registrarUsoCupomSePago($pdo, $externalRef);

                // Notificação no Discord
                try {
                    enviarNotificacaoCompraDiscord(
                        $pedido['nick'],
                        $pedido['tipo_conta'] ?? 'original',
                        $pedido['servidor'],
                        $pedido['vip_nome'],
                        (float)$pedido['valor'],
                        $externalRef,
                        '#7DB9DF',
                        $pedido['metodo_pagamento'] ?? 'pix',
                        (int)($pedido['parcelas'] ?? 1),
                        isset($pedido['valor_total']) ? (float)$pedido['valor_total'] : null,
                        $pedido['cupom_codigo'] ?? null,
                        (float)($pedido['desconto_aplicado'] ?? 0.00),
                        $pedido['tipo_produto'] ?? 'vip',
                        (int)($pedido['quantidade'] ?? 1)
                    );
                } catch (Exception $e) {
                    error_log("Erro ao disparar webhook Discord no webhook MP: " . $e->getMessage());
                }

                // Entrega do VIP
                try {
                    enviarEntregaVip($pedido, $pdo);
                } catch (Exception $e) {
                    error_log("Erro ao disparar entrega VIP no webhook: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao processar pós-pagamento no webhook MP: " . $e->getMessage());
        }
    }
}

http_response_code(200);
echo json_encode(["status" => "processed"]);

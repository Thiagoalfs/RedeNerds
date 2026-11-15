<?php
/**
 * criar_pix.php
 * Cria a cobrança PIX via API do Mercado Pago e registra o pedido no banco de dados.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config_loja.php";
aplicarCorsLoja();

require_once __DIR__ . "/../rate_limiter.php";
exigirRateLimit('loja_criar_pix', 5, 60);

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
    http_response_code(500);
    echo json_encode(["erro" => "Arquivo config.php não encontrado no servidor."], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $configPath;
require_once __DIR__ . "/../auth_api.php";
require_once __DIR__ . "/ip_helper.php";
verificarAcessoApi();

if (isset($pdo) && $pdo instanceof PDO) {
    garantirSchemaTabelaPedidos($pdo);
}

// Lê os dados recebidos via JSON (ou POST)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["erro" => "Conexão com o banco de dados indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

$checkout = prepararCheckoutLoja($pdo, $data);
if (!$checkout['ok']) {
    http_response_code($checkout['status']);
    echo json_encode(["erro" => $checkout['erro']], JSON_UNESCAPED_UNICODE);
    exit;
}

$nick = $checkout['nick'];
$tipoConta = $checkout['tipo_conta'];
$servidor = $checkout['servidor'];
$tipoProduto = $checkout['tipo_produto'];
$itemId = $checkout['item_id'];
$itemNome = $checkout['item_nome'];
$quantidade = $checkout['quantidade'];
$valorOriginal = $checkout['valor_original'];
$descontoAplicado = $checkout['desconto_aplicado'];
$valor = $checkout['valor'];
$cupomCodigo = $checkout['cupom_codigo'];

// Gera identificador único para o pedido
$txid = "NERD-" . strtoupper(bin2hex(random_bytes(8)));

// Token do Mercado Pago
$mpAccessToken = obterMercadoPagoAccessToken();
if (!$mpAccessToken) {
    responder503ServicoIndisponivel("MERCADO_PAGO_ACCESS_TOKEN ausente ou inválido no criar_pix.");
}

$siteUrl = obterSiteUrl();
$paisIp = obterPaisIp();

// Registra o pedido localmente antes de chamar o gateway para evitar cobrança sem registro
$dbVipId = ($tipoProduto === 'vip') ? $itemId : null;
$dbChaveId = ($tipoProduto === 'chave') ? $itemId : null;

try {
    $stmt = $pdo->prepare("
        INSERT INTO pedidos (
            txid, mp_payment_id, nick, pais_ip, tipo_conta, servidor, 
            tipo_produto, quantidade, chave_id, vip_id, vip_nome, cupom_codigo, 
            valor, valor_original, desconto_aplicado, status, metodo_pagamento, 
            pix_copia_cola, pix_qr_base64, cupom_computado, criado_em
        ) VALUES (
            :txid, NULL, :nick, :pais_ip, :tipo_conta, :servidor, 
            :tipo_produto, :quantidade, :chave_id, :vip_id, :vip_nome, :cupom_codigo, 
            :valor, :valor_original, :desconto_aplicado, 'pendente', 'pix', 
            NULL, NULL, 0, NOW()
        )
    ");
    $stmt->execute([
        ':txid' => $txid,
        ':nick' => $nick,
        ':pais_ip' => $paisIp,
        ':tipo_conta' => $tipoConta,
        ':servidor' => $servidor,
        ':tipo_produto' => $tipoProduto,
        ':quantidade' => $quantidade,
        ':chave_id' => $dbChaveId,
        ':vip_id' => $dbVipId,
        ':vip_nome' => $itemNome,
        ':cupom_codigo' => $cupomCodigo,
        ':valor' => $valor,
        ':valor_original' => $valorOriginal,
        ':desconto_aplicado' => $descontoAplicado
    ]);
} catch (Exception $e) {
    error_log("Erro crítico ao registrar pedido PIX antes do gateway: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["erro" => "Não foi possível registrar o pedido no banco de dados. Tente novamente."], JSON_UNESCAPED_UNICODE);
    exit;
}

// Comunicação real com a API do Mercado Pago
$mpUrl = "https://api.mercadopago.com/v1/payments";

$itemDescricao = ($tipoProduto === 'chave') 
    ? "Rede Nerd - {$quantidade}x {$itemNome} ({$servidor}) - Jogador: {$nick}"
    : "Rede Nerd - {$itemNome} ({$servidor}) - Jogador: {$nick}";

// Define a expiração oficial no Mercado Pago (mínimo de 30 minutos aceito pela API)
$dateOfExpiration = (new DateTime('+30 minutes', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d\TH:i:s.000P');

$mpPayload = [
    "transaction_amount" => (float)$valor,
    "description" => $itemDescricao,
    "payment_method_id" => "pix",
    "payer" => [
        "email" => "pagamento.{$nick}@redenerds.com.br",
        "first_name" => $nick,
        "last_name" => "Player"
    ],
    "notification_url" => $siteUrl . "/api/loja/webhook_mercadopago.php",
    "external_reference" => $txid,
    "date_of_expiration" => $dateOfExpiration
];

$ch = curl_init($mpUrl);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$mpAccessToken}",
        "Content-Type: application/json",
        "X-Idempotency-Key: " . $txid
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($mpPayload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT => 15
]);

$mpResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    try { $pdo->prepare("UPDATE pedidos SET status = 'recusado' WHERE txid = :txid AND status = 'pendente' LIMIT 1")->execute([':txid' => $txid]); } catch (Exception $e) {}
    http_response_code(500);
    echo json_encode(["erro" => "Falha ao conectar com o gateway de pagamento: {$curlError}"], JSON_UNESCAPED_UNICODE);
    exit;
}

$mpData = json_decode((string)$mpResponse, true);

if ($httpCode >= 200 && $httpCode < 300 && isset($mpData['id'])) {
    $mpId = (string)$mpData['id'];
    $pointOfInteraction = $mpData['point_of_interaction'] ?? [];
    $transactionData = $pointOfInteraction['transaction_data'] ?? [];

    $pixCopiaCola = $transactionData['qr_code'] ?? null;
    $pixQrBase64 = $transactionData['qr_code_base64'] ?? null;
} else {
    try { $pdo->prepare("UPDATE pedidos SET status = 'recusado' WHERE txid = :txid AND status = 'pendente' LIMIT 1")->execute([':txid' => $txid]); } catch (Exception $e) {}
    $msgErro = $mpData['message'] ?? ($mpData['error'] ?? "Erro na criação do PIX no Mercado Pago.");
    if (isset($mpData['cause']) && is_array($mpData['cause']) && !empty($mpData['cause'][0]['description'])) {
        $msgErro .= " (" . $mpData['cause'][0]['description'] . ")";
    }
    http_response_code(400);
    echo json_encode(["erro" => $msgErro], JSON_UNESCAPED_UNICODE);
    exit;
}

// Atualiza o pedido com os dados do PIX retornado pelo Mercado Pago
try {
    $stmtUp = $pdo->prepare("
        UPDATE pedidos
        SET mp_payment_id = :mp_id,
            pix_copia_cola = :pix_copia_cola,
            pix_qr_base64 = :pix_qr_base64
        WHERE txid = :txid
        LIMIT 1
    ");
    $stmtUp->execute([
        ':mp_id' => $mpId,
        ':pix_copia_cola' => $pixCopiaCola,
        ':pix_qr_base64' => $pixQrBase64,
        ':txid' => $txid
    ]);
} catch (Exception $e) {
    error_log("Erro ao atualizar dados PIX do pedido {$txid}: " . $e->getMessage());
}

$expiraEm = date('c', time() + (15 * 60)); // 15 minutos

echo json_encode([
    "success" => true,
    "txid" => $txid,
    "mp_id" => $mpId,
    "nick" => $nick,
    "tipo_conta" => $tipoConta,
    "tipo_produto" => $tipoProduto,
    "quantidade" => $quantidade,
    "servidor" => $servidor,
    "vip_nome" => $itemNome,
    "item_nome" => $itemNome,
    "valor" => $valor,
    "valor_original" => $valorOriginal,
    "desconto_aplicado" => $descontoAplicado,
    "cupom" => $cupomCodigo,
    "pix_copia_cola" => $pixCopiaCola,
    "pix_qr_base64" => $pixQrBase64,
    "qr_code" => $pixCopiaCola,
    "qr_code_base64" => $pixQrBase64,
    "expira_em" => $expiraEm,
    "modo_demo" => false
], JSON_UNESCAPED_UNICODE);

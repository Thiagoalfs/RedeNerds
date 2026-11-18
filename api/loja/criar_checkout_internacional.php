<?php
/**
 * criar_checkout_internacional.php
 * Cria a preferência do Mercado Pago Checkout Pro para pagamentos internacionais (sem CPF / cartão internacional)
 * e registra o pedido no banco de dados.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config_loja.php";
aplicarCorsLoja();

require_once __DIR__ . "/../rate_limiter.php";
exigirRateLimit('loja_criar_checkout_internacional', 5, 60);

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

// Lê dados da requisição
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$clientIp = obterIpRealCliente();
$email = trim(strtolower((string)($data['email'] ?? '')));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["erro" => "Please provide a valid email address."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["erro" => "Database connection unavailable."], JSON_UNESCAPED_UNICODE);
    exit;
}

$checkout = prepararCheckoutLoja($pdo, $data);
if (!$checkout['ok']) {
    http_response_code($checkout['status']);
    echo json_encode(["erro" => $checkout['erro']], JSON_UNESCAPED_UNICODE);
    exit;
}

$nick = $checkout['nick'];
$destinatario = $checkout['destinatario'];
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

// 4. CRIAÇÃO DA PREFERÊNCIA NO MERCADO PAGO
$mpAccessToken = obterMercadoPagoAccessToken();
if (!$mpAccessToken) {
    responder503ServicoIndisponivel("MERCADO_PAGO_ACCESS_TOKEN ausente ou inválido no criar_checkout_internacional.");
}

$txid = "NERD-" . strtoupper(bin2hex(random_bytes(8)));
$siteUrl = obterSiteUrl();
$paisIp = obterPaisIp();

// Registra o pedido localmente antes de chamar o gateway para evitar cobrança sem registro
$dbVipId = ($tipoProduto === 'vip') ? $itemId : null;
$dbChaveId = ($tipoProduto === 'chave') ? $itemId : null;

try {
    $stmtIns = $pdo->prepare("
        INSERT INTO pedidos (
            txid, mp_payment_id, nick, destinatario, payer_email, payer_cpf, pais_ip, tipo_conta, servidor, 
            tipo_produto, quantidade, chave_id, vip_id, vip_nome, cupom_codigo, valor, valor_original, desconto_aplicado, 
            valor_total, status, metodo_pagamento, cupom_computado, criado_em
        ) VALUES (
            :txid, NULL, :nick, :destinatario, :email, NULL, :pais_ip, :tipo_conta, :servidor,
            :tipo_produto, :quantidade, :chave_id, :vip_id, :vip_nome, :cupom_codigo, :valor, :valor_original, :desconto_aplicado,
            :valor_total, 'pendente', 'checkout_pro', 0, NOW()
        )
    ");
    $stmtIns->execute([
        ':txid'              => $txid,
        ':nick'              => $nick,
        ':destinatario'      => $destinatario,
        ':email'             => $email,
        ':pais_ip'           => $paisIp,
        ':tipo_conta'        => $tipoConta,
        ':servidor'          => $servidor,
        ':tipo_produto'      => $tipoProduto,
        ':quantidade'        => $quantidade,
        ':chave_id'          => $dbChaveId,
        ':vip_id'            => $dbVipId,
        ':vip_nome'          => $itemNome,
        ':cupom_codigo'      => $cupomCodigo,
        ':valor'             => $valor,
        ':valor_original'    => $valorOriginal,
        ':desconto_aplicado' => $descontoAplicado,
        ':valor_total'       => $valor
    ]);
} catch (Exception $e) {
    error_log("Erro ao salvar pedido de Checkout Pro antes do gateway: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["erro" => "Could not register order in database."], JSON_UNESCAPED_UNICODE);
    exit;
}

$now = time();
$expiraFrom = date('Y-m-d\TH:i:s.000P', $now - 120);
$expiraTo   = date('Y-m-d\TH:i:s.000P', $now + 7200);

$itemTitulo = ($tipoProduto === 'chave')
    ? "Rede Nerd - {$quantidade}x {$itemNome} ({$servidor}) - Jogador: {$nick}"
    : "Rede Nerd - {$itemNome} ({$servidor}) - Jogador: {$nick}";

$preferencePayload = [
    "items" => [
        [
            "title" => $itemTitulo,
            "quantity" => 1,
            "currency_id" => "BRL",
            "unit_price" => (float)$valor
        ]
    ],
    "payer" => [
        "email" => $email,
        "name" => $nick
    ],
    "payment_methods" => [
        "excluded_payment_types" => [
            ["id" => "ticket"],
            ["id" => "bank_transfer"]
        ],
        "installments" => 1,
        "default_installments" => 1
    ],
    "external_reference" => $txid,
    "statement_descriptor" => "REDENERDS",
    "notification_url" => $siteUrl . "/api/loja/webhook_mercadopago.php",
    "back_urls" => [
        "success" => $siteUrl . "/loja/?txid={$txid}&status=success",
        "failure" => $siteUrl . "/loja/?txid={$txid}&status=failure",
        "pending" => $siteUrl . "/loja/?txid={$txid}&status=pending"
    ],
    "auto_return" => "approved",
    "expires" => true,
    "expiration_date_from" => $expiraFrom,
    "expiration_date_to"   => $expiraTo,
    "metadata" => [
        "txid" => $txid,
        "nick" => $nick,
        "destinatario" => $destinatario,
        "tipo_conta" => $tipoConta,
        "tipo_produto" => $tipoProduto,
        "quantidade" => $quantidade,
        "servidor" => $servidor,
        "item_id" => $itemId,
        "item_nome" => $itemNome,
        "cupom" => $cupomCodigo
    ]
];

$mpUrl = "https://api.mercadopago.com/checkout/preferences";
$ch = curl_init($mpUrl);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$mpAccessToken}",
        "Content-Type: application/json",
        "X-Idempotency-Key: " . $txid
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($preferencePayload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT => 20
]);

$mpResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    try { $pdo->prepare("UPDATE pedidos SET status = 'recusado' WHERE txid = :txid AND status = 'pendente' LIMIT 1")->execute([':txid' => $txid]); } catch (Exception $e) {}
    http_response_code(500);
    echo json_encode(["erro" => "Connection failure with payment gateway: {$curlError}"], JSON_UNESCAPED_UNICODE);
    exit;
}

$mpData = json_decode((string)$mpResponse, true);

if ($httpCode < 200 || $httpCode >= 300 || !isset($mpData['id'])) {
    try { $pdo->prepare("UPDATE pedidos SET status = 'recusado' WHERE txid = :txid AND status = 'pendente' LIMIT 1")->execute([':txid' => $txid]); } catch (Exception $e) {}
    $msgErro = $mpData['message'] ?? ($mpData['error'] ?? "Failed to create payment checkout preference.");
    if (isset($mpData['cause']) && is_array($mpData['cause']) && !empty($mpData['cause'][0]['description'])) {
        $msgErro .= " (" . $mpData['cause'][0]['description'] . ")";
    }
    http_response_code(400);
    echo json_encode(["erro" => $msgErro], JSON_UNESCAPED_UNICODE);
    exit;
}

$prefId = (string)$mpData['id'];
$initPoint = (string)($mpData['init_point'] ?? '');

// Atualiza o pedido com o ID da preferência no Mercado Pago
try {
    $pdo->prepare("UPDATE pedidos SET mp_payment_id = :mp_id WHERE txid = :txid LIMIT 1")
        ->execute([':mp_id' => $prefId, ':txid' => $txid]);
} catch (Exception $e) {
    error_log("Erro ao atualizar mp_payment_id de Checkout Pro ({$txid}): " . $e->getMessage());
}

echo json_encode([
    "success"            => true,
    "txid"               => $txid,
    "preference_id"      => $prefId,
    "init_point"         => $initPoint,
    "nick"               => $nick,
    "tipo_conta"         => $tipoConta,
    "tipo_produto"       => $tipoProduto,
    "quantidade"         => $quantidade,
    "servidor"           => $servidor,
    "vip_nome"           => $itemNome,
    "item_nome"          => $itemNome,
    "valor"              => $valor,
    "valor_original"     => $valorOriginal,
    "desconto_aplicado"  => $descontoAplicado,
    "cupom"              => $cupomCodigo,
    "expira_em"          => date('c', $now + 7200)
], JSON_UNESCAPED_UNICODE);

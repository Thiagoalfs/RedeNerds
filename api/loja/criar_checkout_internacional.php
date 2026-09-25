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

if (isset($pdo) && $pdo instanceof PDO) {
    garantirSchemaTabelaPedidos($pdo);
}

// Lê dados da requisição
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

// 1. RATE LIMITING (IP e E-mail)
$clientIp = obterIpRealCliente();
$email = trim(strtolower((string)($data['email'] ?? '')));

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->exec("DELETE FROM rate_limits_loja WHERE tentativa_em < (NOW() - INTERVAL 1 HOUR)");

        $stmtRl = $pdo->prepare("
            SELECT COUNT(*) FROM rate_limits_loja 
            WHERE (ip = :ip OR (email = :email AND :email != '')) 
              AND endpoint = 'checkout_internacional' 
              AND tentativa_em >= (NOW() - INTERVAL 10 MINUTE)
        ");
        $stmtRl->execute([':ip' => $clientIp, ':email' => $email]);
        $tentativasRecentes = (int)$stmtRl->fetchColumn();

        if ($tentativasRecentes >= 5) {
            http_response_code(429);
            echo json_encode([
                "erro" => "Too many recent payment attempts. Please wait 10 minutes before trying again."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtLog = $pdo->prepare("
            INSERT INTO rate_limits_loja (ip, email, endpoint, tentativa_em) 
            VALUES (:ip, :email, 'checkout_internacional', NOW())
        ");
        $stmtLog->execute([':ip' => $clientIp, ':email' => $email]);
    }
} catch (Exception $e) {
    error_log("Erro no rate limiting do checkout internacional: " . $e->getMessage());
}

// 2. EXTRAÇÃO E VALIDAÇÃO DOS DADOS
$nick = trim((string)($data['nick'] ?? ''));
$tipoConta = strtolower(trim((string)($data['tipo_conta'] ?? 'original')));
$servidor = trim((string)($data['servidor'] ?? ''));
$tipoProduto = strtolower(trim((string)($data['tipo_produto'] ?? 'vip')));
if (!in_array($tipoProduto, ['vip', 'chave'], true)) {
    $tipoProduto = 'vip';
}

$vipId = (int)($data['vip_id'] ?? 0);
$chaveId = (int)($data['chave_id'] ?? 0);
$itemId = ($tipoProduto === 'chave') ? ($chaveId > 0 ? $chaveId : $vipId) : $vipId;

$quantidade = filter_var($data['quantidade'] ?? 1, FILTER_VALIDATE_INT);
if ($quantidade === false || $quantidade < 1) $quantidade = 1;
if ($quantidade > 100) $quantidade = 100;
if ($tipoProduto === 'vip') $quantidade = 1;

if (empty($nick) || strlen($nick) < 3 || strlen($nick) > 16 || !preg_match('/^[a-zA-Z0-9_]+$/', $nick)) {
    http_response_code(400);
    echo json_encode(["erro" => "Invalid Minecraft Nickname. Use 3 to 16 alphanumeric characters or underscore."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($tipoConta, ['original', 'pirata'], true)) {
    $tipoConta = 'original';
}

if (empty($servidor)) {
    http_response_code(400);
    echo json_encode(["erro" => "Please select the server where you want to receive your item."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["erro" => "Please provide a valid email address."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($itemId <= 0) {
    http_response_code(400);
    echo json_encode(["erro" => "Invalid package selected."], JSON_UNESCAPED_UNICODE);
    exit;
}

$cupomEnviado = strtoupper(trim((string)($data['cupom'] ?? '')));
$cupomCodigo = null;
$valorOriginal = 0.0;
$descontoAplicado = 0.0;
$valor = 0.0;
$itemNome = '';

// 3. CONSULTA ESTRITA DE PREÇO E CUPOM NO BANCO DE DADOS
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["erro" => "Database connection unavailable."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($tipoProduto === 'chave') {
        $stmtItem = $pdo->prepare("SELECT id, nome, preco, servidor_id FROM chaves WHERE id = :id AND (ativo = 1 OR ativo IS NULL) LIMIT 1");
    } else {
        $stmtItem = $pdo->prepare("SELECT id, nome, preco, servidor_id FROM vips WHERE id = :id AND (ativo = 1 OR ativo IS NULL) LIMIT 1");
    }
    $stmtItem->execute([':id' => $itemId]);
    $itemRow = $stmtItem->fetch(PDO::FETCH_ASSOC);

    if (!$itemRow) {
        http_response_code(404);
        echo json_encode(["erro" => ($tipoProduto === 'chave' ? "Keys package" : "VIP package") . " not found or currently inactive."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $precoUnitario = (float)$itemRow['preco'];
    $valorOriginal = round($precoUnitario * $quantidade, 2);
    $itemNome = (string)$itemRow['nome'];
    $valor = $valorOriginal;

    // Validação de Cupom
    if (!empty($cupomEnviado)) {
        $stmtCupom = $pdo->prepare("SELECT * FROM cupons WHERE codigo = :codigo LIMIT 1");
        $stmtCupom->execute([':codigo' => $cupomEnviado]);
        $cupomRow = $stmtCupom->fetch(PDO::FETCH_ASSOC);

        if ($cupomRow) {
            $now = time();
            $expiraTs = strtotime((string)$cupomRow['expira_em']);
            $isExpirado = ($expiraTs && $expiraTs < $now);

            if (!$cupomRow['ativo']) {
                http_response_code(400);
                echo json_encode(["erro" => "Coupon '{$cupomEnviado}' is inactive."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($isExpirado) {
                http_response_code(400);
                echo json_encode(["erro" => "Coupon '{$cupomEnviado}' has expired."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Restrição de servidor se houver
            $cupomServidorId = (int)($cupomRow['servidor_id'] ?? 0);
            if ($cupomServidorId > 0) {
                $itemServidorId = (int)($itemRow['servidor_id'] ?? 0);
                if ($itemServidorId > 0 && $itemServidorId !== $cupomServidorId) {
                    http_response_code(400);
                    echo json_encode(["erro" => "This coupon is not valid for the selected server."], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            $porcentagem = (float)$cupomRow['porcentagem_desconto'];
            $descontoAplicado = round($valorOriginal * ($porcentagem / 100), 2);
            $valor = max(0.01, round($valorOriginal - $descontoAplicado, 2));
            $cupomCodigo = (string)$cupomRow['codigo'];
        } else {
            http_response_code(404);
            echo json_encode(["erro" => "Coupon '{$cupomEnviado}' not found."], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Validação de Servidor Ativo
    $srvSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $servidor));
    $stmtCheckSrv = $pdo->prepare("SELECT id, servername, enabled FROM servidores WHERE (servername = :srv OR nome = :srvSlug) LIMIT 1");
    $stmtCheckSrv->execute([':srv' => $servidor, ':srvSlug' => $srvSlug]);
    $srvRow = $stmtCheckSrv->fetch(PDO::FETCH_ASSOC);
    if ($srvRow) {
        if (isset($srvRow['enabled']) && (int)$srvRow['enabled'] === 0) {
            http_response_code(400);
            echo json_encode(["erro" => "Purchases for this server are temporarily disabled."], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $servidor = (string)$srvRow['servername'];
    }
} catch (Exception $e) {
    error_log("Erro no banco ao validar dados do checkout internacional: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["erro" => "An internal database error occurred."], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. CRIAÇÃO DA PREFERÊNCIA NO MERCADO PAGO
$mpAccessToken = obterMercadoPagoAccessToken();
if (!$mpAccessToken) {
    responder503ServicoIndisponivel("MERCADO_PAGO_ACCESS_TOKEN ausente ou inválido no criar_checkout_internacional.");
}

$txid = "NERD-" . strtoupper(substr(md5(uniqid($nick . time(), true)), 0, 16));
$siteUrl = obterSiteUrl();
$paisIp = obterPaisIp();

$now = time();
$expiraFrom = date('Y-m-d\TH:i:s.000P', $now - 120);
$expiraTo   = date('Y-m-d\TH:i:s.000P', $now + 7200);

$itemTitulo = ($tipoProduto === 'chave')
    ? "RedeNerds - {$quantidade}x {$itemNome} ({$servidor}) - Jogador: {$nick}"
    : "RedeNerds - {$itemNome} ({$servidor}) - Jogador: {$nick}";

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
    http_response_code(500);
    echo json_encode(["erro" => "Connection failure with payment gateway: {$curlError}"], JSON_UNESCAPED_UNICODE);
    exit;
}

$mpData = json_decode((string)$mpResponse, true);

if ($httpCode < 200 || $httpCode >= 300 || !isset($mpData['id'])) {
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

// 5. REGISTRO DO PEDIDO NO BANCO DE DADOS
$dbVipId = ($tipoProduto === 'vip') ? $itemId : null;
$dbChaveId = ($tipoProduto === 'chave') ? $itemId : null;

try {
    $stmtIns = $pdo->prepare("
        INSERT INTO pedidos_vip (
            txid, mp_payment_id, nick, payer_email, payer_cpf, pais_ip, tipo_conta, servidor, 
            tipo_produto, quantidade, chave_id, vip_id, vip_nome, cupom_codigo, valor, valor_original, desconto_aplicado, 
            valor_total, status, metodo_pagamento, cupom_computado, criado_em
        ) VALUES (
            :txid, :mp_id, :nick, :email, NULL, :pais_ip, :tipo_conta, :servidor,
            :tipo_produto, :quantidade, :chave_id, :vip_id, :vip_nome, :cupom_codigo, :valor, :valor_original, :desconto_aplicado,
            :valor_total, 'pendente', 'checkout_pro', 0, NOW()
        )
    ");
    $stmtIns->execute([
        ':txid'              => $txid,
        ':mp_id'             => $prefId,
        ':nick'              => $nick,
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
    error_log("Erro ao salvar pedido de Checkout Pro no banco: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["erro" => "Could not register order in database."], JSON_UNESCAPED_UNICODE);
    exit;
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

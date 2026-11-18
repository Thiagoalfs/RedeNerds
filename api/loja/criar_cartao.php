<?php
/**
 * criar_cartao.php
 * Cria a cobrança de Cartão de Crédito via API do Mercado Pago e registra o pedido no banco de dados.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config_loja.php";
aplicarCorsLoja();

require_once __DIR__ . "/../rate_limiter.php";
exigirRateLimit('loja_criar_cartao', 5, 60);

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
require_once __DIR__ . "/discord_loja_helper.php";
require_once __DIR__ . "/delivery_helper.php";
require_once __DIR__ . "/ip_helper.php";
require_once __DIR__ . "/cupom_helper.php";
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

$clientIp = obterIpRealCliente();
$cpfLimpo = preg_replace('/\D/', '', trim((string)($data['cpf'] ?? '')));

// 1. VALIDAÇÃO DE DADOS DO CARTÃO
$token = trim((string)($data['token'] ?? ''));
$installments = (int)($data['installments'] ?? 1);
$paymentMethodId = strtolower(trim((string)($data['payment_method_id'] ?? '')));
$issuerId = trim((string)($data['issuer_id'] ?? ''));
$deviceId = trim((string)($data['device_id'] ?? ''));
$cardholderName = trim((string)($data['cardholder_name'] ?? ''));
$email = trim((string)($data['email'] ?? ''));

if (empty($token)) {
    http_response_code(400);
    echo json_encode(["erro" => "Não foi possível validar os dados do cartão. Verifique o número, validade e código de segurança."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["erro" => "Informe um e-mail válido para receber o comprovante da compra."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($cpfLimpo) !== 11) {
    http_response_code(400);
    echo json_encode(["erro" => "Informe um CPF válido (11 dígitos)."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($cardholderName) || strlen($cardholderName) < 3) {
    http_response_code(400);
    echo json_encode(["erro" => "Informe o nome completo do titular impresso no cartão."], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. DIVISÃO DO NOME
$partesNome = preg_split('/\s+/', $cardholderName);
$firstName = array_shift($partesNome);
$lastName = !empty($partesNome) ? implode(' ', $partesNome) : $firstName;

// 3. REVALIDAÇÃO DO PREÇO REAL NO BANCO DE DADOS
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
$destinatario = $checkout['destinatario'];
$tipoConta = $checkout['tipo_conta'];
$servidor = $checkout['servidor'];
$tipoProduto = $checkout['tipo_produto'];
$itemId = $checkout['item_id'];
$itemNome = $checkout['item_nome'];
$quantidade = $checkout['quantidade'];
$valorOriginal = $checkout['valor_original'];
$descontoAplicado = $checkout['desconto_aplicado'];
$valorReal = $checkout['valor'];
$cupomCodigo = $checkout['cupom_codigo'];

// 5. MAPEAMENTO AMIGÁVEL DE ERROS DE REJEIÇÃO
function mapearStatusDetailMercadoPago(string $statusDetail): string {
    $mensagens = [
        'cc_rejected_bad_filled_security_code' => 'O código de segurança (CVV) informado está incorreto.',
        'cc_rejected_bad_filled_date'          => 'A data de validade informada está incorreta ou o cartão está vencido.',
        'cc_rejected_bad_filled_other'         => 'Verifique os dados do cartão e tente novamente.',
        'cc_rejected_insufficient_amount'      => 'Saldo ou limite insuficiente no cartão.',
        'cc_rejected_call_for_authorize'       => 'O banco emissor bloqueou a compra. Ligue para seu banco para autorizar.',
        'cc_rejected_card_disabled'            => 'O cartão está bloqueado ou desativado. Entre em contato com seu banco.',
        'cc_rejected_high_risk'                => 'Pagamento recusado pela análise de segurança. Recomendamos pagar via PIX.',
        'cc_rejected_duplicated_payment'       => 'Já existe uma transação idêntica em andamento. Aguarde alguns instantes.',
        'cc_rejected_blacklist'                => 'Não foi possível processar o pagamento com este cartão.',
        'cc_rejected_max_attempts'             => 'Você atingiu o limite de tentativas. Tente novamente mais tarde ou use o PIX.'
    ];

    return $mensagens[$statusDetail] ?? 'Pagamento recusado pela operadora do cartão. Verifique os dados ou utilize o PIX.';
}

// 6. GERAÇÃO DO IDENTIFICADOR ÚNICO E PROCESSAMENTO
$txid = "VIP-CARD-" . strtoupper(bin2hex(random_bytes(6)));

$mpAccessToken = obterMercadoPagoAccessToken();
if (!$mpAccessToken) {
    responder503ServicoIndisponivel("MERCADO_PAGO_ACCESS_TOKEN ausente ou inválido no criar_cartao.");
}

$siteUrl = obterSiteUrl();
$paisIp = obterPaisIp();

$mpId = null;
$mpStatus = 'rejected';
$mpStatusDetail = 'cc_rejected_other';
$totalPagoComJuros = $valorReal;
$cardFirstSix = null;
$cardLastFour = null;

// Registra o pedido localmente antes de cobrar o cartão para evitar cobrança sem registro
$dbVipId = ($tipoProduto === 'vip') ? $itemId : null;
$dbChaveId = ($tipoProduto === 'chave') ? $itemId : null;
$novoPedidoId = 0;

try {
    $stmtPre = $pdo->prepare("
        INSERT INTO pedidos (
            txid, mp_payment_id, nick, destinatario, payer_email, payer_cpf, pais_ip, tipo_conta, servidor, 
            tipo_produto, quantidade, chave_id, vip_id, vip_nome, cupom_codigo, valor, valor_original, desconto_aplicado, 
            valor_total, status, status_detail, metodo_pagamento, 
            parcelas, card_first_six_digits, card_last_four_digits, card_payment_method_id, cupom_computado, criado_em, pago_em
        ) VALUES (
            :txid, NULL, :nick, :destinatario, :email, :cpf, :pais_ip, :tipo_conta, :servidor,
            :tipo_produto, :quantidade, :chave_id, :vip_id, :vip_nome, :cupom_codigo, :valor, :valor_original, :desconto_aplicado, 
            :valor_total, 'pendente', 'pending_gateway', 'cartao',
            :parcelas, NULL, NULL, :method_id, 0, NOW(), NULL
        )
    ");
    $stmtPre->execute([
        ':txid' => $txid,
        ':nick' => $nick,
        ':destinatario' => $destinatario,
        ':email' => $email,
        ':cpf' => $cpfLimpo,
        ':pais_ip' => $paisIp,
        ':tipo_conta' => $tipoConta,
        ':servidor' => $servidor,
        ':tipo_produto' => $tipoProduto,
        ':quantidade' => $quantidade,
        ':chave_id' => $dbChaveId,
        ':vip_id' => $dbVipId,
        ':vip_nome' => $itemNome,
        ':cupom_codigo' => $cupomCodigo,
        ':valor' => $valorReal,
        ':valor_original' => $valorOriginal,
        ':desconto_aplicado' => $descontoAplicado,
        ':valor_total' => $totalPagoComJuros,
        ':parcelas' => $installments,
        ':method_id' => $paymentMethodId
    ]);
    $novoPedidoId = (int)$pdo->lastInsertId();
} catch (Exception $e) {
    error_log("Erro crítico ao registrar pedido de cartão antes do gateway: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["erro" => "Não foi possível registrar o pedido no banco de dados. Tente novamente."], JSON_UNESCAPED_UNICODE);
    exit;
}

$mpUrl = "https://api.mercadopago.com/v1/payments";

$itemDescricao = ($tipoProduto === 'chave')
    ? "Rede Nerd - {$quantidade}x {$itemNome} ({$servidor}) - Jogador: {$nick}"
    : "Rede Nerd - {$itemNome} ({$servidor}) - Jogador: {$nick}";

$mpPayload = [
    "transaction_amount" => (float)$valorReal,
    "token" => $token,
    "description" => $itemDescricao,
    "installments" => (int)$installments,
    "payment_method_id" => $paymentMethodId,
    "payer" => [
        "email" => $email,
        "first_name" => $firstName,
        "last_name" => $lastName,
        "identification" => [
            "type" => "CPF",
            "number" => $cpfLimpo
        ]
    ],
    "notification_url" => $siteUrl . "/api/loja/webhook_mercadopago.php",
    "external_reference" => $txid,
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
        "cupom" => $cupomCodigo,
        "metodo_pagamento" => "cartao"
    ]
];

if (!empty($issuerId)) {
    $mpPayload["issuer_id"] = $issuerId;
}

$headers = [
    "Authorization: Bearer {$mpAccessToken}",
    "Content-Type: application/json",
    "X-Idempotency-Key: " . $txid
];

if (!empty($deviceId)) {
    $headers[] = "X-Meli-Session-Id: " . $deviceId;
}

$ch = curl_init($mpUrl);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($mpPayload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT => 20
]);

$mpResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    try { $pdo->prepare("UPDATE pedidos SET status = 'recusado', status_detail = 'gateway_timeout' WHERE txid = :txid AND status = 'pendente' LIMIT 1")->execute([':txid' => $txid]); } catch (Exception $e) {}
    http_response_code(500);
    echo json_encode(["erro" => "Falha ao conectar com o gateway de pagamento: {$curlError}"], JSON_UNESCAPED_UNICODE);
    exit;
}

$mpData = json_decode((string)$mpResponse, true);

if ($httpCode >= 200 && $httpCode < 300 && isset($mpData['id'])) {
    $mpId = (string)$mpData['id'];
    $mpStatus = strtolower((string)($mpData['status'] ?? 'rejected'));
    $mpStatusDetail = strtolower((string)($mpData['status_detail'] ?? ''));
    
    if (isset($mpData['transaction_details']['total_paid_amount'])) {
        $totalPagoComJuros = (float)$mpData['transaction_details']['total_paid_amount'];
    }

    if (isset($mpData['card']) && is_array($mpData['card'])) {
        $cardFirstSix = $mpData['card']['first_six_digits'] ?? null;
        $cardLastFour = $mpData['card']['last_four_digits'] ?? null;
    }
} else {
    try { $pdo->prepare("UPDATE pedidos SET status = 'recusado', status_detail = 'gateway_error' WHERE txid = :txid AND status = 'pendente' LIMIT 1")->execute([':txid' => $txid]); } catch (Exception $e) {}
    $msgErro = $mpData['message'] ?? ($mpData['error'] ?? "Erro ao processar pagamento com cartão.");
    if (isset($mpData['cause']) && is_array($mpData['cause']) && !empty($mpData['cause'][0]['description'])) {
        $msgErro .= " (" . $mpData['cause'][0]['description'] . ")";
    }
    http_response_code(400);
    echo json_encode(["erro" => $msgErro], JSON_UNESCAPED_UNICODE);
    exit;
}

// 7. ATUALIZAÇÃO DO PEDIDO NO BANCO DE DADOS
$statusBd = ($mpStatus === 'approved') ? 'pago' : (($mpStatus === 'in_process') ? 'pendente' : 'recusado');
$pagoEm = ($statusBd === 'pago') ? date('Y-m-d H:i:s') : null;

try {
    $stmt = $pdo->prepare("
        UPDATE pedidos
        SET mp_payment_id = :mp_id,
            valor_total = :valor_total,
            status = :status,
            status_detail = :status_detail,
            card_first_six_digits = :card_six,
            card_last_four_digits = :card_four,
            pago_em = :pago_em
        WHERE txid = :txid
        LIMIT 1
    ");
    $stmt->execute([
        ':mp_id' => $mpId,
        ':valor_total' => $totalPagoComJuros,
        ':status' => $statusBd,
        ':status_detail' => $mpStatusDetail,
        ':card_six' => $cardFirstSix,
        ':card_four' => $cardLastFour,
        ':pago_em' => $pagoEm,
        ':txid' => $txid
    ]);
} catch (Exception $e) {
    error_log("Erro crítico ao atualizar pedido de cartão no BD: " . $e->getMessage());
    if ($statusBd === 'pago' && isset($mpData) && is_array($mpData) && isset($pdo) && $pdo instanceof PDO) {
        autoRecuperarPedidoMercadoPago($pdo, $txid, $mpId, $mpData);
    }
}

// 8. SE APROVADO IMEDIATAMENTE: EXECUTA CÔMPUTO DE CUPOM, NOTIFICAÇÃO E ENTREGA
if ($statusBd === 'pago') {
    // Computa cupom de forma idempotente
    registrarUsoCupomSePago($pdo, $txid);

    try {
        enviarNotificacaoCompraDiscord(
            $nick,
            $tipoConta,
            $servidor,
            $itemNome,
            $valorReal,
            $txid,
            '#7DB9DF',
            'cartao',
            $installments,
            $totalPagoComJuros,
            $cupomCodigo,
            $descontoAplicado,
            $tipoProduto,
            $quantidade
        );
    } catch (Exception $e) {
        error_log("Erro ao disparar webhook Discord: " . $e->getMessage());
    }

    // Dispara entrega do produto
    try {
        $pedidoCriado = [
            'id'           => $novoPedidoId ?: $txid,
            'nick'         => $nick,
            'servidor'     => $servidor,
            'tipo_produto' => $tipoProduto,
            'quantidade'   => $quantidade,
            'chave_id'     => $dbChaveId,
            'vip_id'       => $dbVipId,
            'vip_nome'     => $itemNome,
            'txid'         => $txid
        ];
        enviarEntregaVip($pedidoCriado, $pdo);
    } catch (Exception $e) {
        error_log("Erro ao disparar entrega no criar_cartao: " . $e->getMessage());
    }
}

// 9. RESPOSTA FINAL AO CLIENTE
if ($mpStatus === 'approved') {
    echo json_encode([
        "success" => true,
        "status" => "approved",
        "txid" => $txid,
        "mp_id" => $mpId,
        "nick" => $nick,
        "tipo_produto" => $tipoProduto,
        "quantidade" => $quantidade,
        "servidor" => $servidor,
        "vip_nome" => $itemNome,
        "item_nome" => $itemNome,
        "valor" => $valorReal,
        "valor_original" => $valorOriginal,
        "desconto_aplicado" => $descontoAplicado,
        "cupom" => $cupomCodigo,
        "valor_total" => $totalPagoComJuros,
        "parcelas" => $installments,
        "mensagem" => "Pagamento aprovado com sucesso! Seus itens foram liberados no servidor."
    ], JSON_UNESCAPED_UNICODE);
} elseif ($mpStatus === 'in_process') {
    echo json_encode([
        "success" => true,
        "status" => "in_process",
        "txid" => $txid,
        "mp_id" => $mpId,
        "nick" => $nick,
        "tipo_produto" => $tipoProduto,
        "quantidade" => $quantidade,
        "servidor" => $servidor,
        "vip_nome" => $itemNome,
        "item_nome" => $itemNome,
        "valor" => $valorReal,
        "valor_original" => $valorOriginal,
        "desconto_aplicado" => $descontoAplicado,
        "cupom" => $cupomCodigo,
        "mensagem" => "Pagamento em análise de segurança. Seus itens serão liberados automaticamente assim que o Mercado Pago aprovar."
    ], JSON_UNESCAPED_UNICODE);
} else {
    $msgAmigavel = mapearStatusDetailMercadoPago($mpStatusDetail);
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "status" => "rejected",
        "status_detail" => $mpStatusDetail,
        "erro" => $msgAmigavel
    ], JSON_UNESCAPED_UNICODE);
}

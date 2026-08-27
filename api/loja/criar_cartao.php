<?php
/**
 * criar_cartao.php
 * Cria a cobrança de Cartão de Crédito via API do Mercado Pago e registra o pedido no banco de dados.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization");

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
require_once __DIR__ . "/discord_loja_helper.php";
verificarAcessoApi();

// Lê os dados recebidos via JSON (ou POST)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

// 1. RATE LIMITING / ANTI-CARDING (Prevenção contra bots e testagem de cartões)
$clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
if (strpos($clientIp, ',') !== false) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}

$cpfRaw = trim($data['cpf'] ?? '');
$cpfLimpo = preg_replace('/\D/', '', $cpfRaw);

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        // Limpa registros antigos com mais de 1 hora
        $pdo->exec("DELETE FROM rate_limits_loja WHERE tentativa_em < (NOW() - INTERVAL 1 HOUR)");

        // Conta tentativas nos últimos 10 minutos para este IP ou CPF
        $stmtRl = $pdo->prepare("
            SELECT COUNT(*) FROM rate_limits_loja 
            WHERE (ip = :ip OR (cpf = :cpf AND :cpf != '')) 
              AND endpoint = 'criar_cartao' 
              AND tentativa_em >= (NOW() - INTERVAL 10 MINUTE)
        ");
        $stmtRl->execute([':ip' => $clientIp, ':cpf' => $cpfLimpo]);
        $tentativasRecentes = (int)$stmtRl->fetchColumn();

        if ($tentativasRecentes >= 5) {
            http_response_code(429);
            echo json_encode([
                "erro" => "Muitas tentativas de pagamento recentes. Por favor, aguarde 10 minutos antes de tentar novamente ou utilize o pagamento via PIX."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Registra a tentativa atual
        $stmtLog = $pdo->prepare("INSERT INTO rate_limits_loja (ip, cpf, endpoint, tentativa_em) VALUES (:ip, :cpf, 'criar_cartao', NOW())");
        $stmtLog->execute([':ip' => $clientIp, ':cpf' => $cpfLimpo]);
    }
} catch (Exception $e) {
    error_log("Erro no rate limiting de cartão: " . $e->getMessage());
}

// 2. EXTRAÇÃO E VALIDAÇÃO DOS DADOS
$nick = trim($data['nick'] ?? '');
$tipoConta = strtolower(trim($data['tipo_conta'] ?? 'original'));
$servidor = trim($data['servidor'] ?? '');
$vipId = (int)($data['vip_id'] ?? 0);

$token = trim($data['token'] ?? '');
$installments = (int)($data['installments'] ?? 1);
$paymentMethodId = strtolower(trim($data['payment_method_id'] ?? ''));
$issuerId = trim($data['issuer_id'] ?? '');
$deviceId = trim($data['device_id'] ?? '');
$cardholderName = trim($data['cardholder_name'] ?? '');
$email = trim($data['email'] ?? '');

// Validações básicas
if (empty($nick) || strlen($nick) < 3 || strlen($nick) > 16 || !preg_match('/^[a-zA-Z0-9_]+$/', $nick)) {
    http_response_code(400);
    echo json_encode(["erro" => "Nick inválido. Use de 3 a 16 caracteres alfanuméricos ou underline."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($tipoConta, ['original', 'pirata'])) {
    $tipoConta = 'original';
}

if (empty($servidor)) {
    http_response_code(400);
    echo json_encode(["erro" => "Selecione o servidor onde deseja receber o VIP."], JSON_UNESCAPED_UNICODE);
    exit;
}

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

// 3. DIVISÃO DO NOME (first_name e last_name para maximizar antifraude)
$partesNome = preg_split('/\s+/', $cardholderName);
$firstName = array_shift($partesNome);
$lastName = !empty($partesNome) ? implode(' ', $partesNome) : $firstName;

// 4. REVALIDAÇÃO DO PREÇO REAL NO BANCO DE DADOS (Nunca confiar no front)
$valorReal = 0.00;
$vipNome = '';

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmtVip = $pdo->prepare("SELECT nome, preco FROM vips WHERE id = :id AND (ativo = 1 OR ativo IS NULL) LIMIT 1");
        $stmtVip->execute([':id' => $vipId]);
        $vipRow = $stmtVip->fetch(PDO::FETCH_ASSOC);

        if ($vipRow) {
            $valorReal = (float)$vipRow['preco'];
            $vipNome = $vipRow['nome'];
        }
    }
} catch (Exception $e) {
    error_log("Erro ao buscar preço do VIP no BD: " . $e->getMessage());
}

// Fallback caso não encontre no BD
if ($valorReal <= 0) {
    $precosDefault = [
        1 => ["nome" => "VIP Carvão", "preco" => 20.00],
        2 => ["nome" => "VIP Ferro", "preco" => 40.00],
        3 => ["nome" => "VIP Ouro", "preco" => 60.00],
        4 => ["nome" => "VIP Diamante", "preco" => 80.00],
        5 => ["nome" => "VIP Netherita", "preco" => 120.00]
    ];
    if (isset($precosDefault[$vipId])) {
        $valorReal = (float)$precosDefault[$vipId]['preco'];
        $vipNome = $precosDefault[$vipId]['nome'];
    }
}

if ($valorReal <= 0) {
    http_response_code(400);
    echo json_encode(["erro" => "Pacote VIP não encontrado ou com valor inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($vipNome)) {
    $vipNome = "VIP " . ucfirst($servidor);
}

// 5. MAPEAMENTO AMIGÁVEL DE ERROS DE REJEIÇÃO (status_detail)
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

// 6. GERAÇÃO DO IDENTIFICADOR ÚNICO (txid)
$txid = "VIP-CARD-" . strtoupper(bin2hex(random_bytes(6)));

$mpAccessToken = defined('MERCADO_PAGO_ACCESS_TOKEN') ? trim(MERCADO_PAGO_ACCESS_TOKEN) : '';
$isLiveMpToken = !empty($mpAccessToken) && strpos($mpAccessToken, 'APP_USR-SEU-ACCESS-TOKEN') === false;

$mpId = null;
$mpStatus = 'rejected';
$mpStatusDetail = 'cc_rejected_other';
$totalPagoComJuros = $valorReal;
$cardFirstSix = substr($data['card_number'] ?? '', 0, 6);
$cardLastFour = substr($data['card_number'] ?? '', -4);

if ($isLiveMpToken) {
    $mpUrl = "https://api.mercadopago.com/v1/payments";

    $mpPayload = [
        "transaction_amount" => (float)$valorReal,
        "token" => $token,
        "description" => "VIP {$vipNome} - Nick: {$nick} ({$servidor})",
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
        "external_reference" => $txid,
        "metadata" => [
            "txid" => $txid,
            "nick" => $nick,
            "tipo_conta" => $tipoConta,
            "servidor" => $servidor,
            "vip_id" => $vipId,
            "vip_nome" => $vipNome,
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
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 20
    ]);

    $mpResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        http_response_code(500);
        echo json_encode(["erro" => "Falha ao conectar com o gateway de pagamento: {$curlError}"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mpData = json_decode($mpResponse, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($mpData['id'])) {
        $mpId = (string)$mpData['id'];
        $mpStatus = strtolower($mpData['status'] ?? 'rejected');
        $mpStatusDetail = strtolower($mpData['status_detail'] ?? '');
        
        if (isset($mpData['transaction_details']['total_paid_amount'])) {
            $totalPagoComJuros = (float)$mpData['transaction_details']['total_paid_amount'];
        }

        if (isset($mpData['card'])) {
            $cardFirstSix = $mpData['card']['first_six_digits'] ?? $cardFirstSix;
            $cardLastFour = $mpData['card']['last_four_digits'] ?? $cardLastFour;
        }
    } else {
        $msgErro = $mpData['message'] ?? ($mpData['error'] ?? "Erro ao processar pagamento com cartão.");
        if (isset($mpData['cause']) && is_array($mpData['cause']) && !empty($mpData['cause'][0]['description'])) {
            $msgErro .= " (" . $mpData['cause'][0]['description'] . ")";
        }
        http_response_code(400);
        echo json_encode(["erro" => $msgErro], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else {
    // Modo Demonstração / Sandbox (Permite testar fluxo visualmente sem credencial de produção)
    $mpId = "DEMO_CARD_" . time();
    $mpStatus = 'approved';
    $mpStatusDetail = 'accredited';
    $totalPagoComJuros = $valorReal;
}

// 7. REGISTRO NO BANCO DE DADOS
$statusBd = ($mpStatus === 'approved') ? 'pago' : (($mpStatus === 'in_process') ? 'pendente' : 'recusado');
$pagoEm = ($statusBd === 'pago') ? date('Y-m-d H:i:s') : null;

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("
            INSERT INTO pedidos_vip (
                txid, mp_payment_id, nick, payer_email, payer_cpf, tipo_conta, servidor, 
                vip_id, vip_nome, valor, valor_total, status, status_detail, metodo_pagamento, 
                parcelas, card_first_six_digits, card_last_four_digits, card_payment_method_id, criado_em, pago_em
            ) VALUES (
                :txid, :mp_id, :nick, :email, :cpf, :tipo_conta, :servidor,
                :vip_id, :vip_nome, :valor, :valor_total, :status, :status_detail, 'cartao',
                :parcelas, :card_six, :card_four, :method_id, NOW(), :pago_em
            )
        ");
        $stmt->execute([
            ':txid' => $txid,
            ':mp_id' => $mpId,
            ':nick' => $nick,
            ':email' => $email,
            ':cpf' => $cpfLimpo,
            ':tipo_conta' => $tipoConta,
            ':servidor' => $servidor,
            ':vip_id' => $vipId,
            ':vip_nome' => $vipNome,
            ':valor' => $valorReal,
            ':valor_total' => $totalPagoComJuros,
            ':status' => $statusBd,
            ':status_detail' => $mpStatusDetail,
            ':parcelas' => $installments,
            ':card_six' => $cardFirstSix,
            ':card_four' => $cardLastFour,
            ':method_id' => $paymentMethodId,
            ':pago_em' => $pagoEm
        ]);
    }
} catch (Exception $e) {
    error_log("Erro ao salvar pedido de cartão no BD: " . $e->getMessage());
}

// 8. SE APROVADO IMEDIATAMENTE: DISPARA NOTIFICAÇÃO NO DISCORD
if ($statusBd === 'pago') {
    try {
        enviarNotificacaoCompraDiscord(
            $nick,
            $tipoConta,
            $servidor,
            $vipNome,
            $valorReal,
            $txid,
            '#7DB9DF',
            'cartao',
            $installments,
            $totalPagoComJuros
        );
    } catch (Exception $e) {
        error_log("Erro ao disparar webhook Discord: " . $e->getMessage());
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
        "servidor" => $servidor,
        "vip_nome" => $vipNome,
        "valor" => $valorReal,
        "valor_total" => $totalPagoComJuros,
        "parcelas" => $installments,
        "mensagem" => "Pagamento aprovado com sucesso! Seu VIP foi liberado no servidor."
    ], JSON_UNESCAPED_UNICODE);
} elseif ($mpStatus === 'in_process') {
    echo json_encode([
        "success" => true,
        "status" => "in_process",
        "txid" => $txid,
        "mp_id" => $mpId,
        "nick" => $nick,
        "servidor" => $servidor,
        "vip_nome" => $vipNome,
        "mensagem" => "Pagamento em análise de segurança. Seu VIP será liberado automaticamente assim que o Mercado Pago aprovar."
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

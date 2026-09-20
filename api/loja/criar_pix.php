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

// Lê os dados recebidos via JSON (ou POST)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$nick = trim((string)($data['nick'] ?? ''));
$tipoConta = strtolower(trim((string)($data['tipo_conta'] ?? 'original')));
$servidor = trim((string)($data['servidor'] ?? ''));
$vipId = (int)($data['vip_id'] ?? 0);
$vipNome = trim((string)($data['vip_nome'] ?? ''));
$valor = (float)($data['valor'] ?? 0);

// Validação dos campos obrigatórios
if (empty($nick) || strlen($nick) < 3 || strlen($nick) > 16 || !preg_match('/^[a-zA-Z0-9_]+$/', $nick)) {
    http_response_code(400);
    echo json_encode(["erro" => "Nick inválido. Use de 3 a 16 caracteres alfanuméricos ou underline."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($tipoConta, ['original', 'pirata'], true)) {
    $tipoConta = 'original';
}

if (empty($servidor)) {
    http_response_code(400);
    echo json_encode(["erro" => "Selecione o servidor onde deseja receber o VIP."], JSON_UNESCAPED_UNICODE);
    exit;
}

$cupomEnviado = strtoupper(trim((string)($data['cupom'] ?? '')));
$cupomCodigo = null;
$valorOriginal = $valor;
$descontoAplicado = 0.00;

// Validação e busca estrita do VIP no banco de dados
if ($vipId <= 0) {
    http_response_code(400);
    echo json_encode(["erro" => "Identificador do VIP inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["erro" => "Conexão com o banco de dados indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmtVip = $pdo->prepare("SELECT id, nome, preco, servidor_id FROM vips WHERE id = :id AND (ativo = 1 OR ativo IS NULL) LIMIT 1");
$stmtVip->execute([':id' => $vipId]);
$vipRow = $stmtVip->fetch(PDO::FETCH_ASSOC);

if (!$vipRow) {
    http_response_code(404);
    echo json_encode(["erro" => "Pacote VIP não encontrado ou inativo."], JSON_UNESCAPED_UNICODE);
    exit;
}

$valorOriginal = (float)$vipRow['preco'];
$vipNome = (string)$vipRow['nome'];
$valor = $valorOriginal;

if ($valor <= 0) {
    http_response_code(400);
    echo json_encode(["erro" => "Valor do pacote VIP inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validação do Servidor e correspondência com o VIP
$srvSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $servidor));
$stmtCheckSrv = $pdo->prepare("SELECT id, servername, nome, enabled FROM servidores WHERE (servername = :srv OR nome = :srvSlug) LIMIT 1");
$stmtCheckSrv->execute([':srv' => $servidor, ':srvSlug' => $srvSlug]);
$srvRow = $stmtCheckSrv->fetch(PDO::FETCH_ASSOC);

if (!$srvRow || (isset($srvRow['enabled']) && (int)$srvRow['enabled'] === 0)) {
    http_response_code(400);
    echo json_encode(["erro" => "O servidor selecionado é inválido ou está desabilitado."], JSON_UNESCAPED_UNICODE);
    exit;
}

$servidorId = (int)$srvRow['id'];
$servidor = (string)$srvRow['servername'];

// Confere se o servidor do VIP corresponde ao servidor selecionado
$vipServidorId = (int)($vipRow['servidor_id'] ?? 0);
if ($vipServidorId > 0 && $vipServidorId !== $servidorId) {
    http_response_code(400);
    echo json_encode(["erro" => "O pacote VIP selecionado não pertence ao servidor {$servidor}."], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validação de Cupom de Desconto se fornecido
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
            echo json_encode(["erro" => "O cupom '{$cupomEnviado}' está desativado."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($isExpirado) {
            http_response_code(400);
            echo json_encode(["erro" => "O cupom '{$cupomEnviado}' expirou em " . date('d/m/Y H:i', $expiraTs) . "."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verifica restrição de servidor do cupom
        $cupomServidorId = (int)($cupomRow['servidor_id'] ?? 0);
        if ($cupomServidorId > 0 && $cupomServidorId !== $servidorId) {
            $stmtSrvNome = $pdo->prepare("SELECT servername FROM servidores WHERE id = :id LIMIT 1");
            $stmtSrvNome->execute([':id' => $cupomServidorId]);
            $srvNome = $stmtSrvNome->fetchColumn() ?: 'outro servidor';

            http_response_code(400);
            echo json_encode(["erro" => "O cupom '{$cupomEnviado}' é válido exclusivamente para compras no servidor {$srvNome}."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $porcentagem = (float)$cupomRow['porcentagem_desconto'];
        $descontoAplicado = round($valorOriginal * ($porcentagem / 100), 2);
        $valor = max(0.01, round($valorOriginal - $descontoAplicado, 2));
        $cupomCodigo = (string)$cupomRow['codigo'];
    } else {
        http_response_code(404);
        echo json_encode(["erro" => "Cupom '{$cupomEnviado}' não encontrado."], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Gera identificador único para o pedido
$txid = "NERD-" . strtoupper(substr(md5(uniqid($nick . time(), true)), 0, 16));

// Token do Mercado Pago
$mpAccessToken = obterMercadoPagoAccessToken();
if (!$mpAccessToken) {
    responder503ServicoIndisponivel("MERCADO_PAGO_ACCESS_TOKEN ausente ou inválido no criar_pix.");
}

$siteUrl = obterSiteUrl();
$paisIp = obterPaisIp();

// Comunicação real com a API do Mercado Pago
$mpUrl = "https://api.mercadopago.com/v1/payments";

$mpPayload = [
    "transaction_amount" => (float)$valor,
    "description" => "RedeNerds - {$vipNome} ({$servidor}) - Jogador: {$nick}",
    "payment_method_id" => "pix",
    "payer" => [
        "email" => "pagamento.{$nick}@redenerds.com.br",
        "first_name" => $nick,
        "last_name" => "Player"
    ],
    "notification_url" => $siteUrl . "/api/loja/webhook_mercadopago.php",
    "external_reference" => $txid
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
    $msgErro = $mpData['message'] ?? ($mpData['error'] ?? "Erro na criação do PIX no Mercado Pago.");
    if (isset($mpData['cause']) && is_array($mpData['cause']) && !empty($mpData['cause'][0]['description'])) {
        $msgErro .= " (" . $mpData['cause'][0]['description'] . ")";
    }
    http_response_code(400);
    echo json_encode(["erro" => $msgErro], JSON_UNESCAPED_UNICODE);
    exit;
}

// Salva o pedido no banco de dados
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("
            INSERT INTO pedidos_vip (
                txid, mp_payment_id, nick, pais_ip, tipo_conta, servidor, vip_id, vip_nome, cupom_codigo, 
                valor, valor_original, desconto_aplicado, status, metodo_pagamento, 
                pix_copia_cola, pix_qr_base64, cupom_computado, criado_em
            ) VALUES (
                :txid, :mp_id, :nick, :pais_ip, :tipo_conta, :servidor, :vip_id, :vip_nome, :cupom_codigo, 
                :valor, :valor_original, :desconto_aplicado, 'pendente', 'pix', 
                :pix_copia_cola, :pix_qr_base64, 0, NOW()
            )
        ");
        $stmt->execute([
            ':txid' => $txid,
            ':mp_id' => $mpId,
            ':nick' => $nick,
            ':pais_ip' => $paisIp,
            ':tipo_conta' => $tipoConta,
            ':servidor' => $servidor,
            ':vip_id' => $vipId,
            ':vip_nome' => $vipNome,
            ':cupom_codigo' => $cupomCodigo,
            ':valor' => $valor,
            ':valor_original' => $valorOriginal,
            ':desconto_aplicado' => $descontoAplicado,
            ':pix_copia_cola' => $pixCopiaCola,
            ':pix_qr_base64' => $pixQrBase64
        ]);
    }
} catch (Exception $e) {
    error_log("Erro ao salvar pedido no MySQL: " . $e->getMessage());
}

$expiraEm = date('c', time() + (15 * 60)); // 15 minutos

echo json_encode([
    "success" => true,
    "txid" => $txid,
    "mp_id" => $mpId,
    "nick" => $nick,
    "tipo_conta" => $tipoConta,
    "servidor" => $servidor,
    "vip_nome" => $vipNome,
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

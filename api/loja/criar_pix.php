<?php
/**
 * criar_pix.php
 * Cria a cobrança PIX via API do Mercado Pago e registra o pedido no banco de dados.
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
verificarAcessoApi();

// Lê os dados recebidos via JSON (ou POST)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$nick = trim($data['nick'] ?? '');
$tipoConta = strtolower(trim($data['tipo_conta'] ?? 'original'));
$servidor = trim($data['servidor'] ?? '');
$vipId = (int)($data['vip_id'] ?? 0);
$vipNome = trim($data['vip_nome'] ?? '');
$valor = (float)($data['valor'] ?? 0);

// Validação dos campos obrigatórios
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

$cupomEnviado = strtoupper(trim($data['cupom'] ?? ''));
$cupomCodigo = null;
$valorOriginal = $valor;
$descontoAplicado = 0.00;

// Revalidação de preço do VIP e aplicação de Cupom no banco de dados
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        if ($vipId > 0) {
            $stmtVip = $pdo->prepare("SELECT nome, preco FROM vips WHERE id = :id AND (ativo = 1 OR ativo IS NULL) LIMIT 1");
            $stmtVip->execute([':id' => $vipId]);
            $vipRow = $stmtVip->fetch(PDO::FETCH_ASSOC);
            if ($vipRow) {
                $valorOriginal = (float)$vipRow['preco'];
                $vipNome = $vipRow['nome'];
                $valor = $valorOriginal;
            }
        }

        // Valida Cupom de Desconto se fornecido
        if (!empty($cupomEnviado)) {
            $stmtCupom = $pdo->prepare("SELECT * FROM cupons WHERE codigo = :codigo LIMIT 1");
            $stmtCupom->execute([':codigo' => $cupomEnviado]);
            $cupomRow = $stmtCupom->fetch(PDO::FETCH_ASSOC);

            if ($cupomRow) {
                $now = time();
                $expiraTs = strtotime($cupomRow['expira_em']);
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

                $porcentagem = (float)$cupomRow['porcentagem_desconto'];
                $descontoAplicado = round($valorOriginal * ($porcentagem / 100), 2);
                $valor = max(0.01, round($valorOriginal - $descontoAplicado, 2));
                $cupomCodigo = $cupomRow['codigo'];

                // Incrementa contador de usos
                $pdo->prepare("UPDATE cupons SET usos_total = usos_total + 1 WHERE id = :id")->execute([':id' => $cupomRow['id']]);
            } else {
                http_response_code(404);
                echo json_encode(["erro" => "Cupom '{$cupomEnviado}' não encontrado."], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
} catch (Exception $e) {
    error_log("Erro ao revalidar VIP/Cupom no criar_pix: " . $e->getMessage());
}

// Valida se o servidor está habilitado (enabled = 1) no banco de dados
try {
    $srvSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $servidor));
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmtCheck = $pdo->prepare("SELECT id, servername, enabled FROM servidores WHERE (servername = :srv OR nome = :srvSlug) LIMIT 1");
        $stmtCheck->execute([':srv' => $servidor, ':srvSlug' => $srvSlug]);
        $srvRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($srvRow) {
            if (isset($srvRow['enabled']) && (int)$srvRow['enabled'] === 0) {
                http_response_code(400);
                echo json_encode(["erro" => "As compras para este servidor estão temporariamente desabilitadas."], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $servidor = $srvRow['servername'];
        }
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $stmtCheck = $conn->prepare("SELECT id, servername, enabled FROM servidores WHERE (servername = ? OR nome = ?) LIMIT 1");
        if ($stmtCheck) {
            $stmtCheck->bind_param("ss", $servidor, $srvSlug);
            $stmtCheck->execute();
            $res = $stmtCheck->get_result();
            if ($res && $srvRow = $res->fetch_assoc()) {
                if (isset($srvRow['enabled']) && (int)$srvRow['enabled'] === 0) {
                    http_response_code(400);
                    echo json_encode(["erro" => "As compras para este servidor estão temporariamente desabilitadas."], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $servidor = $srvRow['servername'];
            }
        }
    }
} catch (Exception $e) {
    // Prossegue normalmente se a tabela não estiver disponível
}

// Gera identificador único para o pedido
$txid = "NERD-" . strtoupper(substr(md5(uniqid($nick . time(), true)), 0, 16));

// Token do Mercado Pago
$mpAccessToken = defined('MERCADO_PAGO_ACCESS_TOKEN') ? trim(MERCADO_PAGO_ACCESS_TOKEN) : '';

$mpId = null;
$pixCopiaCola = null;
$pixQrBase64 = null;

$isLiveMpToken = (!empty($mpAccessToken) && strpos($mpAccessToken, 'APP_USR-SEU-ACCESS-TOKEN') === false);

if ($isLiveMpToken) {
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
        "notification_url" => "https://redenerds.com.br/api/loja/webhook_mercadopago.php",
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
        CURLOPT_SSL_VERIFYPEER => false,
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

    $mpData = json_decode($mpResponse, true);

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
} else {
    // Modo Demonstração / Sandbox (Permite testar o modal visualmente antes de colocar o token)
    $mpId = "DEMO_" . time();
    $pixCopiaCola = "00020126580014br.gov.bcb.pix0136" . $txid . "520400005303986540" . number_format($valor, 2, '.', '') . "5802BR5909RedeNerds6009Sao Paulo62070503***6304ABCD";
    
    // QR code placeholder SVG gerado em base64
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200"><rect width="200" height="200" fill="#ffffff"/><rect x="20" y="20" width="50" height="50" fill="#030407"/><rect x="30" y="30" width="30" height="30" fill="#ffffff"/><rect x="38" y="38" width="14" height="14" fill="#030407"/><rect x="130" y="20" width="50" height="50" fill="#030407"/><rect x="140" y="30" width="30" height="30" fill="#ffffff"/><rect x="148" y="38" width="14" height="14" fill="#030407"/><rect x="20" y="130" width="50" height="50" fill="#030407"/><rect x="30" y="140" width="30" height="30" fill="#ffffff"/><rect x="38" y="148" width="14" height="14" fill="#030407"/><circle cx="100" cy="100" r="14" fill="#7DB9DF"/></svg>';
    $pixQrBase64 = base64_encode($svg);
}

// Salva o pedido no banco de dados
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("
            INSERT INTO pedidos_vip (
                txid, nick, tipo_conta, servidor, vip_id, vip_nome, cupom_codigo, 
                valor, valor_original, desconto_aplicado, status, metodo_pagamento, 
                pix_copia_cola, pix_qr_base64, criado_em
            ) VALUES (
                :txid, :nick, :tipo_conta, :servidor, :vip_id, :vip_nome, :cupom_codigo, 
                :valor, :valor_original, :desconto_aplicado, 'pendente', 'pix', 
                :pix_copia_cola, :pix_qr_base64, NOW()
            )
        ");
        $stmt->execute([
            ':txid' => $txid,
            ':nick' => $nick,
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
    "modo_demo" => !$isLiveMpToken
], JSON_UNESCAPED_UNICODE);

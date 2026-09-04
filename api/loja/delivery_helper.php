<?php
/**
 * delivery_helper.php
 * Helper responsável pelo envio e integração automática de entrega de VIPs
 */

$configPaths = [
    __DIR__ . "/../../../config.php",
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php",
    __DIR__ . "/config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        require_once $cp;
        break;
    }
}

if (!defined('DELIVERY_API_URL')) {
    define('DELIVERY_API_URL', 'http://br2.xmxcloud.net:10504/api/v1/deliveries');
}

if (!defined('DELIVERY_API_TOKEN')) {
    define('DELIVERY_API_TOKEN', '');
}

/**
 * Envia uma ordem de entrega de VIP para a API do servidor Minecraft.
 *
 * @param array|int|string $pedido Dados do pedido (array) ou ID/txid do pedido
 * @param PDO|null $pdo Conexão PDO (opcional)
 * @return array ['success' => bool, 'http_code' => int, 'response' => mixed, 'error' => string|null]
 */
function enviarEntregaVip($pedido, $pdo = null) {
    if (!$pdo) {
        global $pdo;
    }

    // Se foi passado apenas o ID ou TXID, busca os dados completos no banco
    if (!is_array($pedido) && $pdo instanceof PDO) {
        $idOrTxid = trim((string)$pedido);
        if (is_numeric($idOrTxid)) {
            $stmt = $pdo->prepare("SELECT * FROM pedidos_vip WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int)$idOrTxid]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM pedidos_vip WHERE txid = :txid LIMIT 1");
            $stmt->execute([':txid' => $idOrTxid]);
        }
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (empty($pedido) || !is_array($pedido)) {
        error_log("ERRO DELIVERY: Pedido inválido ou não encontrado para entrega.");
        return [
            'success' => false,
            'http_code' => 0,
            'response' => null,
            'error' => 'Pedido não encontrado'
        ];
    }

    // 1. Order ID (identificador do pedido gerado pelo sistema como NERD-... ou Mercado Pago ID)
    $orderId = '';
    if (!empty($pedido['txid'])) {
        $orderId = (string)$pedido['txid'];
    } elseif (!empty($pedido['orderId'])) {
        $orderId = (string)$pedido['orderId'];
    } elseif (!empty($pedido['mp_payment_id'])) {
        $orderId = (string)$pedido['mp_payment_id'];
    } elseif (!empty($pedido['id'])) {
        $orderId = (string)$pedido['id'];
    }

    // 2. Player (Nick do usuário)
    $player = trim((string)($pedido['nick'] ?? ($pedido['player'] ?? '')));

    // 3. Server Slug (Puxa o slug 'nome' do banco de dados na tabela servidores)
    $serverSlug = '';
    $rawServidor = trim((string)($pedido['servidor'] ?? ($pedido['server'] ?? '')));

    if ($pdo instanceof PDO && !empty($rawServidor)) {
        try {
            $stmtSrv = $pdo->prepare("SELECT nome FROM servidores WHERE servername = :srv OR nome = :srv LIMIT 1");
            $stmtSrv->execute([':srv' => $rawServidor]);
            $srvRow = $stmtSrv->fetch(PDO::FETCH_ASSOC);
            if ($srvRow && !empty($srvRow['nome'])) {
                $serverSlug = $srvRow['nome'];
            }
        } catch (Exception $e) {
            error_log("AVISO DELIVERY: Erro ao buscar slug do servidor: " . $e->getMessage());
        }
    }

    if (empty($serverSlug)) {
        $serverSlug = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $rawServidor));
    }

    // 4. Package ID (Coluna packageId da tabela de vips)
    $packageId = '';
    $vipId = $pedido['vip_id'] ?? null;
    $vipNome = $pedido['vip_nome'] ?? null;

    if ($pdo instanceof PDO) {
        try {
            if (!empty($vipId)) {
                $stmtVip = $pdo->prepare("SELECT packageId FROM vips WHERE id = :id LIMIT 1");
                $stmtVip->execute([':id' => (int)$vipId]);
                $vipRow = $stmtVip->fetch(PDO::FETCH_ASSOC);
                if ($vipRow && !empty($vipRow['packageId'])) {
                    $packageId = $vipRow['packageId'];
                }
            }
            if (empty($packageId) && !empty($vipNome)) {
                $stmtVip = $pdo->prepare("SELECT packageId FROM vips WHERE nome = :nome LIMIT 1");
                $stmtVip->execute([':nome' => $vipNome]);
                $vipRow = $stmtVip->fetch(PDO::FETCH_ASSOC);
                if ($vipRow && !empty($vipRow['packageId'])) {
                    $packageId = $vipRow['packageId'];
                }
            }
        } catch (Exception $e) {
            error_log("AVISO DELIVERY: Erro ao buscar packageId da tabela vips: " . $e->getMessage());
        }
    }

    if (empty($packageId)) {
        $packageId = (string)($pedido['packageId'] ?? ($vipId ?? ''));
    }

    // Monta o payload JSON exatamente conforme especificado
    $payload = [
        "server"    => (string)$serverSlug,
        "orderId"   => (string)$orderId,
        "packageId" => (string)$packageId,
        "player"    => (string)$player
    ];

    $deliveryUrl = defined('DELIVERY_API_URL') && !empty(DELIVERY_API_URL) 
        ? DELIVERY_API_URL 
        : 'http://br2.xmxcloud.net:10504/api/v1/deliveries';

    $deliveryToken = defined('DELIVERY_API_TOKEN') ? trim(DELIVERY_API_TOKEN) : '';

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if (!empty($deliveryToken)) {
        $headers[] = 'Authorization: Bearer ' . $deliveryToken;
        $headers[] = 'Authorization: ' . $deliveryToken;
        $headers[] = 'token: ' . $deliveryToken;
        $headers[] = 'X-Token: ' . $deliveryToken;
    }

    $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init($deliveryUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    $sucesso = ($httpCode >= 200 && $httpCode < 300);

    if ($sucesso) {
        error_log("DELIVERY SUCESSO: Pedido #{$orderId} enviado para '{$player}' ({$packageId} no servidor '{$serverSlug}') - HTTP {$httpCode}");
    } else {
        error_log("DELIVERY ERRO: Falha ao enviar Pedido #{$orderId} ({$packageId} no servidor '{$serverSlug}'): HTTP {$httpCode} - Erro: {$curlError} - Resposta: {$response}");
    }

    return [
        'success' => $sucesso,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $curlError ?: null
    ];
}
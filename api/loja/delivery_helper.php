<?php
/**
 * delivery_helper.php
 * Helper responsável pelo envio e integração automática de entrega de VIPs e Chaves.
 * As configurações de endpoint (URL e Token) ficam centralizadas exclusivamente no config.php.
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

if (!defined('DELIVERY_SERVERS')) {
    define('DELIVERY_SERVERS', []);
}

if (!defined('DELIVERY_API_URL')) {
    define('DELIVERY_API_URL', '');
}

if (!defined('DELIVERY_API_TOKEN')) {
    define('DELIVERY_API_TOKEN', '');
}


/**
 * Envia uma ordem de entrega de produto (VIP / Chave) para a API do servidor Minecraft.
 *
 * Estrutura do payload JSON enviado:
 * {
 *   "server": "slug-do-servidor",
 *   "orderId": "NERD-123456789",
 *   "packageId": "vip-mvp",
 *   "player": "NickDoJogador",
 *   "quantity": 1
 * }
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

    // 1. Order ID (identificador único do pedido, preferencialmente o txid ex: NERD-...)
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
    $servidorId = isset($pedido['servidor_id']) && is_numeric($pedido['servidor_id']) ? (int)$pedido['servidor_id'] : 0;
    $rawServidor = trim((string)($pedido['servidor'] ?? ($pedido['server'] ?? '')));

    if ($pdo instanceof PDO) {
        try {
            if ($servidorId > 0) {
                $stmtSrv = $pdo->prepare("SELECT nome FROM servidores WHERE id = :id LIMIT 1");
                $stmtSrv->execute([':id' => $servidorId]);
                $srvRow = $stmtSrv->fetch(PDO::FETCH_ASSOC);
                if ($srvRow && !empty($srvRow['nome'])) {
                    $serverSlug = (string)$srvRow['nome'];
                }
            }
            if (empty($serverSlug) && !empty($rawServidor)) {
                $stmtSrv = $pdo->prepare("SELECT nome FROM servidores WHERE servername = :srv OR nome = :srv LIMIT 1");
                $stmtSrv->execute([':srv' => $rawServidor]);
                $srvRow = $stmtSrv->fetch(PDO::FETCH_ASSOC);
                if ($srvRow && !empty($srvRow['nome'])) {
                    $serverSlug = (string)$srvRow['nome'];
                }
            }
        } catch (Exception $e) {
            error_log("AVISO DELIVERY: Erro ao buscar slug do servidor: " . $e->getMessage());
        }
    }

    if (empty($serverSlug)) {
        $serverSlug = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $rawServidor));
    }

    // 4. Package ID & Quantidade
    $packageId = '';
    $tipoProduto = strtolower(trim((string)($pedido['tipo_produto'] ?? 'vip')));
    $quantidade = (int)($pedido['quantidade'] ?? 1);
    if ($quantidade < 1) $quantidade = 1;

    $vipId = $pedido['vip_id'] ?? null;
    $chaveId = $pedido['chave_id'] ?? null;
    $itemNome = $pedido['vip_nome'] ?? ($pedido['item_nome'] ?? null);

    if ($pdo instanceof PDO) {
        try {
            if ($tipoProduto === 'chave' || !empty($chaveId)) {
                if (!empty($chaveId)) {
                    $stmtChave = $pdo->prepare("SELECT packageId FROM chaves WHERE id = :id LIMIT 1");
                    $stmtChave->execute([':id' => (int)$chaveId]);
                    $chaveRow = $stmtChave->fetch(PDO::FETCH_ASSOC);
                    if ($chaveRow && !empty($chaveRow['packageId'])) {
                        $packageId = $chaveRow['packageId'];
                    }
                }
                if (empty($packageId) && !empty($itemNome)) {
                    $stmtChave = $pdo->prepare("SELECT packageId FROM chaves WHERE nome = :nome LIMIT 1");
                    $stmtChave->execute([':nome' => $itemNome]);
                    $chaveRow = $stmtChave->fetch(PDO::FETCH_ASSOC);
                    if ($chaveRow && !empty($chaveRow['packageId'])) {
                        $packageId = $chaveRow['packageId'];
                    }
                }
            } else {
                if (!empty($vipId)) {
                    $stmtVip = $pdo->prepare("SELECT packageId FROM vips WHERE id = :id LIMIT 1");
                    $stmtVip->execute([':id' => (int)$vipId]);
                    $vipRow = $stmtVip->fetch(PDO::FETCH_ASSOC);
                    if ($vipRow && !empty($vipRow['packageId'])) {
                        $packageId = $vipRow['packageId'];
                    }
                }
                if (empty($packageId) && !empty($itemNome)) {
                    $stmtVip = $pdo->prepare("SELECT packageId FROM vips WHERE nome = :nome LIMIT 1");
                    $stmtVip->execute([':nome' => $itemNome]);
                    $vipRow = $stmtVip->fetch(PDO::FETCH_ASSOC);
                    if ($vipRow && !empty($vipRow['packageId'])) {
                        $packageId = $vipRow['packageId'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("AVISO DELIVERY: Erro ao buscar packageId da tabela vips/chaves: " . $e->getMessage());
        }
    }

    if (empty($packageId)) {
        $packageId = (string)($pedido['packageId'] ?? ($chaveId ?: ($vipId ?? '')));
    }

    // Monta o payload JSON exatamente na estrutura requerida pela API
    $payload = [
        "server"    => (string)$serverSlug,
        "orderId"   => (string)$orderId,
        "packageId" => (string)$packageId,
        "player"    => (string)$player,
        "quantity"  => (int)$quantidade
    ];

    // 5. Determina a URL e o Token específicos para o servidor de destino a partir do config.php
    $deliveryUrl = '';
    $deliveryToken = '';

    if (defined('DELIVERY_SERVERS') && is_array(DELIVERY_SERVERS)) {
        $slugNormalizado = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string)$serverSlug));

        foreach (DELIVERY_SERVERS as $srvKey => $srvConfig) {
            $keyNorm = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string)$srvKey));
            if ($keyNorm === $slugNormalizado || strcasecmp((string)$srvKey, (string)$serverSlug) === 0) {
                if (is_array($srvConfig) && !empty($srvConfig['url'])) {
                    $deliveryUrl = trim((string)$srvConfig['url']);
                    $deliveryToken = trim((string)($srvConfig['token'] ?? ''));
                    break;
                }
            }
        }
    }

    // Se não encontrou endpoint específico no array, utiliza o fallback geral
    if (empty($deliveryUrl) && defined('DELIVERY_API_URL') && !empty(DELIVERY_API_URL)) {
        $deliveryUrl = trim((string)DELIVERY_API_URL);
        $deliveryToken = defined('DELIVERY_API_TOKEN') ? trim((string)DELIVERY_API_TOKEN) : '';
    }

    if (empty($deliveryUrl)) {
        error_log("ERRO DELIVERY: Nenhuma URL de entrega configurada para o servidor '{$serverSlug}' no config.php.");
        return [
            'success' => false,
            'http_code' => 0,
            'response' => null,
            'error' => "URL de entrega não configurada para o servidor '{$serverSlug}' no config.php"
        ];
    }

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
        if ($pdo instanceof PDO && !empty($orderId)) {
            try {
                $upEntregue = $pdo->prepare("UPDATE pedidos_vip SET entregue = 1 WHERE txid = :txid OR id = :id");
                $upEntregue->execute([':txid' => $orderId, ':id' => (int)$orderId]);
            } catch (Exception $e) {
                error_log("AVISO DELIVERY: Falha ao marcar entregue = 1 no banco: " . $e->getMessage());
            }
        }
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
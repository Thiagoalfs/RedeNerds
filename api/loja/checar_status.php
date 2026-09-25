<?php
/**
 * checar_status.php
 * Verifica o status de pagamento do pedido (usado no polling pelo modal).
 * - PIX e Cartão Nacional: janela de 15 minutos.
 * - Checkout Pro Internacional: janela de 2 horas.
 * - Itera por todos os pagamentos da busca no Mercado Pago e realiza transição atômica se algum estiver 'approved'.
 */

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', '0');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config_loja.php";
aplicarCorsLoja();

require_once __DIR__ . "/../rate_limiter.php";
exigirRateLimit('loja_checar_status', 30, 60);

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
require_once __DIR__ . "/delivery_helper.php";
require_once __DIR__ . "/cupom_helper.php";
require_once __DIR__ . "/ip_helper.php";
verificarAcessoApi();

$txid = trim((string)($_GET['txid'] ?? ''));

if (empty($txid)) {
    http_response_code(400);
    echo json_encode(["erro" => "Identificador de transação (txid) não informado."], JSON_UNESCAPED_UNICODE);
    exit;
}

// Rate Limiting anti-abuso / anti-spam para polling de status (máx 60 requisições por IP por minuto)
$clientIp = obterIpRealCliente();
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->exec("DELETE FROM rate_limits_loja WHERE tentativa_em < (NOW() - INTERVAL 10 MINUTE)");

        $stmtRl = $pdo->prepare("
            SELECT COUNT(*) FROM rate_limits_loja 
            WHERE ip = :ip 
              AND endpoint = 'checar_status' 
              AND tentativa_em >= (NOW() - INTERVAL 1 MINUTE)
        ");
        $stmtRl->execute([':ip' => $clientIp]);
        $tentativasRecentes = (int)$stmtRl->fetchColumn();

        if ($tentativasRecentes >= 60) {
            http_response_code(429);
            echo json_encode([
                "erro" => "Muitas requisições de status. Por favor, aguarde alguns segundos antes de verificar novamente."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtLog = $pdo->prepare("INSERT INTO rate_limits_loja (ip, endpoint, tentativa_em) VALUES (:ip, 'checar_status', NOW())");
        $stmtLog->execute([':ip' => $clientIp]);
    }
} catch (Exception $e) {
    error_log("Erro no rate limiting de status: " . $e->getMessage());
}

// Auto-limpeza de pedidos expirados (15 min para PIX/Cartão, 2 horas para Checkout Pro)
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->exec("
            UPDATE pedidos 
            SET status = 'expirado' 
            WHERE status = 'pendente' 
              AND (metodo_pagamento IS NULL OR metodo_pagamento <> 'checkout_pro')
              AND TIMESTAMPDIFF(MINUTE, criado_em, NOW()) >= 15
        ");
        $pdo->exec("
            UPDATE pedidos 
            SET status = 'expirado' 
            WHERE status = 'pendente' 
              AND metodo_pagamento = 'checkout_pro'
              AND TIMESTAMPDIFF(HOUR, criado_em, NOW()) >= 2
        ");
    }
} catch (Exception $e) {
    // Ignora erro de limpeza silenciosamente
}

if (isset($pdo) && $pdo instanceof PDO) {
    garantirSchemaTabelaPedidos($pdo);
}

$pedido = null;

// 1. Busca o pedido no banco de dados local
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("
            SELECT *, TIMESTAMPDIFF(SECOND, criado_em, NOW()) as segundos_desde_criacao 
            FROM pedidos 
            WHERE txid = :txid 
            LIMIT 1
        ");
        $stmt->execute([':txid' => $txid]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erro ao buscar status do pedido: " . $e->getMessage());
}

// Se não encontrou o pedido localmente, tenta auto-recuperar consultando a API do Mercado Pago
if (!$pedido) {
    $mpAccessToken = obterMercadoPagoAccessToken();
    if ($mpAccessToken && isset($pdo) && $pdo instanceof PDO) {
        $searchUrl = "https://api.mercadopago.com/v1/payments/search?external_reference=" . urlencode($txid) . "&sort=date_created&criteria=desc";
        $ch = curl_init($searchUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$mpAccessToken}"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 8
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $response) {
            $searchData = json_decode((string)$response, true);
            $results = $searchData['results'] ?? [];

            foreach ($results as $paymentInfo) {
                if (strtolower((string)($paymentInfo['status'] ?? '')) === 'approved') {
                    $approvedPaymentId = $paymentInfo['id'] ?? null;
                    $inseriuNovo = autoRecuperarPedidoMercadoPago($pdo, $txid, $approvedPaymentId, $paymentInfo);

                    try {
                        $stmtFetch = $pdo->prepare("SELECT *, 0 as segundos_desde_criacao FROM pedidos WHERE txid = :txid LIMIT 1");
                        $stmtFetch->execute([':txid' => $txid]);
                        $pedido = $stmtFetch->fetch(PDO::FETCH_ASSOC);

                        if ($pedido) {
                            if ($inseriuNovo) {
                                try {
                                    enviarNotificacaoCompraDiscord(
                                        $pedido['nick'],
                                        $pedido['tipo_conta'] ?? 'original',
                                        $pedido['servidor'],
                                        $pedido['vip_nome'],
                                        (float)$pedido['valor'],
                                        $txid,
                                        '#7DB9DF',
                                        $pedido['metodo_pagamento'] ?? 'pix',
                                        1,
                                        null,
                                        null,
                                        0.00,
                                        $pedido['tipo_produto'] ?? 'vip',
                                        (int)($pedido['quantidade'] ?? 1)
                                    );
                                } catch (Exception $eDisc) {}

                                try {
                                    enviarEntregaVip($pedido, $pdo);
                                } catch (Exception $eEntr) {}
                            }

                            echo json_encode([
                                "success" => true,
                                "txid" => $txid,
                                "status" => "pago",
                                "aprovado" => true,
                                "pago_em" => date('c'),
                                "nick" => $pedido['nick'],
                                "servidor" => $pedido['servidor'],
                                "vip_nome" => $pedido['vip_nome']
                            ], JSON_UNESCAPED_UNICODE);
                            exit;
                        }
                    } catch (Exception $eFetch) {
                        error_log("Erro ao buscar pedido recuperado: " . $eFetch->getMessage());
                    }
                    break;
                }
            }
        }
    }

    if (!$pedido) {
        echo json_encode([
            "success" => true,
            "txid" => $txid,
            "status" => "pendente",
            "aprovado" => false
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$statusAtual = strtolower((string)($pedido['status'] ?? 'pendente'));
$metodo = strtolower((string)($pedido['metodo_pagamento'] ?? 'pix'));
$limiteSegundos = ($metodo === 'checkout_pro') ? 7200 : 900; // 2 horas vs 15 minutos

// 2. Se já estiver marcado como 'pago' no banco
if ($statusAtual === 'pago') {
    echo json_encode([
        "success" => true,
        "txid" => $txid,
        "status" => "pago",
        "aprovado" => true,
        "pago_em" => $pedido['pago_em'] ?? date('c'),
        "nick" => $pedido['nick'],
        "servidor" => $pedido['servidor'],
        "vip_nome" => $pedido['vip_nome']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Se estiver marcado como 'cancelado', 'expirado' ou 'recusado'
if (in_array($statusAtual, ['cancelado', 'expirado', 'recusado'], true)) {
    echo json_encode([
        "success" => true,
        "txid" => $txid,
        "status" => $statusAtual,
        "aprovado" => false,
        "expirado" => ($statusAtual === 'expirado'),
        "mensagem" => ($statusAtual === 'expirado' ? "Tempo limite esgotado para este pagamento." : "Cobrança cancelada ou recusada.")
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Verificação de expiração por tempo decorrido
$segundosDecorridos = isset($pedido['segundos_desde_criacao']) ? (int)$pedido['segundos_desde_criacao'] : 0;
if ($segundosDecorridos >= $limiteSegundos) {
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $upStmt = $pdo->prepare("UPDATE pedidos SET status = 'expirado' WHERE txid = :txid AND status = 'pendente'");
            $upStmt->execute([':txid' => $txid]);
        }
    } catch (Exception $e) {}

    echo json_encode([
        "success" => true,
        "txid" => $txid,
        "status" => "expirado",
        "aprovado" => false,
        "expirado" => true,
        "mensagem" => "Tempo limite esgotado para este pagamento."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 5. Consulta ao Mercado Pago para pedidos pendentes
$mpAccessToken = obterMercadoPagoAccessToken();

if ($mpAccessToken) {
    $searchUrl = "https://api.mercadopago.com/v1/payments/search?external_reference=" . urlencode($txid) . "&sort=date_created&criteria=desc";

    $ch = curl_init($searchUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$mpAccessToken}"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 8
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 && $response) {
        $searchData = json_decode((string)$response, true);
        $results = $searchData['results'] ?? [];

        if (is_array($results) && !empty($results)) {
            $encontrouAprovado = false;
            $approvedPaymentId = null;
            $ultimoStatus = '';

            // Itera por TODOS os pagamentos vinculados ao external_reference
            foreach ($results as $paymentInfo) {
                $st = strtolower((string)($paymentInfo['status'] ?? ''));
                if ($st === 'approved') {
                    $encontrouAprovado = true;
                    $approvedPaymentId = $paymentInfo['id'] ?? null;
                    break;
                }
                if (empty($ultimoStatus)) {
                    $ultimoStatus = $st;
                }
            }

            if ($encontrouAprovado) {
                $transicaoOcorreu = false;
                try {
                    if (isset($pdo) && $pdo instanceof PDO) {
                        $upStmt = $pdo->prepare("
                            UPDATE pedidos 
                            SET status = 'pago', pago_em = NOW(), mp_payment_id = COALESCE(:mp_id, mp_payment_id) 
                            WHERE txid = :txid AND status <> 'pago'
                        ");
                        $upStmt->execute([
                            ':txid' => $txid,
                            ':mp_id' => $approvedPaymentId ? (string)$approvedPaymentId : null
                        ]);
                        $transicaoOcorreu = ($upStmt->rowCount() === 1);
                    }
                } catch (Exception $e) {
                    error_log("Erro ao atualizar status pago no checar_status: " . $e->getMessage());
                }

                if ($transicaoOcorreu && isset($pdo) && $pdo instanceof PDO) {
                    // Cômputo do cupom
                    registrarUsoCupomSePago($pdo, $txid);

                    // Dispara webhook Discord
                    try {
                        enviarNotificacaoCompraDiscord(
                            $pedido['nick'],
                            $pedido['tipo_conta'] ?? 'original',
                            $pedido['servidor'],
                            $pedido['vip_nome'],
                            (float)$pedido['valor'],
                            $txid,
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
                        error_log("Erro ao disparar webhook Discord no checar_status: " . $e->getMessage());
                    }

                    // Dispara entrega do VIP
                    try {
                        enviarEntregaVip($pedido, $pdo);
                    } catch (Exception $e) {
                        error_log("Erro ao disparar entrega VIP no checar_status: " . $e->getMessage());
                    }
                }

                echo json_encode([
                    "success" => true,
                    "txid" => $txid,
                    "status" => "pago",
                    "aprovado" => true,
                    "pago_em" => date('c'),
                    "nick" => $pedido['nick'],
                    "servidor" => $pedido['servidor'],
                    "vip_nome" => $pedido['vip_nome']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            } elseif ($metodo !== 'checkout_pro' && in_array($ultimoStatus, ['cancelled', 'rejected'], true)) {
                // Apenas para PIX/Cartão transparente rejeitado de forma definitiva
                try {
                    if (isset($pdo) && $pdo instanceof PDO) {
                        $upStmt = $pdo->prepare("UPDATE pedidos SET status = 'cancelado' WHERE txid = :txid AND status = 'pendente'");
                        $upStmt->execute([':txid' => $txid]);
                    }
                } catch (Exception $e) {}

                echo json_encode([
                    "success" => true,
                    "txid" => $txid,
                    "status" => "cancelado",
                    "aprovado" => false,
                    "expirado" => true,
                    "mensagem" => "Cobrança cancelada pelo gateway de pagamento."
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            // Para checkout_pro: NUNCA marca cancelado/recusado por pagamentos intermediários rejected; mantém pendente.
        }
    }
}

// Continua pendente normalmente enquanto o timer estiver correndo
echo json_encode([
    "success" => true,
    "txid" => $txid,
    "status" => "pendente",
    "aprovado" => false,
    "segundos_restantes" => max(0, $limiteSegundos - $segundosDecorridos)
], JSON_UNESCAPED_UNICODE);

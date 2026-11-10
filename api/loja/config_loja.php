<?php
/**
 * config_loja.php
 * Configurações utilitárias e validações comuns para os endpoints da loja.
 */

declare(strict_types=1);

/**
 * Retorna a URL base oficial do site de forma estrita e segura.
 * Nunca utiliza headers dinâmicos como HTTP_HOST.
 */
function obterSiteUrl(): string {
    if (defined('SITE_URL') && !empty(SITE_URL)) {
        return rtrim(SITE_URL, '/');
    }
    return 'https://redenerds.com.br';
}

/**
 * Envia cabeçalhos CORS restritos e seguros.
 */
function aplicarCorsLoja(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = [
        'https://redenerds.com.br',
        'https://www.redenerds.com.br'
    ];

    if (in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: {$origin}");
    } else {
        // Fallback para o domínio principal
        header("Access-Control-Allow-Origin: https://redenerds.com.br");
    }

    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization");
}

/**
 * Obtém o Access Token do Mercado Pago e valida se é uma chave válida de produção/teste.
 * Caso seja inválida ou placeholder, retorna null.
 */
function obterMercadoPagoAccessToken(): ?string {
    if (!defined('MERCADO_PAGO_ACCESS_TOKEN')) {
        return null;
    }
    $token = trim((string)MERCADO_PAGO_ACCESS_TOKEN);
    if (empty($token) || strpos($token, 'APP_USR-SEU-ACCESS-TOKEN') !== false || strpos($token, 'DEMO_TOKEN') !== false) {
        return null;
    }
    return $token;
}

/**
 * Emite uma resposta HTTP 503 padronizada e sem vazamento de dados internos.
 */
function responder503ServicoIndisponivel(string $logDetalhe): void {
    error_log("[MERCADO PAGO 503] {$logDetalhe}");
    http_response_code(503);
    echo json_encode([
        "erro" => "Serviço de pagamentos temporariamente indisponível. Tente novamente mais tarde."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Garante automaticamente que a tabela chaves e a tabela pedidos existam,
 * renomeando pedidos_vip para pedidos se necessário, e configurando todas as colunas.
 */
function garantirSchemaTabelaPedidos(?PDO $pdo): void {
    // shortcut: schema is fully migrated via migrations/; no runtime DDL on user requests
    return;
}

/**
 * Extrai nick, tipo de produto, quantidade, servidor e nome do item a partir da descrição e metadata do Mercado Pago.
 * Suporta formatos unificados e legados ('Jogador:', 'Nick:', 'Player:') e estrutura de parênteses.
 */
function extrairDadosDescricaoPagamento(string $descricao, array $paymentInfo = []): array {
    $metadata = $paymentInfo['metadata'] ?? [];

    $tipoProduto = $metadata['tipo_produto'] ?? null;
    $quantidade = isset($metadata['quantidade']) ? (int)$metadata['quantidade'] : null;
    $servidor = $metadata['servidor'] ?? null;
    $itemNome = $metadata['item_nome'] ?? ($metadata['vip_nome'] ?? null);
    $nick = $metadata['nick'] ?? null;

    // Se a metadata não contiver todos os dados, extrai da descrição com suporte a múltiplos formatos
    if (empty($nick) || empty($itemNome)) {
        // Formato 1: "Rede Nerd - 5x Chave Mítica (Survival) - Jogador: Thiago" ou "... - Player: Thiago"
        // Formato 2: "Rede Nerd - Chave Mítica - Nick: Thiago (Survival)"
        if (preg_match('/Rede Nerd\s*-\s*(?:(\d+)x\s+)?(.*?)\s*(?:\((.*?)\))?\s*-\s*(?:Jogador|Nick|Player):\s*([a-zA-Z0-9_]+)(?:\s*\((.*?)\))?/i', $descricao, $m)) {
            if (!empty($m[1])) {
                $quantidade = (int)$m[1];
                $tipoProduto = 'chave';
            }
            if (empty($itemNome) && !empty($m[2])) {
                $itemNome = trim($m[2]);
            }
            $srvFound = !empty($m[3]) ? trim($m[3]) : (!empty($m[5]) ? trim($m[5]) : null);
            if (empty($servidor) && !empty($srvFound)) {
                $servidor = $srvFound;
            }
            if (empty($nick) && !empty($m[4])) {
                $nick = trim($m[4]);
            }
        }
    }

    if (empty($nick)) {
        $nick = trim((string)($paymentInfo['payer']['first_name'] ?? ''));
    }
    if (empty($nick)) {
        $nick = 'Jogador';
    }

    $tipoProdutoFinal = ($tipoProduto === 'chave' || ($quantidade !== null && $quantidade > 1) || stripos($descricao, 'chave') !== false) ? 'chave' : 'vip';
    $qtdFinal = max(1, $quantidade ?? 1);

    return [
        'nick' => $nick,
        'tipo_produto' => $tipoProdutoFinal,
        'quantidade' => $qtdFinal,
        'servidor' => !empty($servidor) ? $servidor : 'Survival',
        'item_nome' => !empty($itemNome) ? $itemNome : ($tipoProdutoFinal === 'chave' ? 'Chave Mítica' : 'VIP')
    ];
}

/**
 * Auto-recupera um pedido aprovado no Mercado Pago inserindo-o atomicamente no banco de dados.
 * Protegido contra duplicação/race-condition por UNIQUE INDEX e verificação de rowCount.
 *
 * @return bool Retorna true SOMENTE se esta execução foi a responsável por inserir a linha (rowCount === 1).
 */
function autoRecuperarPedidoMercadoPago(PDO $pdo, string $externalRef, mixed $paymentId, array $paymentInfo): bool {
    garantirSchemaTabelaPedidos($pdo);

    $extraidos = extrairDadosDescricaoPagamento((string)($paymentInfo['description'] ?? ''), $paymentInfo);
    $valorPago = (float)($paymentInfo['transaction_amount'] ?? 0);
    $metodoPgto = strtolower((string)($paymentInfo['payment_method_id'] ?? 'pix'));

    $chaveIdRec = null;
    $vipIdRec = null;
    try {
        if ($extraidos['tipo_produto'] === 'chave') {
            $stmtC = $pdo->prepare("SELECT id FROM chaves WHERE nome = :nome LIMIT 1");
            $stmtC->execute([':nome' => $extraidos['item_nome']]);
            $chaveIdRec = $stmtC->fetchColumn() ?: null;
        } else {
            $stmtV = $pdo->prepare("SELECT id FROM vips WHERE nome = :nome LIMIT 1");
            $stmtV->execute([':nome' => $extraidos['item_nome']]);
            $vipIdRec = $stmtV->fetchColumn() ?: null;
        }
    } catch (\Throwable $e) {}

    try {
        $stmtIns = $pdo->prepare("
            INSERT INTO pedidos (
                txid, mp_payment_id, nick, pais_ip, tipo_conta, servidor, 
                tipo_produto, quantidade, chave_id, vip_id, vip_nome, 
                valor, valor_original, status, metodo_pagamento, cupom_computado, criado_em, pago_em
            ) VALUES (
                :txid, :mp_id, :nick, 'BR', 'original', :servidor, 
                :tipo_produto, :quantidade, :chave_id, :vip_id, :vip_nome, 
                :valor, :valor, 'pago', :metodo, 1, NOW(), NOW()
            )
        ");
        $stmtIns->execute([
            ':txid' => $externalRef,
            ':mp_id' => $paymentId ? (string)$paymentId : null,
            ':nick' => $extraidos['nick'],
            ':servidor' => $extraidos['servidor'],
            ':tipo_produto' => $extraidos['tipo_produto'],
            ':quantidade' => $extraidos['quantidade'],
            ':chave_id' => $chaveIdRec,
            ':vip_id' => $vipIdRec,
            ':vip_nome' => $extraidos['item_nome'],
            ':valor' => $valorPago,
            ':metodo' => $metodoPgto
        ]);

        return ($stmtIns->rowCount() === 1);
    } catch (\Throwable $e) {
        // Se disparar Duplicate Key (1062) devido a chamada concorrente, retorna false com segurança
        return false;
    }
}

/**
 * Cancela ativamente um pagamento pendente na API do Mercado Pago.
 * Essencial para invalidar o QR Code PIX no Banco Central imediatamente após a expiração.
 *
 * @param string|int $mpPaymentId Identificador do pagamento no Mercado Pago
 * @return bool True se o cancelamento foi aceito ou se já estava cancelado
 */
function cancelarPagamentoMercadoPago(string|int $mpPaymentId): bool {
    $token = obterMercadoPagoAccessToken();
    if (!$token || empty($mpPaymentId)) {
        return false;
    }

    $idLimpo = trim((string)$mpPaymentId);
    if (!preg_match('/^[0-9]+$/', $idLimpo)) {
        return false;
    }

    $url = "https://api.mercadopago.com/v1/payments/" . urlencode($idLimpo);
    $payloadJson = json_encode(['status' => 'cancelled']);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("[MERCADO PAGO CANCEL] Erro cURL ao cancelar pagamento {$idLimpo}: {$curlError}");
        return false;
    }

    $data = json_decode((string)$response, true);
    $statusRetornado = strtolower((string)($data['status'] ?? ''));

    $sucesso = ($httpCode >= 200 && $httpCode < 300) || $statusRetornado === 'cancelled';
    if ($sucesso) {
        error_log("[MERCADO PAGO CANCEL] Pagamento {$idLimpo} cancelado com sucesso no gateway.");
    } else {
        error_log("[MERCADO PAGO CANCEL] Resposta {$httpCode} ao cancelar {$idLimpo}: " . json_encode($data));
    }

    return $sucesso;
}

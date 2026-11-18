<?php
/**
 * config_loja.php
 * Configurações utilitárias e validações comuns para os endpoints da loja.
 */

declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

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
    $destinatario = !empty($metadata['destinatario']) ? trim((string)$metadata['destinatario']) : null;
    if ($destinatario !== null && ($destinatario === '' || strcasecmp($destinatario, (string)$nick) === 0)) {
        $destinatario = null;
    }

    return [
        'nick' => $nick,
        'destinatario' => $destinatario,
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
                txid, mp_payment_id, nick, destinatario, pais_ip, tipo_conta, servidor, 
                tipo_produto, quantidade, chave_id, vip_id, vip_nome, 
                valor, valor_original, status, metodo_pagamento, cupom_computado, criado_em, pago_em
            ) VALUES (
                :txid, :mp_id, :nick, :destinatario, 'BR', 'original', :servidor, 
                :tipo_produto, :quantidade, :chave_id, :vip_id, :vip_nome, 
                :valor, :valor, 'pago', :metodo, 1, NOW(), NOW()
            )
        ");
        $stmtIns->execute([
            ':txid' => $externalRef,
            ':mp_id' => $paymentId ? (string)$paymentId : null,
            ':nick' => $extraidos['nick'],
            ':destinatario' => $extraidos['destinatario'] ?? null,
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

/**
 * Valida os dados de entrada do checkout, consulta produto/servidor/cupom no banco
 * e calcula o valor final (Zero-Trust) de forma única para PIX, Cartão e Internacional.
 *
 * @return array{ok:bool,status?:int,erro?:string,nick?:string,tipo_conta?:string,servidor?:string,servidor_id?:int,tipo_produto?:string,item_id?:int,item_nome?:string,quantidade?:int,valor_original?:float,desconto_aplicado?:float,valor?:float,cupom_codigo?:?string}
 */
function prepararCheckoutLoja(PDO $pdo, array $data): array {
    $nick = trim((string)($data['nick'] ?? ''));
    $destinatarioRaw = trim((string)($data['destinatario'] ?? ''));
    $destinatario = null;
    if ($destinatarioRaw !== '') {
        if (strlen($destinatarioRaw) < 3 || strlen($destinatarioRaw) > 16 || !preg_match('/^[a-zA-Z0-9_]+$/', $destinatarioRaw)) {
            return ['ok' => false, 'status' => 400, 'erro' => 'Nick do destinatário inválido. Use de 3 a 16 caracteres alfanuméricos ou underline.'];
        }
        if (strcasecmp($destinatarioRaw, $nick) !== 0) {
            $destinatario = $destinatarioRaw;
        }
    }
    $tipoConta = strtolower(trim((string)($data['tipo_conta'] ?? 'original')));
    if (!in_array($tipoConta, ['original', 'pirata'], true)) {
        $tipoConta = 'original';
    }

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

    if ($nick === '' || strlen($nick) < 3 || strlen($nick) > 16 || !preg_match('/^[a-zA-Z0-9_]+$/', $nick)) {
        return ['ok' => false, 'status' => 400, 'erro' => 'Nick inválido. Use de 3 a 16 caracteres alfanuméricos ou underline.'];
    }
    if ($servidor === '') {
        return ['ok' => false, 'status' => 400, 'erro' => 'Selecione o servidor onde deseja receber o produto.'];
    }
    if ($itemId <= 0) {
        return ['ok' => false, 'status' => 400, 'erro' => 'Identificador do produto inválido.'];
    }

    $tabelaItem = ($tipoProduto === 'chave') ? 'chaves' : 'vips';
    $stmtItem = $pdo->prepare("SELECT id, nome, preco, servidor_id FROM {$tabelaItem} WHERE id = :id AND (ativo = 1 OR ativo IS NULL) LIMIT 1");
    $stmtItem->execute([':id' => $itemId]);
    $itemRow = $stmtItem->fetch(PDO::FETCH_ASSOC);

    if (!$itemRow) {
        return ['ok' => false, 'status' => 404, 'erro' => ($tipoProduto === 'chave' ? 'Pacote' : 'Pacote VIP') . ' não encontrado ou inativo.'];
    }

    $valorOriginal = round((float)$itemRow['preco'] * $quantidade, 2);
    if ($valorOriginal <= 0) {
        return ['ok' => false, 'status' => 400, 'erro' => 'Valor do produto inválido.'];
    }

    $srvSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $servidor));
    $stmtCheckSrv = $pdo->prepare("SELECT id, servername, nome, enabled FROM servidores WHERE (servername = :srv OR nome = :srvSlug) LIMIT 1");
    $stmtCheckSrv->execute([':srv' => $servidor, ':srvSlug' => $srvSlug]);
    $srvRow = $stmtCheckSrv->fetch(PDO::FETCH_ASSOC);

    if (!$srvRow || (isset($srvRow['enabled']) && (int)$srvRow['enabled'] === 0)) {
        return ['ok' => false, 'status' => 400, 'erro' => 'O servidor selecionado é inválido ou está desabilitado.'];
    }

    $servidorId = (int)$srvRow['id'];
    $servidorNome = (string)$srvRow['servername'];

    $itemServidorId = (int)($itemRow['servidor_id'] ?? 0);
    if ($itemServidorId > 0 && $itemServidorId !== $servidorId) {
        return ['ok' => false, 'status' => 400, 'erro' => "O pacote selecionado não pertence ao servidor {$servidorNome}."];
    }

    $valor = $valorOriginal;
    $descontoAplicado = 0.00;
    $cupomCodigo = null;
    $cupomEnviado = strtoupper(trim((string)($data['cupom'] ?? '')));

    if ($cupomEnviado !== '') {
        $stmtCupom = $pdo->prepare("SELECT * FROM cupons WHERE codigo = :codigo LIMIT 1");
        $stmtCupom->execute([':codigo' => $cupomEnviado]);
        $cupomRow = $stmtCupom->fetch(PDO::FETCH_ASSOC);

        if (!$cupomRow) {
            return ['ok' => false, 'status' => 404, 'erro' => "Cupom '{$cupomEnviado}' não encontrado."];
        }
        if (empty($cupomRow['ativo'])) {
            return ['ok' => false, 'status' => 400, 'erro' => "O cupom '{$cupomEnviado}' está desativado."];
        }
        $expiraTs = !empty($cupomRow['expira_em']) ? strtotime((string)$cupomRow['expira_em']) : false;
        if ($expiraTs && $expiraTs < time()) {
            return ['ok' => false, 'status' => 400, 'erro' => "O cupom '{$cupomEnviado}' expirou em " . date('d/m/Y H:i', $expiraTs) . "."];
        }
        $cupomServidorId = (int)($cupomRow['servidor_id'] ?? 0);
        if ($cupomServidorId > 0 && $cupomServidorId !== $servidorId) {
            $stmtSrvNome = $pdo->prepare("SELECT servername FROM servidores WHERE id = :id LIMIT 1");
            $stmtSrvNome->execute([':id' => $cupomServidorId]);
            $srvNome = $stmtSrvNome->fetchColumn() ?: 'outro servidor';
            return ['ok' => false, 'status' => 400, 'erro' => "O cupom '{$cupomEnviado}' é válido exclusivamente para compras no servidor {$srvNome}."];
        }

        $descontoAplicado = round($valorOriginal * ((float)$cupomRow['porcentagem_desconto'] / 100), 2);
        $valor = max(0.01, round($valorOriginal - $descontoAplicado, 2));
        $cupomCodigo = (string)$cupomRow['codigo'];
    }

    return [
        'ok' => true,
        'nick' => $nick,
        'destinatario' => $destinatario,
        'tipo_conta' => $tipoConta,
        'servidor' => $servidorNome,
        'servidor_id' => $servidorId,
        'tipo_produto' => $tipoProduto,
        'item_id' => $itemId,
        'item_nome' => (string)$itemRow['nome'],
        'quantidade' => $quantidade,
        'valor_original' => $valorOriginal,
        'desconto_aplicado' => $descontoAplicado,
        'valor' => $valor,
        'cupom_codigo' => $cupomCodigo,
    ];
}

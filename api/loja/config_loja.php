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
 * Garante automaticamente que a tabela chaves e as colunas necessárias em pedidos_vip existam,
 * e que vip_id permita NULL para evitar restrições de integridade em compras de chaves.
 */
function garantirSchemaTabelaPedidos(?PDO $pdo): void {
    if (!$pdo || !($pdo instanceof PDO)) {
        return;
    }

    static $jaVerificado = false;
    if ($jaVerificado) {
        return;
    }
    $jaVerificado = true;

    try {
        // 1. Cria tabela chaves se não existir
        $pdo->exec("CREATE TABLE IF NOT EXISTS `chaves` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `servidor_id` INT NOT NULL DEFAULT 0,
          `servidor` VARCHAR(100) NOT NULL DEFAULT '',
          `nome` VARCHAR(100) NOT NULL,
          `imagem` VARCHAR(255) NULL DEFAULT NULL,
          `packageId` VARCHAR(100) NOT NULL,
          `preco` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          `destaque` TINYINT(1) NOT NULL DEFAULT 0,
          `ativo` TINYINT(1) NOT NULL DEFAULT 1,
          `cor1` VARCHAR(20) NOT NULL DEFAULT '#FFD700',
          `cor2` VARCHAR(20) NOT NULL DEFAULT '#FFA500',
          `vantagens` TEXT NULL,
          `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_srv (`servidor_id`, `ativo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 2. Analisa colunas existentes em pedidos_vip
        $stmtCols = $pdo->query("SHOW COLUMNS FROM `pedidos_vip`");
        if ($stmtCols) {
            $cols = [];
            $colDetails = [];
            while ($col = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
                $colName = strtolower((string)$col['Field']);
                $cols[] = $colName;
                $colDetails[$colName] = $col;
            }

            if (!in_array('quantidade', $cols, true)) {
                $pdo->exec("ALTER TABLE `pedidos_vip` ADD COLUMN `quantidade` INT NOT NULL DEFAULT 1");
            }
            if (!in_array('tipo_produto', $cols, true)) {
                $pdo->exec("ALTER TABLE `pedidos_vip` ADD COLUMN `tipo_produto` VARCHAR(20) NOT NULL DEFAULT 'vip'");
            }
            if (!in_array('chave_id', $cols, true)) {
                $pdo->exec("ALTER TABLE `pedidos_vip` ADD COLUMN `chave_id` INT NULL DEFAULT NULL");
            }
            if (!in_array('cupom_computado', $cols, true)) {
                $pdo->exec("ALTER TABLE `pedidos_vip` ADD COLUMN `cupom_computado` TINYINT(1) NOT NULL DEFAULT 0");
            }
            if (!in_array('pais_ip', $cols, true)) {
                $pdo->exec("ALTER TABLE `pedidos_vip` ADD COLUMN `pais_ip` CHAR(2) NULL DEFAULT NULL");
            }

            // Garante que vip_id permita NULL (crucial para compras de chaves)
            if (isset($colDetails['vip_id']) && strtolower((string)($colDetails['vip_id']['Null'] ?? '')) === 'no') {
                $pdo->exec("ALTER TABLE `pedidos_vip` MODIFY COLUMN `vip_id` INT NULL DEFAULT NULL");
            }

            // 3. Garante constraint UNIQUE em txid para evitar concorrência/duplicações
            $stmtIdx = $pdo->query("SHOW INDEX FROM `pedidos_vip` WHERE Column_name = 'txid'");
            $hasUniqueTxid = false;
            if ($stmtIdx) {
                while ($idxRow = $stmtIdx->fetch(PDO::FETCH_ASSOC)) {
                    if (isset($idxRow['Non_unique']) && (int)$idxRow['Non_unique'] === 0) {
                        $hasUniqueTxid = true;
                        break;
                    }
                }
            }
            if (!$hasUniqueTxid) {
                try {
                    $pdo->exec("ALTER TABLE `pedidos_vip` ADD UNIQUE INDEX `uq_txid` (`txid`)");
                } catch (\Throwable $e) {
                    // Ignora se já existir ou se houver duplicatas legadas
                }
            }
        }
    } catch (\Throwable $e) {
        error_log("Aviso ao garantir schema da loja em pedidos_vip/chaves: " . $e->getMessage());
    }
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
        // Formato 1: "RedeNerds - 5x Chave Mítica (Survival) - Jogador: Thiago" ou "... - Player: Thiago"
        // Formato 2: "RedeNerds - Chave Mítica - Nick: Thiago (Survival)"
        if (preg_match('/RedeNerds\s*-\s*(?:(\d+)x\s+)?(.*?)\s*(?:\((.*?)\))?\s*-\s*(?:Jogador|Nick|Player):\s*([a-zA-Z0-9_]+)(?:\s*\((.*?)\))?/i', $descricao, $m)) {
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
            INSERT INTO pedidos_vip (
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

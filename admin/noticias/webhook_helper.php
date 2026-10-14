<?php
/**
 * Helper para envio, edição e exclusão de mensagens via Webhook do Discord.
 */

if (!defined('DISCORD_WEBHOOKS')) {
    define('DISCORD_WEBHOOKS', []);
}

/**
 * Converte caminhos relativos de upload para URLs absolutas da Rede Nerds.
 */
function formatarUrlCapaDiscord($capa) {
    if (empty($capa)) {
        return null;
    }

    if (preg_match('#^https?://#i', $capa)) {
        return $capa;
    }

    $baseUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://redenerds.com.br';
    $caminhoLimpo = preg_replace('#^(\.\./|\./|/)+#', '', $capa);
    return $baseUrl . "/" . $caminhoLimpo;
}

/**
 * Timeout padrão (segundos) das chamadas cURL ao Discord, para não travar
 * a requisição do administrador em instabilidades do Discord.
 */
const DISCORD_CURL_TIMEOUT = 10;
const DISCORD_CURL_CONNECT_TIMEOUT = 5;

/**
 * Valida o ID de mensagem do Discord (snowflake numérico).
 */
function discordMensagemIdValido($mensagemID): bool {
    return !empty($mensagemID) && preg_match('/^\d{10,25}$/', (string)$mensagemID) === 1;
}

/**
 * Envia uma nova notícia para o Discord via Webhook e retorna o ID da mensagem criada.
 */
function enviarWebhookDiscord($categoriaEnvio, $titulo, $conteudo, $autor, $capa, $servidor, $marcarEveryone = false) {
    // 1. Verifica se a constante foi definida
    if (empty(DISCORD_WEBHOOKS)) {
        error_log("ERRO DISCORD: Nenhum webhook configurado no config.php.");
        return null;
    }

    $urlWebhook = DISCORD_WEBHOOKS[$categoriaEnvio] ?? null;
    if (empty($urlWebhook)) {
        error_log("ERRO DISCORD: Nenhuma URL de webhook encontrada para a categoria '{$categoriaEnvio}'.");
        return null;
    }

    $url = $urlWebhook . (strpos($urlWebhook, '?') !== false ? '&wait=true' : '?wait=true');
    $content = $marcarEveryone ? "@everyone" : "";

    $embed = [
        "title" => $titulo,
        "description" => $conteudo,
        "color" => 3447003,
        "author" => [
            "name" => $autor,
            "icon_url" => "https://mc-heads.net/avatar/" . urlencode($autor) . "/100"
        ],
        "footer" => [
            "text" => "Servidor: " . $servidor
        ],
        "timestamp" => date('c')
    ];

    $urlCapaFormatada = formatarUrlCapaDiscord($capa);
    if (!empty($urlCapaFormatada)) {
        $embed["image"] = ["url" => $urlCapaFormatada];
    }

    $payload = json_encode([
        "content" => $content,
        "embeds" => [$embed]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!$payload) {
        error_log("ERRO DISCORD: Falha ao converter payload para JSON.");
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: DiscordBot (RedeNerds, 1.0)'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => DISCORD_CURL_CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT => DISCORD_CURL_TIMEOUT,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Se houve erro de cURL no servidor
    if ($curlError) {
        error_log("ERRO cURL DISCORD: " . $curlError);
        return null;
    }

    // Se o Discord respondeu com sucesso (HTTP 200/204)
    if ($httpCode >= 200 && $httpCode < 300 && $response) {
        $data = json_decode($response, true);
        return $data['id'] ?? null;
    }

    // Se o Discord recusou a requisição (ex: HTTP 400 ou 404)
    error_log("ERRO DISCORD HTTP {$httpCode}: " . $response);
    return null;
}

/**
 * Edita uma mensagem já enviada no Discord via Webhook.
 */
function editarWebhookDiscord($categoriaEnvio, $mensagemID, $titulo, $conteudo, $autor, $capa, $servidor) {
    if (empty(DISCORD_WEBHOOKS) || !isset(DISCORD_WEBHOOKS[$categoriaEnvio])) {
        error_log("ERRO DISCORD: nenhuma URL de webhook para a categoria '{$categoriaEnvio}'.");
        return false;
    }
    if (!discordMensagemIdValido($mensagemID)) {
        error_log("ERRO DISCORD: ID de mensagem inválido para edição.");
        return false;
    }

    $url = rtrim(DISCORD_WEBHOOKS[$categoriaEnvio], '/') . "/messages/" . $mensagemID;

    $embed = [
        "title" => $titulo,
        "description" => $conteudo,
        "color" => 3447003,
        "author" => [
            "name" => $autor,
            "icon_url" => "https://mc-heads.net/avatar/" . urlencode($autor) . "/100"
        ],
        "footer" => [
            "text" => "Servidor: " . $servidor
        ],
        "timestamp" => date('c')
    ];

    $urlCapaFormatada = formatarUrlCapaDiscord($capa);
    if (!empty($urlCapaFormatada)) {
        $embed["image"] = ["url" => $urlCapaFormatada];
    }

    $payload = json_encode([
        "embeds" => [$embed]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!$payload) {
        error_log("ERRO DISCORD: falha ao converter payload para JSON (edição).");
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: DiscordBot (RedeNerds, 1.0)'
        ],
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => DISCORD_CURL_CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT => DISCORD_CURL_TIMEOUT,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("ERRO cURL DISCORD (edição): " . $curlError);
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("ERRO DISCORD HTTP {$httpCode} (edição).");
        return false;
    }

    return true;
}

/**
 * Deleta uma mensagem do Discord enviada previamente via Webhook.
 */
function deletarWebhookDiscord($categoriaEnvio, $mensagemID) {
    if (empty(DISCORD_WEBHOOKS) || !isset(DISCORD_WEBHOOKS[$categoriaEnvio])) {
        error_log("ERRO DISCORD: nenhuma URL de webhook para a categoria '{$categoriaEnvio}'.");
        return false;
    }
    if (!discordMensagemIdValido($mensagemID)) {
        error_log("ERRO DISCORD: ID de mensagem inválido para exclusão.");
        return false;
    }

    $url = rtrim(DISCORD_WEBHOOKS[$categoriaEnvio], '/') . "/messages/" . $mensagemID;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'User-Agent: DiscordBot (RedeNerds, 1.0)'
        ],
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => DISCORD_CURL_CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT => DISCORD_CURL_TIMEOUT,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("ERRO cURL DISCORD (exclusão): " . $curlError);
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("ERRO DISCORD HTTP {$httpCode} (exclusão).");
        return false;
    }

    return true;
}

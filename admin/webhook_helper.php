<?php
/**
 * Helper para envio e edição de mensagens via Webhook do Discord.
 */

if (!defined('DISCORD_WEBHOOKS')) {
    // Garante que a constante existe (definida no config.php)
    define('DISCORD_WEBHOOKS', []);
}

/**
 * Envia uma nova notícia para o Discord via Webhook e retorna o ID da mensagem criada.
 */
function enviarWebhookDiscord($categoriaEnvio, $titulo, $conteudo, $autor, $capa, $servidor, $marcarEveryone = false) {
    $urlWebhook = DISCORD_WEBHOOKS[$categoriaEnvio] ?? null;
    if (empty($urlWebhook)) {
        return null;
    }

    // Adiciona ?wait=true para que o Discord retorne os dados da mensagem criada (incluindo o ID)
    $url = $urlWebhook . (strpos($urlWebhook, '?') !== false ? '&wait=true' : '?wait=true');

    $content = $marcarEveryone ? "@everyone" : "";

    $embed = [
        "title" => $titulo,
        "description" => $conteudo,
        "color" => 3447003, // Azul
        "author" => [
            "name" => $autor,
            "icon_url" => "https://mc-heads.net/avatar/" . urlencode($autor) . "/100"
        ],
        "footer" => [
            "text" => "Servidor: " . $servidor
        ],
        "timestamp" => date('c')
    ];

    if (!empty($capa)) {
        $embed["image"] = ["url" => $capa];
    }

    $payload = json_encode([
        "content" => $content,
        "embeds" => [$embed]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 && $response) {
        $data = json_decode($response, true);
        return $data['id'] ?? null;
    }

    return null;
}

/**
 * Edita uma mensagem já enviada no Discord via Webhook utilizando o mensagemID gravado no banco.
 */
function editarWebhookDiscord($categoriaEnvio, $mensagemID, $titulo, $conteudo, $autor, $capa, $servidor) {
    $urlWebhook = DISCORD_WEBHOOKS[$categoriaEnvio] ?? null;
    if (empty($urlWebhook) || empty($mensagemID)) {
        return false;
    }

    // Endpoint do Discord para editar mensagem específica do webhook
    $url = rtrim($urlWebhook, '/') . "/messages/" . $mensagemID;

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

    if (!empty($capa)) {
        $embed["image"] = ["url" => $capa];
    }

    $payload = json_encode([
        "embeds" => [$embed]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode >= 200 && $httpCode < 300);
}
<?php
/**
 * Helper para envio, edição e exclusão de mensagens via Webhook do Discord.
 */

if (!defined('DISCORD_WEBHOOKS')) {
    define('DISCORD_WEBHOOKS', []);
}

/**
 * Converte caminhos relativos de upload para URLs absolutas da Rede Nerd.
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

function discordUltimoErro(?string $definir = null): ?string {
    static $ultimo = null;
    if ($definir !== null) {
        $ultimo = $definir === '' ? null : $definir;
    }
    return $ultimo;
}

function truncarTextoDiscord(string $texto, int $limite): string {
    if (mb_strlen($texto, 'UTF-8') <= $limite) {
        return $texto;
    }
    return rtrim(mb_substr($texto, 0, max(1, $limite - 3), 'UTF-8')) . '...';
}

function discordWebhookUrlValida(string $url): bool {
    return preg_match('#^https://(discord\.com|discordapp\.com)/api/webhooks/\d+/[A-Za-z0-9._-]+/?$#', trim($url)) === 1;
}

function obterConfigWebhookDiscordPorServidor(?PDO $pdo, string $servidor, string $categoriaEnvio): ?array {
    $db = ($pdo instanceof PDO) ? $pdo : (($GLOBALS['pdo'] ?? null) instanceof PDO ? $GLOBALS['pdo'] : null);
    if (!$db) {
        return null;
    }
    $srv = trim($servidor);
    $cat = trim($categoriaEnvio);
    if ($srv === '' || $cat === '') {
        return null;
    }
    try {
        $stmt = $db->prepare("
            SELECT w.webhook_url, s.discord_role_id
            FROM servidores_webhooks w
            INNER JOIN servidores s ON s.id = w.servidor_id
            WHERE (s.servername = :srv OR s.nome = :srv)
              AND w.categoria = :cat
            LIMIT 1
        ");
        $stmt->execute([':srv' => $srv, ':cat' => $cat]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || trim((string)($row['webhook_url'] ?? '')) === '') {
            return null;
        }
        $roleId = trim((string)($row['discord_role_id'] ?? ''));
        return [
            'url' => trim((string)$row['webhook_url']),
            'role_id' => $roleId !== '' ? $roleId : null,
        ];
    } catch (Throwable $e) {
        error_log("ERRO DISCORD WEBHOOK DB: " . $e->getMessage());
        return null;
    }
}

/**
 * Busca a URL de webhook do Discord para um servidor e categoria no banco de dados.
 */
function obterWebhookDiscordPorServidor(?PDO $pdo, string $servidor, string $categoriaEnvio): ?string {
    $cfg = obterConfigWebhookDiscordPorServidor($pdo, $servidor, $categoriaEnvio);
    return $cfg ? $cfg['url'] : null;
}

/**
 * Envia uma nova notícia para o Discord via Webhook e retorna o ID da mensagem criada.
 */
function enviarWebhookDiscord($categoriaEnvio, $titulo, $conteudo, $autor, $capa, $servidor, $marcarEveryone = false, ?PDO $pdo = null) {
    discordUltimoErro('');
    $cfg = obterConfigWebhookDiscordPorServidor($pdo, (string)$servidor, (string)$categoriaEnvio);
    if (!$cfg) {
        discordUltimoErro("Webhook não cadastrado para '{$servidor}' ({$categoriaEnvio}).");
        error_log("ERRO DISCORD: Nenhum webhook cadastrado para '{$servidor}' na categoria '{$categoriaEnvio}'.");
        return null;
    }
    $urlWebhook = $cfg['url'];
    $roleId = $cfg['role_id'];

    $url = $urlWebhook . (strpos($urlWebhook, '?') !== false ? '&wait=true' : '?wait=true');
    $content = '';
    $bodyPayload = [
        "embeds" => []
    ];
    if ($marcarEveryone) {
        if (!empty($roleId)) {
            $content = "<@&{$roleId}>";
            $bodyPayload["allowed_mentions"] = ["roles" => [$roleId]];
        } else {
            $content = "@everyone";
        }
    }

    $embed = [
        "title" => truncarTextoDiscord((string)$titulo, 256),
        "description" => truncarTextoDiscord((string)$conteudo, 4096),
        "color" => 3447003,
        "author" => [
            "name" => truncarTextoDiscord((string)$autor, 256),
            "icon_url" => "https://mc-heads.net/avatar/" . urlencode($autor)
        ],
        "footer" => [
            "text" => truncarTextoDiscord("Servidor: " . $servidor, 2048)
        ],
        "timestamp" => date('c')
    ];

    $urlCapaFormatada = formatarUrlCapaDiscord($capa);
    if (!empty($urlCapaFormatada)) {
        $embed["image"] = ["url" => $urlCapaFormatada];
    }

    $bodyPayload["content"] = $content;
    $bodyPayload["embeds"] = [$embed];
    $payload = json_encode($bodyPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!$payload) {
        error_log("ERRO DISCORD: Falha ao converter payload para JSON.");
        return null;
    }

    $ch = curl_init($url);
    $curlOpts = [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: DiscordBot (Rede Nerd, 1.0)'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => DISCORD_CURL_CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT => DISCORD_CURL_TIMEOUT,
    ];
    if (defined('CURLSSLOPT_NATIVE_CA')) {
        $curlOpts[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
    }
    curl_setopt_array($ch, $curlOpts);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    if (PHP_VERSION_ID < 80500) {
        @curl_close($ch);
    }

    // Se houve erro de cURL no servidor
    if ($curlError) {
        discordUltimoErro("Erro cURL: " . $curlError);
        error_log("ERRO cURL DISCORD: " . $curlError);
        return null;
    }

    // Se o Discord respondeu com sucesso (HTTP 200/204)
    if ($httpCode >= 200 && $httpCode < 300 && $response) {
        $data = json_decode($response, true);
        return $data['id'] ?? null;
    }

    // Se o Discord recusou a requisição (ex: HTTP 400 ou 404)
    discordUltimoErro("Discord retornou HTTP {$httpCode}");
    error_log("ERRO DISCORD HTTP {$httpCode}: " . $response);
    return null;
}

/**
 * Edita uma mensagem já enviada no Discord via Webhook.
 */
function editarWebhookDiscord($categoriaEnvio, $mensagemID, $titulo, $conteudo, $autor, $capa, $servidor, ?PDO $pdo = null) {
    $urlWebhook = obterWebhookDiscordPorServidor($pdo, (string)$servidor, (string)$categoriaEnvio);
    if (empty($urlWebhook)) {
        error_log("ERRO DISCORD: nenhum webhook cadastrado para '{$servidor}' ({$categoriaEnvio}).");
        return false;
    }
    if (!discordMensagemIdValido($mensagemID)) {
        error_log("ERRO DISCORD: ID de mensagem inválido para edição.");
        return false;
    }

    $url = rtrim($urlWebhook, '/') . "/messages/" . $mensagemID;

    $embed = [
        "title" => truncarTextoDiscord((string)$titulo, 256),
        "description" => truncarTextoDiscord((string)$conteudo, 4096),
        "color" => 3447003,
        "author" => [
            "name" => truncarTextoDiscord((string)$autor, 256),
            "icon_url" => "https://mc-heads.net/avatar/" . urlencode($autor)
        ],
        "footer" => [
            "text" => truncarTextoDiscord("Servidor: " . $servidor, 2048)
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
    $curlOpts = [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: DiscordBot (Rede Nerd, 1.0)'
        ],
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => DISCORD_CURL_CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT => DISCORD_CURL_TIMEOUT,
    ];
    if (defined('CURLSSLOPT_NATIVE_CA')) {
        $curlOpts[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
    }
    curl_setopt_array($ch, $curlOpts);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    if (PHP_VERSION_ID < 80500) {
        @curl_close($ch);
    }

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
function deletarWebhookDiscord($categoriaEnvio, $mensagemID, $servidor = '', ?PDO $pdo = null) {
    $urlWebhook = obterWebhookDiscordPorServidor($pdo, (string)$servidor, (string)$categoriaEnvio);
    if (empty($urlWebhook)) {
        error_log("ERRO DISCORD: nenhum webhook cadastrado para '{$servidor}' ({$categoriaEnvio}).");
        return false;
    }
    if (!discordMensagemIdValido($mensagemID)) {
        error_log("ERRO DISCORD: ID de mensagem inválido para exclusão.");
        return false;
    }

    $url = rtrim($urlWebhook, '/') . "/messages/" . $mensagemID;

    $ch = curl_init($url);
    $curlOpts = [
        CURLOPT_HTTPHEADER => [
            'User-Agent: DiscordBot (Rede Nerd, 1.0)'
        ],
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => DISCORD_CURL_CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT => DISCORD_CURL_TIMEOUT,
    ];
    if (defined('CURLSSLOPT_NATIVE_CA')) {
        $curlOpts[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
    }
    curl_setopt_array($ch, $curlOpts);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    if (PHP_VERSION_ID < 80500) {
        @curl_close($ch);
    }

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

<?php
/**
 * discord_loja_helper.php
 * Helper para envio de notificações de compras de VIP para o Discord.
 */

if (!defined('DISCORD_WEBHOOKS')) {
    define('DISCORD_WEBHOOKS', []);
}

/**
 * Envia notificação de compra de VIP para o canal do Discord.
 *
 * @param string $nick Nickname do jogador no Minecraft
 * @param string $tipoConta 'original' ou 'pirata'
 * @param string $servidor Nome do servidor (ex: 'Potato Nerd', 'NerdDead')
 * @param string $vipNome Nome do plano VIP (ex: 'VIP Ouro')
 * @param float|string $valor Valor pago em R$
 * @param string $txid ID da transação ou Mercado Pago ID
 * @param string|null $corHex Cor do embed em hexadecimal (ex: '#7DB9DF')
 * @return bool Sucesso no envio
 */
function enviarNotificacaoCompraDiscord($nick, $tipoConta, $servidor, $vipNome, $valor, $txid, $corHex = '#7DB9DF') {
    $urlWebhook = DISCORD_WEBHOOKS['Loja'] ?? (DISCORD_WEBHOOKS['Atualizações'] ?? null);

    if (empty($urlWebhook)) {
        error_log("AVISO LOJA: Nenhum webhook de 'Loja' configurado no config.php.");
        return false;
    }

    $url = $urlWebhook . (strpos($urlWebhook, '?') !== false ? '&wait=true' : '?wait=true');

    // Converte hex para decimal pro Discord
    $corLimpa = ltrim($corHex, '#');
    $corDecimal = hexdec($corLimpa);
    if ($corDecimal <= 0) $corDecimal = 8239583; // fallback azul #7DB9DF

    $tipoContaFormatada = (strtolower($tipoConta) === 'original') ? '🟢 Original' : '⚪ Pirata/Alternativo';
    $valorFormatado = number_format((float)$valor, 2, ',', '.');
    $avatarUrl = "https://mc-heads.net/avatar/" . urlencode($nick) . "/128";

    $embed = [
        "title" => "💎 Nova Compra Aprovada!",
        "description" => "Um jogador acabou de adquirir um pacote VIP via **PIX**!",
        "color" => $corDecimal,
        "thumbnail" => [
            "url" => $avatarUrl
        ],
        "fields" => [
            [
                "name" => "👤 Jogador",
                "value" => "**`{$nick}`** ({$tipoContaFormatada})",
                "inline" => true
            ],
            [
                "name" => "🌍 Servidor",
                "value" => "**{$servidor}**",
                "inline" => true
            ],
            [
                "name" => "💎 Pacote VIP",
                "value" => "**{$vipNome}**",
                "inline" => true
            ],
            [
                "name" => "💰 Valor Pago",
                "value" => "R$ {$valorFormatado}",
                "inline" => true
            ],
            [
                "name" => "🧾 Transação (ID)",
                "value" => "`{$txid}`",
                "inline" => true
            ],
            [
                "name" => "⚡ Status",
                "value" => "✅ **Aprovado Instantâneo**",
                "inline" => true
            ]
        ],
        "footer" => [
            "text" => "Rede Nerds • Loja Oficial",
            "icon_url" => "https://redenerds.com.br/assets/images/logo.webp"
        ],
        "timestamp" => date('c')
    ];

    $payload = json_encode([
        "username" => "Loja RedeNerds",
        "avatar_url" => "https://redenerds.com.br/assets/images/logo.webp",
        "embeds" => [$embed]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: DiscordBot (RedeNerdsLoja, 1.0)'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 5
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("ERRO cURL DISCORD LOJA: " . $curlError);
        return false;
    }

    return ($httpCode >= 200 && $httpCode < 300);
}

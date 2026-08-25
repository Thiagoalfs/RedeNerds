<?php
/**
 * auth_api.php
 *
 * Middleware para controle de acesso e autenticação das APIs.
 *
 * Regras de Acesso:
 * 1. Se informada uma API Key válida (Header X-API-Key, Bearer Token ou ?api_key=), libera o acesso.
 * 2. Se for requisição interna do próprio site (AJAX/fetch via Referer legítimo + Same-Origin), libera.
 * 3. Se for acesso direto pela barra de endereço (navegador) ou cliente externo sem API Key, BLOQUEIA (403).
 */

function verificarAcessoApi(bool $exigirApiKeyApenas = false): void {
    $headers = function_exists('getallheaders') ? getallheaders() : [];

    // 1. Procura a API Key nos headers ou query string
    $apiKeyEnviada = $_SERVER['HTTP_X_API_KEY']
        ?? ($headers['X-API-Key']
        ?? ($headers['x-api-key']
        ?? ($_GET['api_key']
        ?? '')));

    if (empty($apiKeyEnviada) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $apiKeyEnviada = trim($matches[1]);
        }
    }

    // Se uma API Key válida foi informada, autoriza imediatamente
    if (defined('API_SECRET_KEY') && !empty($apiKeyEnviada) && hash_equals(API_SECRET_KEY, (string)$apiKeyEnviada)) {
        return;
    }

    // Se a rota exige ESTRITAMENTE a API Key (ex: servidor de Minecraft, bot ou rota privada)
    if ($exigirApiKeyApenas) {
        http_response_code(403);
        echo json_encode([
            "erro" => "Acesso negado: API Key inválida ou ausente."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. Bloqueia navegação direta na barra de endereço (Sec-Fetch-Mode: navigate)
    $secFetchMode = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
    if ($secFetchMode === 'navigate') {
        http_response_code(403);
        echo json_encode([
            "erro" => "Acesso negado: Acesso direto não permitido. Forneça uma API Key válida no header X-API-Key ou no parâmetro ?api_key=."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. Valida se a requisição é um fetch/AJAX interno legítimo do site (com Referer ou Origin obrigatórios)
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $secFetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';

    $dominiosAutorizados = [
        'redenerds.com.br',
        'www.redenerds.com.br',
        'localhost',
        '127.0.0.1'
    ];

    // Se tiver Sec-Fetch-Site, deve ser same-origin ou same-site
    if (!empty($secFetchSite) && !in_array($secFetchSite, ['same-origin', 'same-site'], true)) {
        http_response_code(403);
        echo json_encode([
            "erro" => "Acesso negado: Origem não permitida."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ehFetchInternoValido = false;

    if (!empty($origin)) {
        $originHost = parse_url($origin, PHP_URL_HOST);
        if ($originHost && in_array(strtolower($originHost), $dominiosAutorizados, true)) {
            $ehFetchInternoValido = true;
        }
    }

    if (!empty($referer)) {
        $refererHost = parse_url($referer, PHP_URL_HOST);
        if ($refererHost && in_array(strtolower($refererHost), $dominiosAutorizados, true)) {
            $ehFetchInternoValido = true;
        }
    }

    if ($ehFetchInternoValido) {
        return;
    }

    // Se não tem API Key e não é requisição interna de página do site, bloqueia!
    http_response_code(403);
    echo json_encode([
        "erro" => "Acesso negado: Requisição não autorizada. Forneça uma API Key válida no header X-API-Key ou no parâmetro ?api_key=."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

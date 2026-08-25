<?php
/**
 * auth_api.php
 *
 * Middleware para controle de acesso e autenticação das APIs.
 *
 * Regras de Acesso:
 * 1. Se informada uma API Key válida (Header X-API-Key, Bearer Token ou ?api_key=), libera o acesso.
 * 2. Se for requisição legítima de dentro do próprio site (mesmo domínio, Origin/Referer autorizados ou localhost), libera o acesso para o frontend.
 * 3. Se não atender a nenhum dos critérios acima, bloqueia com HTTP 403 Forbidden.
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

    // 2. Valida se a requisição provém do frontend do site (Same-Origin / Referer / Localhost)
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    $dominiosAutorizados = [
        'redenerds.com.br',
        'www.redenerds.com.br',
        'localhost',
        '127.0.0.1'
    ];

    $ehOrigemAutorizada = false;

    if (!empty($origin)) {
        $originHost = parse_url($origin, PHP_URL_HOST);
        if ($originHost && in_array(strtolower($originHost), $dominiosAutorizados, true)) {
            $ehOrigemAutorizada = true;
        }
    } elseif (!empty($referer)) {
        $refererHost = parse_url($referer, PHP_URL_HOST);
        if ($refererHost && in_array(strtolower($refererHost), $dominiosAutorizados, true)) {
            $ehOrigemAutorizada = true;
        }
    } elseif (!empty($host)) {
        $hostLimpo = explode(':', $host)[0];
        if (in_array(strtolower($hostLimpo), $dominiosAutorizados, true)) {
            $ehOrigemAutorizada = true;
        }
    }

    if ($ehOrigemAutorizada) {
        return;
    }

    // Requisição externa não autorizada sem API Key
    http_response_code(403);
    echo json_encode([
        "erro" => "Acesso negado: Requisição não autorizada. Forneça uma API Key válida no header X-API-Key."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

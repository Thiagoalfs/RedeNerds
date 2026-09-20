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

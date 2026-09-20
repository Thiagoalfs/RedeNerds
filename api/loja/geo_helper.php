<?php
/**
 * geo_helper.php
 * Determinação da sugestão de modalidade de pagamento (nacional vs internacional)
 * combinando geolocalização por IP confiável e sinais do navegador.
 */

declare(strict_types=1);

require_once __DIR__ . "/ip_helper.php";

/**
 * Valida o formato do timezone do cliente.
 */
function validarTimezone(?string $tz): ?string {
    if ($tz === null) return null;
    $tz = trim($tz);
    if (strlen($tz) >= 3 && strlen($tz) <= 40 && preg_match('/^[A-Za-z0-9_\/+\-]{3,40}$/', $tz)) {
        return $tz;
    }
    return null;
}

/**
 * Valida o formato do idioma do cliente.
 */
function validarIdioma(?string $lang): ?string {
    if ($lang === null) return null;
    $lang = trim($lang);
    if (strlen($lang) >= 2 && strlen($lang) <= 10 && preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,4})?$/', $lang)) {
        return $lang;
    }
    return null;
}

/**
 * Verifica se um timezone corresponde ao território brasileiro.
 */
function isTimezoneBrasileiro(?string $tz): bool {
    if (empty($tz)) return false;
    $regex = '/^America\/(Sao_Paulo|Fortaleza|Cuiaba|Manaus|Belem|Recife|Bahia|Porto_Velho|Boa_Vista|Campo_Grande|Maceio|Rio_Branco|Araguaina|Noronha|Santarem|Eirunepe)$/i';
    return (bool)preg_match($regex, $tz);
}

/**
 * Verifica se um idioma corresponde ao português (pt ou pt-BR).
 */
function isIdiomaBrasileiro(?string $lang): bool {
    if (empty($lang)) return false;
    return (bool)preg_match('/^pt(-BR)?$/i', $lang);
}

/**
 * Retorna a sugestão de localidade ('nacional' ou 'internacional') e a origem da decisão.
 * 
 * Regra:
 * - IP = BR => nacional
 * - IP estrangeiro + sinal brasileiro (fuso BR ou pt/pt-BR) => nacional
 * - Apenas quando IP estrangeiro (ou não-BR) E sinal do navegador estrangeiro => internacional
 * - Sem sinais claros => nacional (default)
 * 
 * @param string|null $clientTz Timezone enviado pelo navegador (ex: 'America/Sao_Paulo', 'Europe/Berlin')
 * @param string|null $clientLang Idioma enviado pelo navegador (ex: 'pt-BR', 'en-US', 'de')
 * @return array{sugestao: string, origem: string}
 */
function obterSugestaoLocalidade(?string $clientTz = null, ?string $clientLang = null): array {
    $paisIp = obterPaisIp(); // Retorna 'BR', 'US', 'DE', 'XX', etc. apenas se Cloudflare legítimo, senão null
    $tzValido = validarTimezone($clientTz);
    $langValido = validarIdioma($clientLang);

    $sinalBrasileiro = (isTimezoneBrasileiro($tzValido) || isIdiomaBrasileiro($langValido));
    $sinalEstrangeiro = (!empty($tzValido) && !isTimezoneBrasileiro($tzValido)) || (!empty($langValido) && !isIdiomaBrasileiro($langValido));

    // 1. IP explicitamente brasileiro
    if ($paisIp === 'BR') {
        return [
            'sugestao' => 'nacional',
            'origem'   => 'cf_country'
        ];
    }

    // 2. Se há sinal de navegador brasileiro (mesmo com IP estrangeiro / VPN / Locaweb sem CF)
    if ($sinalBrasileiro) {
        return [
            'sugestao' => 'nacional',
            'origem'   => ($paisIp !== null ? 'cf_country' : 'browser_signal')
        ];
    }

    // 3. IP estrangeiro confirmado pela Cloudflare (diferente de BR) e sem sinal brasileiro
    if ($paisIp !== null && $paisIp !== 'BR') {
        return [
            'sugestao' => 'internacional',
            'origem'   => 'cf_country'
        ];
    }

    // 4. Sem IP Cloudflare, mas sinal do navegador claramente estrangeiro
    if ($sinalEstrangeiro) {
        return [
            'sugestao' => 'internacional',
            'origem'   => 'browser_signal'
        ];
    }

    // 5. Fallback padrão seguro
    return [
        'sugestao' => 'nacional',
        'origem'   => 'default'
    ];
}

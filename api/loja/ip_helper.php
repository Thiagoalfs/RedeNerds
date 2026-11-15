<?php
/**
 * ip_helper.php
 * Resolução segura do IP real do cliente com validação rigorosa de proxies da Cloudflare (anti-spoofing).
 */

declare(strict_types=1);

/**
 * Lista de blocos CIDR IPv4 oficiais da Cloudflare.
 */
const CLOUDFLARE_IPV4_CIDRS = [
    '173.245.48.0/20',
    '103.21.244.0/22',
    '103.22.200.0/22',
    '103.31.4.0/22',
    '141.101.64.0/18',
    '108.162.192.0/18',
    '190.93.240.0/20',
    '188.114.96.0/20',
    '197.234.240.0/22',
    '198.41.128.0/17',
    '162.158.0.0/15',
    '104.16.0.0/13',
    '104.24.0.0/14',
    '172.64.0.0/13',
    '131.0.72.0/22'
];

/**
 * Lista de blocos CIDR IPv6 oficiais da Cloudflare.
 */
const CLOUDFLARE_IPV6_CIDRS = [
    '2400:cb00::/32',
    '2606:4700::/32',
    '2803:f800::/32',
    '2405:b500::/32',
    '2405:8100::/32',
    '2a06:98c0::/29',
    '2c0f:f248::/32'
];

/**
 * Verifica se um endereço IP pertence a uma determinada faixa CIDR (IPv4 ou IPv6).
 */
function ipPertenceCidr(string $ip, string $cidr): bool {
    $parts = explode('/', $cidr, 2);
    if (count($parts) !== 2) {
        return false;
    }

    $subnet = $parts[0];
    $mask = (int)$parts[1];

    $ipBinary = @inet_pton($ip);
    $subnetBinary = @inet_pton($subnet);

    if ($ipBinary === false || $subnetBinary === false) {
        return false;
    }

    if (strlen($ipBinary) !== strlen($subnetBinary)) {
        return false;
    }

    $bytesCount = strlen($ipBinary);
    $fullBytes = intdiv($mask, 8);
    $remainingBits = $mask % 8;

    if ($fullBytes > $bytesCount) {
        return false;
    }

    // Compara os bytes inteiros
    if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($subnetBinary, 0, $fullBytes)) {
        return false;
    }

    // Compara os bits restantes do último byte parcial, se houver
    if ($remainingBits > 0 && $fullBytes < $bytesCount) {
        $bitmask = (0xFF << (8 - $remainingBits)) & 0xFF;
        $ipByte = ord($ipBinary[$fullBytes]);
        $subByte = ord($subnetBinary[$fullBytes]);
        if (($ipByte & $bitmask) !== ($subByte & $bitmask)) {
            return false;
        }
    }

    return true;
}

/**
 * Verifica se o IP fornecido é um endereço de proxy oficial da Cloudflare.
 */
function isCloudflareIp(string $ip): bool {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        foreach (CLOUDFLARE_IPV4_CIDRS as $cidr) {
            if (ipPertenceCidr($ip, $cidr)) {
                return true;
            }
        }
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        foreach (CLOUDFLARE_IPV6_CIDRS as $cidr) {
            if (ipPertenceCidr($ip, $cidr)) {
                return true;
            }
        }
    }
    return false;
}

function isProxyInterno(string $ip): bool {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        foreach (['127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'] as $cidr) {
            if (ipPertenceCidr($ip, $cidr)) {
                return true;
            }
        }
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        foreach (['::1/128', 'fc00::/7'] as $cidr) {
            if (ipPertenceCidr($ip, $cidr)) {
                return true;
            }
        }
    }
    return false;
}

function normalizarIpv4Mapeado(string $ip): string {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $bin = @inet_pton($ip);
        if ($bin !== false && strlen($bin) === 16 && substr($bin, 0, 12) === str_repeat("\x00", 10) . "\xff\xff") {
            $ipv4 = inet_ntop(substr($bin, 12));
            if ($ipv4 !== false) {
                return $ipv4;
            }
        }
    }
    return $ip;
}

function resolverIpAposProxyInterno(string $remoteAddr): string {
    $remoteAddr = normalizarIpv4Mapeado($remoteAddr);
    if (!isProxyInterno($remoteAddr)) {
        return $remoteAddr;
    }
    $xff = trim($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($xff !== '') {
        $partes = array_reverse(array_map('trim', explode(',', $xff)));
        foreach ($partes as $cand) {
            $cand = normalizarIpv4Mapeado($cand);
            if (filter_var($cand, FILTER_VALIDATE_IP) && !isProxyInterno($cand)) {
                return $cand;
            }
        }
    }
    $xReal = normalizarIpv4Mapeado(trim($_SERVER['HTTP_X_REAL_IP'] ?? ''));
    if ($xReal !== '' && filter_var($xReal, FILTER_VALIDATE_IP) && !isProxyInterno($xReal)) {
        return $xReal;
    }
    return $remoteAddr;
}

/**
 * Obtém o IP real do cliente.
 * Se REMOTE_ADDR for um proxy interno (ex.: Caddy/Docker), resolve o peer externo via X-Forwarded-For (direita para esquerda).
 * Se o peer for da Cloudflare, aceita HTTP_CF_CONNECTING_IP.
 * Caso contrário, ignora headers de proxy para prevenir spoofing e retorna REMOTE_ADDR.
 */
function obterIpRealCliente(): string {
    $remoteAddr = resolverIpAposProxyInterno(trim($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));

    if (isCloudflareIp($remoteAddr)) {
        $cfIp = trim($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
        if (strpos($cfIp, ',') !== false) {
            $cfIp = trim(explode(',', $cfIp)[0]);
        }
        $cfIp = normalizarIpv4Mapeado($cfIp);
        if (filter_var($cfIp, FILTER_VALIDATE_IP)) {
            if (defined('LOJA_DEBUG_LOG') && LOJA_DEBUG_LOG === true) {
                error_log("[IP_HELPER DEBUG] IP resolvido via Cloudflare: {$cfIp} (Proxy: {$remoteAddr})");
            }
            return $cfIp;
        }
    }

    if (defined('LOJA_DEBUG_LOG') && LOJA_DEBUG_LOG === true) {
        error_log("[IP_HELPER DEBUG] IP direto (REMOTE_ADDR): {$remoteAddr}");
    }

    return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '127.0.0.1';
}

/**
 * Obtém o código de país do IP do cliente caso venha de um proxy Cloudflare legítimo.
 * Retorna null se não estiver atrás da Cloudflare ou se o header não estiver presente.
 */
function obterPaisIp(): ?string {
    $remoteAddr = resolverIpAposProxyInterno(trim($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));

    if (isCloudflareIp($remoteAddr) && !empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $country = strtoupper(trim($_SERVER['HTTP_CF_IPCOUNTRY']));
        if (in_array($country, ['XX', 'T1'], true)) {
            return null;
        }
        if (preg_match('/^[A-Z]{2}$/', $country)) {
            return $country;
        }
    }

    return null;
}

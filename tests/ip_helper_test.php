<?php
/**
 * ip_helper_test.php
 * Testes unitários para resolução de IP e validação de CIDR da Cloudflare.
 */

declare(strict_types=1);

require_once __DIR__ . "/test_bootstrap.php";

echo "==> Executando testes de Resolução de IP (ip_helper)...\n";

// 1. Teste de matching CIDR IPv4
assert(ipPertenceCidr('173.245.48.1', '173.245.48.0/20') === true, "Falha: 173.245.48.1 deve pertencer a 173.245.48.0/20");
assert(ipPertenceCidr('173.245.63.254', '173.245.48.0/20') === true, "Falha: 173.245.63.254 deve pertencer a 173.245.48.0/20");
assert(ipPertenceCidr('173.245.64.1', '173.245.48.0/20') === false, "Falha: 173.245.64.1 NÃO deve pertencer a 173.245.48.0/20");
echo "  [PASS] Matching de CIDR IPv4 validado com precisão.\n";

// 2. Teste de IP Cloudflare
assert(isCloudflareIp('173.245.48.10') === true, "Falha: IP CF deveria retornar true");
assert(isCloudflareIp('187.100.50.20') === false, "Falha: IP não-CF deveria retornar false");
echo "  [PASS] isCloudflareIp diferencia corretamente IPs da Cloudflare e IPs diretos.\n";

// 3. Teste anti-spoofing em conexão direta (Locaweb)
$_SERVER['REMOTE_ADDR'] = '187.100.50.20';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '1.2.3.4'; // Tentativa de spoofing
$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';

$ipResolvido = obterIpRealCliente();
assert($ipResolvido === '187.100.50.20', "Falha de segurança: REMOTE_ADDR direto deve ser usado e headers forjados devem ser ignorados");
assert(obterPaisIp() === null, "Falha: país de IP deve ser null quando não for Cloudflare legítimo");
echo "  [PASS] Anti-spoofing protegeu com sucesso em conexões diretas.\n";

// 4. Teste em conexão legítima atrás da Cloudflare
$_SERVER['REMOTE_ADDR'] = '108.162.192.50'; // IP oficial CF
$_SERVER['HTTP_CF_CONNECTING_IP'] = '189.40.10.5';
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'BR';

$ipResolvidoCf = obterIpRealCliente();
assert($ipResolvidoCf === '189.40.10.5', "Falha: CF-Connecting-IP legítimo deve ser retornado");
assert(obterPaisIp() === 'BR', "Falha: CF-IPCountry deve retornar 'BR'");
echo "  [PASS] Conexão legítima da Cloudflare resolve IP do visitante e país.\n";

echo "✔ Todos os testes de ip_helper passaram com sucesso!\n\n";

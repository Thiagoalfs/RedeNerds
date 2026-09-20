<?php
/**
 * localidade_test.php
 * Testes para as regras de sugestão de localidade (nacional vs internacional).
 */

declare(strict_types=1);

require_once __DIR__ . "/test_bootstrap.php";

echo "==> Executando testes de Detecção de Localidade...\n";

// 1. IP direto (Locaweb / sem Cloudflare) com fuso e idioma brasileiros -> nacional
$_SERVER['REMOTE_ADDR'] = '187.100.50.20';
unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_CF_IPCOUNTRY']);

$res1 = obterSugestaoLocalidade('America/Sao_Paulo', 'pt-BR');
assert($res1['sugestao'] === 'nacional', "Falha: fuso e idioma BR devem sugerir nacional");
echo "  [PASS] Fuso America/Sao_Paulo e idioma pt-BR sugerem nacional.\n";

// 2. Fuso de Eirunepe (Acre/Amazonas) -> nacional
$res2 = obterSugestaoLocalidade('America/Eirunepe', 'en-US');
assert($res2['sugestao'] === 'nacional', "Falha: fuso America/Eirunepe deve sugerir nacional mesmo com idioma en-US");
echo "  [PASS] Fuso America/Eirunepe com idioma en-US sugere nacional.\n";

// 3. Usuário no exterior com fuso e idioma estrangeiros -> internacional
$res3 = obterSugestaoLocalidade('Europe/Berlin', 'de-DE');
assert($res3['sugestao'] === 'internacional', "Falha: fuso e idioma europeus devem sugerir internacional");
assert($res3['origem'] === 'browser_signal', "Falha: origem deve ser browser_signal");
echo "  [PASS] Fuso Europe/Berlin e idioma de-DE sugerem internacional via browser_signal.\n";

// 4. Usuário nos EUA com fuso e idioma dos EUA -> internacional
$res4 = obterSugestaoLocalidade('America/New_York', 'en-US');
assert($res4['sugestao'] === 'internacional', "Falha: fuso e idioma US devem sugerir internacional");
echo "  [PASS] Fuso America/New_York e idioma en-US sugerem internacional.\n";

// 5. Sem sinais (default) -> nacional
$res5 = obterSugestaoLocalidade(null, null);
assert($res5['sugestao'] === 'nacional', "Falha: sem sinais deve retornar fallback seguro 'nacional'");
assert($res5['origem'] === 'default', "Falha: origem deve ser default");
echo "  [PASS] Sem sinais retorna fallback padrão 'nacional'.\n";

// 6. Testes com IP de Proxy Cloudflare legítimo
// 173.245.48.5 pertence ao bloco 173.245.48.0/20 da Cloudflare
$_SERVER['REMOTE_ADDR'] = '173.245.48.5';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '200.200.200.200';
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'BR';

$resCfBr = obterSugestaoLocalidade('Europe/Berlin', 'de');
assert($resCfBr['sugestao'] === 'nacional', "Falha: CF-IPCountry BR deve sugerir nacional");
assert($resCfBr['origem'] === 'cf_country', "Falha: origem deve ser cf_country");
echo "  [PASS] Cloudflare IPCountry BR sugere nacional com origem cf_country.\n";

$_SERVER['HTTP_CF_IPCOUNTRY'] = 'US';
$resCfUs = obterSugestaoLocalidade('America/New_York', 'en');
assert($resCfUs['sugestao'] === 'internacional', "Falha: CF-IPCountry US com sinal US deve sugerir internacional");
assert($resCfUs['origem'] === 'cf_country', "Falha: origem deve ser cf_country");
echo "  [PASS] Cloudflare IPCountry US sugere internacional com origem cf_country.\n";

// 7. Cloudflare com país desconhecido (XX ou T1) e sinal brasileiro
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'XX';
$resCfXx = obterSugestaoLocalidade('America/Fortaleza', 'pt-BR');
assert($resCfXx['sugestao'] === 'nacional', "Falha: CF-IPCountry XX com sinal brasileiro deve sugerir nacional");
echo "  [PASS] Cloudflare IPCountry XX com sinal brasileiro sugere nacional.\n";

echo "✔ Todos os testes de localidade passaram com sucesso!\n\n";

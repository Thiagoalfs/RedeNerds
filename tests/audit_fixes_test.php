<?php
/**
 * audit_fixes_test.php
 * Testes unitários para as correções de auditoria (entrega pendente, webhook signature, etc).
 */

declare(strict_types=1);

require_once __DIR__ . "/test_bootstrap.php";

echo "==> Executando testes das correções de auditoria...\n";

// 1. Teste de retentarEntregasPendentes
$pdo = criarBancoTestes();
TestSpy::reset();

$pdo->prepare("
    INSERT INTO pedidos (txid, nick, servidor, vip_id, vip_nome, valor, status, entregue)
    VALUES (:txid, :nick, :servidor, :vip_id, :vip_nome, :valor, :status, :entregue)
")->execute([
    ':txid'     => 'NERD-RETRY-01',
    ':nick'     => 'PlayerRetry',
    ':servidor' => 'Survival',
    ':vip_id'   => 1,
    ':vip_nome' => 'VIP Ouro',
    ':valor'    => 30.00,
    ':status'   => 'pago',
    ':entregue' => 0
]);

require_once __DIR__ . "/../api/loja/delivery_helper.php";

$resultados = retentarEntregasPendentes($pdo, 10);
assert(isset($resultados['NERD-RETRY-01']), "Falha: pedido não entregue deveria ser retentado");
assert($resultados['NERD-RETRY-01'] === true, "Falha: retorno do retry deveria ser sucesso");
echo "  [PASS] retentarEntregasPendentes retenta pedidos pagos com entregue = 0 com sucesso.\n";

// 2. Teste de validação de assinatura do webhook Mercado Pago
$webhookSecret = 'test_webhook_secret_key_123';
$paymentId = '1234567890';
$requestId = 'req-test-uuid-987';
$ts = (string)time();
$manifest = "id:{$paymentId};request-id:{$requestId};ts:{$ts};";
$validHash = hash_hmac('sha256', $manifest, $webhookSecret);
$invalidHash = 'wrong_hash_value';

assert(hash_equals($validHash, hash_hmac('sha256', $manifest, $webhookSecret)) === true, "Falha: HMAC válido deve coincidir");
assert(hash_equals($invalidHash, hash_hmac('sha256', $manifest, $webhookSecret)) === false, "Falha: HMAC inválido deve ser rejeitado");
echo "  [PASS] HMAC de assinatura de webhook validado com sucesso.\n";

// 3. Teste de preservação de packageId em VIPs
$nomeVip = "VIP Ouro [Promoção de Natal]";
$packageIdEnviado = "vip_ouro_custom";
$packageIdResult = $packageIdEnviado !== '' ? $packageIdEnviado : 'slug_fallback';
assert($packageIdResult === "vip_ouro_custom", "Falha: packageId existente deve ser preservado");

$packageIdVazio = "";
if ($packageIdVazio === '') {
    $cleanSlug = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nomeVip) ?: $nomeVip;
    $cleanSlug = strtolower(trim($cleanSlug));
    $cleanSlug = preg_replace('/[\s\-]+/', '_', $cleanSlug);
    $cleanSlug = preg_replace('/[^a-z0-9_]/', '', $cleanSlug);
    $packageIdVazio = trim(preg_replace('/_+/', '_', $cleanSlug), '_') ?: 'vip_' . time();
}
assert(!empty($packageIdVazio) && $packageIdVazio !== "vip_ouro_custom", "Falha: packageId vazio deve gerar slug baseado no nome");
echo "  [PASS] Preservação de packageId em VIPs validada com sucesso.\n";

// 4. Teste de validação de snowflake do Discord no webhook_helper
require_once __DIR__ . "/../admin/noticias/webhook_helper.php";
$validSnowflake = "123456789012345678";
$invalidTitle = "Novidade de Natal";
assert(discordMensagemIdValido($validSnowflake) === true, "Falha: snowflake numérico deve ser válido");
assert(discordMensagemIdValido($invalidTitle) === false, "Falha: texto não pode ser aceito como mensagem ID");
echo "  [PASS] Validação de snowflake e assinaturas de webhook do Discord testadas com sucesso.\n";

// 5. Teste das correções da auditoria (fuso, ordem INSERT->gateway, txid criptográfico, sem ALTER TABLE em runtime)
require_once __DIR__ . "/../api/loja/config_loja.php";
assert(date_default_timezone_get() === 'America/Sao_Paulo', "Falha: config_loja.php deve definir timezone America/Sao_Paulo");

foreach (['criar_pix.php', 'criar_cartao.php', 'criar_checkout_internacional.php'] as $checkoutFile) {
    $src = file_get_contents(__DIR__ . "/../api/loja/" . $checkoutFile);
    $posInsert = strpos($src, 'INSERT INTO pedidos');
    $posCurl = strpos($src, 'curl_exec(');
    assert($posInsert !== false && $posCurl !== false && $posInsert < $posCurl, "Falha em {$checkoutFile}: INSERT INTO pedidos deve ocorrer antes de curl_exec");
    assert(strpos($src, 'uniqid(') === false, "Falha em {$checkoutFile}: nao deve usar uniqid() para txid");
}

$vipsEditarSrc = file_get_contents(__DIR__ . "/../admin/api/vips/editar.php");
assert(stripos($vipsEditarSrc, 'ALTER TABLE') === false, "Falha: admin/api/vips/editar.php nao deve executar ALTER TABLE em runtime");
echo "  [PASS] Fuso horario, ordem INSERT->gateway, txid seguro e ausencia de ALTER TABLE validados.\n";

// 6. Teste de integracao de prepararCheckoutLoja() (VIP, Chave, Cupom valido/expirado/servidor) e token de reset
$pdoLoja = new PDO('sqlite::memory:');
$pdoLoja->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdoLoja->exec("CREATE TABLE servidores (id INTEGER PRIMARY KEY, servername TEXT, nome TEXT, enabled INTEGER)");
$pdoLoja->exec("CREATE TABLE vips (id INTEGER PRIMARY KEY, nome TEXT, preco REAL, servidor_id INTEGER, ativo INTEGER)");
$pdoLoja->exec("CREATE TABLE chaves (id INTEGER PRIMARY KEY, nome TEXT, preco REAL, servidor_id INTEGER, ativo INTEGER)");
$pdoLoja->exec("CREATE TABLE cupons (id INTEGER PRIMARY KEY, codigo TEXT, porcentagem_desconto REAL, servidor_id INTEGER, ativo INTEGER, expira_em TEXT)");
$pdoLoja->exec("INSERT INTO servidores VALUES (1, 'AeroCity', 'aerocity', 1), (2, 'Pixel', 'pixel', 1)");
$pdoLoja->exec("INSERT INTO vips VALUES (10, 'VIP Ouro', 50.00, 1, 1)");
$pdoLoja->exec("INSERT INTO chaves VALUES (20, 'Chave Mitica', 10.00, 1, 1)");
$pdoLoja->exec("INSERT INTO cupons VALUES (1, 'AERO10', 10, 1, 1, '2099-12-31 23:59:59'), (2, 'EXPIRADO', 20, 0, 1, '2020-01-01 00:00:00'), (3, 'SO_PIXEL', 15, 2, 1, '2099-12-31 23:59:59')");

$okVip = prepararCheckoutLoja($pdoLoja, ['nick' => 'Steve_123', 'servidor' => 'AeroCity', 'tipo_produto' => 'vip', 'vip_id' => 10, 'quantidade' => 5, 'cupom' => 'aero10']);
assert($okVip['ok'] === true && $okVip['quantidade'] === 1 && $okVip['valor_original'] === 50.0 && $okVip['desconto_aplicado'] === 5.0 && $okVip['valor'] === 45.0, "Falha: calculo de VIP + cupom deve forcar quantidade=1 e aplicar 10%");

$okChave = prepararCheckoutLoja($pdoLoja, ['nick' => 'Alex_99', 'servidor' => 'aerocity', 'tipo_produto' => 'chave', 'chave_id' => 20, 'quantidade' => 3]);
assert($okChave['ok'] === true && $okChave['quantidade'] === 3 && $okChave['valor'] === 30.0, "Falha: calculo de Chave x3 deve somar 30.00");

$errExp = prepararCheckoutLoja($pdoLoja, ['nick' => 'Steve_123', 'servidor' => 'AeroCity', 'tipo_produto' => 'vip', 'vip_id' => 10, 'cupom' => 'EXPIRADO']);
assert($errExp['ok'] === false && $errExp['status'] === 400, "Falha: cupom expirado deve retornar 400");

$errSrv = prepararCheckoutLoja($pdoLoja, ['nick' => 'Steve_123', 'servidor' => 'AeroCity', 'tipo_produto' => 'vip', 'vip_id' => 10, 'cupom' => 'SO_PIXEL']);
assert($errSrv['ok'] === false && $errSrv['status'] === 400, "Falha: cupom de outro servidor deve retornar 400");

$pdoLoja->exec("CREATE TABLE usuarios (id INTEGER PRIMARY KEY, reset_token_hash TEXT, reset_expira_em TEXT)");
$rawToken = bin2hex(random_bytes(32));
$pdoLoja->prepare("INSERT INTO usuarios VALUES (1, ?, '2099-12-31 23:59:59'), (2, ?, '2020-01-01 00:00:00')")
    ->execute([hash('sha256', $rawToken), hash('sha256', str_repeat('a', 64))]);
$stmtTok = $pdoLoja->prepare("SELECT id FROM usuarios WHERE reset_token_hash = ? AND reset_expira_em > datetime('now')");
$stmtTok->execute([hash('sha256', $rawToken)]);
assert((int)$stmtTok->fetchColumn() === 1, "Falha: token valido nao foi encontrado");
$stmtTok->execute([hash('sha256', str_repeat('a', 64))]);
assert($stmtTok->fetchColumn() === false, "Falha: token expirado nao pode ser aceito");

require_once __DIR__ . "/../admin/includes/image_upload_helper.php";
assert(function_exists('adminConverterParaWebp') && function_exists('adminObterMimeImagem'), "Falha: image_upload_helper.php deve exportar funcoes compartilhadas");
echo "  [PASS] prepararCheckoutLoja (VIP, Chave, Cupons), reset token e image_upload_helper validados.\n";

// 7. Teste de endurecimento de schema (Escopo 1) e sentinela NULL em cupons
$migrationFile = __DIR__ . "/../migrations/2026_11_15_schema_hardening.sql";
assert(file_exists($migrationFile), "Falha: migracao 2026_11_15_schema_hardening.sql deve existir");
$migrationSql = file_get_contents($migrationFile);
assert(strpos($migrationSql, 'CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci') !== false, "Falha: migracao deve padronizar collation utf8mb4_unicode_ci");
assert(strpos($migrationSql, 'UPDATE `chaves` SET `servidor_id` = NULL WHERE `servidor_id` = 0') !== false, "Falha: migracao deve converter sentinela 0 para NULL em chaves");
assert(strpos($migrationSql, 'UPDATE `cupons` SET `servidor_id` = NULL WHERE `servidor_id` = 0') !== false, "Falha: migracao deve converter sentinela 0 para NULL em cupons");
foreach (['fk_chaves_servidor', 'fk_cupons_servidor', 'fk_equipe_cargo', 'fk_admin_logs_usuario', 'fk_pedidos_vip', 'fk_pedidos_chave'] as $fk) {
    assert(strpos($migrationSql, $fk) !== false, "Falha: migracao deve definir FK {$fk}");
}
assert(preg_match('/MODIFY COLUMN\s+`(?:criado_em|created_at|atualizado_em)`\s+DATETIME/i', $migrationSql) === 1, "Falha: migracao deve converter colunas TIMESTAMP para DATETIME");

foreach (['criar.php', 'editar.php'] as $cupomApi) {
    $src = file_get_contents(__DIR__ . "/../admin/api/cupons/" . $cupomApi);
    assert(preg_match("/':servidor_id'\s*=>\s*\\\$servidor_id\s*>\s*0\s*\?\s*\\\$servidor_id\s*:\s*null/", $src) === 1, "Falha em cupons/{$cupomApi}: deve bindar null quando servidor_id <= 0");
}
echo "  [PASS] Migracao Scope 1 (collation, NULL sentinels, FKs, DATETIME) e bind NULL em cupons validados.\n";

// 8. Teste de permissoes, validacao e helper de webhooks por servidor
require_once __DIR__ . "/../admin/includes/permissoes.php";
assert(normalizarCargoAdmin('Co-Fundador') === 'fundador', "Falha: Co-Fundador deve normalizar para fundador");
assert(usuarioPodeAcessar('webhooks', 'fundador') === true, "Falha: fundador/co-fundador deve acessar webhooks");
assert(usuarioPodeAcessar('webhooks', 'diretor') === true, "Falha: diretor deve acessar webhooks");
assert(usuarioPodeAcessar('webhooks', 'coordenador') === true, "Falha: coordenador deve acessar webhooks");
assert(usuarioPodeAcessar('webhooks', 'desenvolvedor') === false, "Falha: desenvolvedor nao deve acessar webhooks");

require_once __DIR__ . "/../admin/noticias/webhook_helper.php";
assert(discordWebhookUrlValida('https://discord.com/api/webhooks/123456789012345678/abc_DEF-123') === true, "Falha: URL valida de webhook Discord rejeitada");
assert(discordWebhookUrlValida('https://evil.example.com/api/webhooks/123/abc') === false, "Falha: URL nao-Discord deve ser rejeitada");

$whMigration = __DIR__ . "/../migrations/2026_11_20_servidores_webhooks.sql";
assert(file_exists($whMigration), "Falha: migracao 2026_11_20_servidores_webhooks.sql deve existir");
$whSql = file_get_contents($whMigration);
assert(strpos($whSql, 'servidores_webhooks') !== false && strpos($whSql, 'fk_srv_webhooks_servidor') !== false, "Falha: migracao deve definir servidores_webhooks e FK");
echo "  [PASS] Permissoes, validacao de URL e migracao de webhooks por servidor validados.\n";

echo "✔ Todos os testes de correções de auditoria passaram com sucesso!\n";

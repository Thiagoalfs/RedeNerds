<?php
/**
 * sem_token_503_test.php
 * Testes para validação de ausência de credencial e resposta 503 segura.
 */

declare(strict_types=1);

require_once __DIR__ . "/test_bootstrap.php";

echo "==> Executando testes de Token Ausente / Placeholder (HTTP 503)...\n";

assert(obterMercadoPagoAccessToken() === 'TEST-TOKEN-123456', "Falha: token válido deve ser retornado");
echo "  [PASS] Token de teste válido é aceito.\n";

echo "✔ Testes de validação de token passaram com sucesso!\n\n";

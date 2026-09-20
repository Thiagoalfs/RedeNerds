<?php
/**
 * run_all_tests.php
 * Script executor de toda a suite de testes unitários e de integração.
 */

declare(strict_types=1);

echo "======================================================================\n";
echo "       INICIANDO SUITE DE TESTES: SEGURANÇA E PAGAMENTOS LOJA         \n";
echo "======================================================================\n\n";

$testFiles = [
    __DIR__ . "/ip_helper_test.php",
    __DIR__ . "/localidade_test.php",
    __DIR__ . "/cupom_idempotencia_test.php",
    __DIR__ . "/webhook_atomicidade_test.php",
    __DIR__ . "/sem_token_503_test.php"
];

$total = count($testFiles);
$pass = 0;

foreach ($testFiles as $file) {
    $nome = basename($file);
    echo "▶ Executando: {$nome}...\n";
    $output = [];
    $retCode = 0;
    exec("php " . escapeshellarg($file), $output, $retCode);

    echo implode("\n", $output) . "\n";

    if ($retCode === 0) {
        $pass++;
    } else {
        echo "❌ ERRO: Teste {$nome} falhou com código de saída {$retCode}!\n";
    }
}

echo "======================================================================\n";
if ($pass === $total) {
    echo " 🎉 RESULTADO FINAL: TODOS OS {$total} ARQUIVOS DE TESTE PASSARAM! (100%)\n";
} else {
    echo " ⚠️ RESULTADO FINAL: {$pass}/{$total} TESTES PASSARAM. HOUVE FALHAS!\n";
}
echo "======================================================================\n";

exit($pass === $total ? 0 : 1);

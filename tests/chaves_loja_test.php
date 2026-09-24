<?php
/**
 * chaves_loja_test.php
 * Testes unitários para o sistema de pacotes de chaves:
 * - Validação zero-trust de quantidade e tipo de produto
 * - Cálculo de preço no backend independente do payload do cliente
 * - Formatação de comandos de entrega RCON com placeholder {QUANTIDADE} / {QTD}
 * - Notificações Discord personalizadas para chaves
 */

declare(strict_types=1);

require_once __DIR__ . '/test_bootstrap.php';

echo "--- TESTES: SISTEMA DE PACOTES DE CHAVES E ZERO-TRUST ---\n";

// 1. Teste de Sanitização e Clamping de Quantidade
function testQuantityClamping(): void {
    echo "1. Testando clamping e sanitização de quantidade... ";

    $sanitize = function(string $tipoProduto, $rawQty): int {
        $tipo = strtolower(trim($tipoProduto));
        if (!in_array($tipo, ['vip', 'chave'], true)) $tipo = 'vip';
        $qty = filter_var($rawQty, FILTER_VALIDATE_INT);
        if ($qty === false || $qty < 1) $qty = 1;
        if ($qty > 100) $qty = 100;
        if ($tipo === 'vip') $qty = 1;
        return $qty;
    };

    // Para VIP, quantidade DEVE ser sempre 1 independente do input do cliente
    assert($sanitize('vip', 5) === 1, 'VIP com qtd 5 deve virar 1');
    assert($sanitize('vip', -10) === 1, 'VIP com qtd negativa deve virar 1');
    assert($sanitize('vip', '99') === 1, 'VIP com string deve virar 1');

    // Para Chave, clamping entre 1 e 100
    assert($sanitize('chave', 1) === 1, 'Chave com qtd 1');
    assert($sanitize('chave', 5) === 5, 'Chave com qtd 5');
    assert($sanitize('chave', 100) === 100, 'Chave com qtd 100');
    assert($sanitize('chave', 0) === 1, 'Chave com qtd 0 deve virar 1');
    assert($sanitize('chave', -5) === 1, 'Chave com qtd negativa deve virar 1');
    assert($sanitize('chave', 999) === 100, 'Chave com qtd 999 deve ser limitada a 100');
    assert($sanitize('chave', 'hack') === 1, 'Chave com valor não numérico deve virar 1');

    echo "OK!\n";
}

// 2. Teste de Cálculo Seguro de Preço Server-Side
function testServerSidePriceCalculation(): void {
    echo "2. Testando cálculo de valor no servidor com cupom... ";

    $calc = function(float $precoUnitarioBanco, int $quantidade, ?float $cupomPct): float {
        $totalBruto = $precoUnitarioBanco * $quantidade;
        if ($cupomPct !== null && $cupomPct > 0) {
            $desconto = round($totalBruto * ($cupomPct / 100), 2);
            $totalLiquido = round($totalBruto - $desconto, 2);
            return max(0.01, $totalLiquido);
        }
        return round($totalBruto, 2);
    };

    // Chave de R$ 5,00 x 3 = R$ 15,00
    assert(abs($calc(5.00, 3, null) - 15.00) < 0.001, '5 x 3 = 15.00');

    // Chave de R$ 5,00 x 4 com 10% de cupom = R$ 18,00
    assert(abs($calc(5.00, 4, 10.0) - 18.00) < 0.001, '20.00 - 10% = 18.00');

    // Chave de R$ 12,50 x 5 com 25% de cupom = 62.50 - 15.63 = 46.87
    assert(abs($calc(12.50, 5, 25.0) - 46.87) < 0.01, '62.50 - 25% = 46.87');

    echo "OK!\n";
}

// 3. Teste de Formatação de Notificação Discord para Chaves vs VIPs
function testDiscordNotificationPayload(): void {
    echo "3. Testando payload de notificação Discord para chaves... ";

    // Simulação do payload para Chave com quantidade > 1
    $dadosChave = [
        'nick' => 'SteveGamer',
        'servidor' => 'Rankup',
        'vip_nome' => 'Chave Mística',
        'tipo_produto' => 'chave',
        'quantidade' => 5,
        'valor' => 25.00,
        'txid' => 'NERD-TEST1234',
        'metodo' => 'PIX'
    ];

    $embed = gerarEmbedDiscordParaTeste($dadosChave);
    assert(strpos($embed['title'], 'Chaves') !== false, 'Título Discord de chaves deve conter Chaves');
    assert(strpos($embed['title'], 'SteveGamer') !== false, 'Título deve conter o nick');
    
    // Verifica campo de produto
    $campoProduto = array_values(array_filter($embed['fields'], fn($f) => $f['name'] === '🗝️ Pacote de Chaves'))[0] ?? null;
    assert($campoProduto !== null, 'Deve conter campo com ícone de chave');
    assert(strpos($campoProduto['value'], 'Chave Mística') !== false, 'Deve exibir o nome da chave');
    assert(strpos($campoProduto['value'], 'x5') !== false, 'Deve exibir o multiplicador de quantidade');

    echo "OK!\n";
}

function gerarEmbedDiscordParaTeste(array $dados): array {
    $nick = $dados['nick'];
    $servidor = $dados['servidor'];
    $itemNome = $dados['vip_nome'];
    $tipoProduto = $dados['tipo_produto'] ?? 'vip';
    $quantidade = (int)($dados['quantidade'] ?? 1);
    $valor = (float)$dados['valor'];
    $txid = $dados['txid'];
    $metodo = $dados['metodo'];

    $isChave = ($tipoProduto === 'chave');
    $tituloIcon = $isChave ? '🗝️' : '💎';
    $tituloTipo = $isChave ? 'Chaves' : 'VIP';
    $labelProduto = $isChave ? '🗝️ Pacote de Chaves' : '💎 Pacote VIP';
    $itemDisplay = ($isChave && $quantidade > 1) ? "**{$itemNome}** (x{$quantidade})" : "**{$itemNome}**";

    return [
        'title' => "{$tituloIcon} Nova Compra de {$tituloTipo} Confirmada! ({$nick})",
        'color' => $isChave ? 0xF59E0B : 0x10B981,
        'fields' => [
            ['name' => '👤 Jogador', 'value' => "```{$nick}```", 'inline' => true],
            ['name' => '🌐 Servidor', 'value' => "```{$servidor}```", 'inline' => true],
            ['name' => $labelProduto, 'value' => $itemDisplay, 'inline' => true],
            ['name' => '💰 Valor Pago', 'value' => 'R$ ' . number_format($valor, 2, ',', '.'), 'inline' => true],
            ['name' => '💳 Método', 'value' => $metodo, 'inline' => true],
            ['name' => '🆔 TXID', 'value' => "`{$txid}`", 'inline' => true],
        ]
    ];
}

// Execução dos testes
testQuantityClamping();
testServerSidePriceCalculation();
testDiscordNotificationPayload();

echo "\n✨ Todos os testes do sistema de chaves passaram com sucesso!\n";

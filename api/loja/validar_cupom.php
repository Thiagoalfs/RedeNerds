<?php
/**
 * validar_cupom.php
 * Endpoint para validação e cálculo em tempo real de cupons de desconto.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config_loja.php";
aplicarCorsLoja();

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$configPaths = [
    __DIR__ . "/../../../config.php",
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}
if (!$configPath) {
    http_response_code(500);
    echo json_encode(["erro" => "config.php não encontrado."], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $configPath;
require_once __DIR__ . "/../auth_api.php";
require_once __DIR__ . "/ip_helper.php";
verificarAcessoApi();

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$cupomCodigo = strtoupper(trim((string)($data['cupom'] ?? ($_GET['cupom'] ?? ''))));
$tipoProduto = strtolower(trim((string)($data['tipo_produto'] ?? ($_GET['tipo_produto'] ?? 'vip'))));
if (!in_array($tipoProduto, ['vip', 'chave'], true)) {
    $tipoProduto = 'vip';
}

$vipId = (int)($data['vip_id'] ?? ($_GET['vip_id'] ?? 0));
$chaveId = (int)($data['chave_id'] ?? ($_GET['chave_id'] ?? 0));
$itemId = ($tipoProduto === 'chave') ? ($chaveId > 0 ? $chaveId : $vipId) : $vipId;

$quantidade = filter_var($data['quantidade'] ?? ($_GET['quantidade'] ?? 1), FILTER_VALIDATE_INT);
if ($quantidade === false || $quantidade < 1) $quantidade = 1;
if ($quantidade > 100) $quantidade = 100;
if ($tipoProduto === 'vip') $quantidade = 1;

if (empty($cupomCodigo)) {
    http_response_code(400);
    echo json_encode(["erro" => "Informe o código do cupom."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($itemId <= 0) {
    http_response_code(400);
    echo json_encode(["erro" => "Identificador do produto inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["erro" => "Banco de dados indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Rate Limiting anti-bruteforce (máx 10 tentativas por IP em 10 minutos)
$clientIp = obterIpRealCliente();
try {
    $pdo->exec("DELETE FROM rate_limits_loja WHERE tentativa_em < (NOW() - INTERVAL 1 HOUR)");

    $stmtRl = $pdo->prepare("
        SELECT COUNT(*) FROM rate_limits_loja 
        WHERE ip = :ip 
          AND endpoint = 'validar_cupom' 
          AND tentativa_em >= (NOW() - INTERVAL 10 MINUTE)
    ");
    $stmtRl->execute([':ip' => $clientIp]);
    $tentativasRecentes = (int)$stmtRl->fetchColumn();

    if ($tentativasRecentes >= 10) {
        http_response_code(429);
        echo json_encode([
            "erro" => "Muitas tentativas de validação de cupom. Por favor, aguarde 10 minutos antes de tentar novamente."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmtLog = $pdo->prepare("INSERT INTO rate_limits_loja (ip, endpoint, tentativa_em) VALUES (:ip, 'validar_cupom', NOW())");
    $stmtLog->execute([':ip' => $clientIp]);
} catch (Exception $e) {
    error_log("Erro no rate limiting de cupom: " . $e->getMessage());
}

try {
    // 1. Busca o preço oficial do item e seus dados de servidor
    if ($tipoProduto === 'chave') {
        $stmtItem = $pdo->prepare("SELECT id, nome, preco, servidor_id FROM chaves WHERE id = :id AND (ativo = 1 OR ativo IS NULL) LIMIT 1");
    } else {
        $stmtItem = $pdo->prepare("SELECT id, nome, preco, servidor_id FROM vips WHERE id = :id AND (ativo = 1 OR ativo IS NULL) LIMIT 1");
    }
    $stmtItem->execute([':id' => $itemId]);
    $itemRow = $stmtItem->fetch(PDO::FETCH_ASSOC);

    if (!$itemRow) {
        http_response_code(404);
        echo json_encode(["erro" => ($tipoProduto === 'chave' ? "Pacote de Chaves" : "Pacote VIP") . " não encontrado ou inativo."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $precoUnitario = (float)$itemRow['preco'];
    $precoOriginal = round($precoUnitario * $quantidade, 2);

    // 2. Busca o cupom
    $stmtCupom = $pdo->prepare("SELECT * FROM cupons WHERE codigo = :codigo LIMIT 1");
    $stmtCupom->execute([':codigo' => $cupomCodigo]);
    $cupomRow = $stmtCupom->fetch(PDO::FETCH_ASSOC);

    if (!$cupomRow) {
        http_response_code(404);
        echo json_encode(["erro" => "Cupom '{$cupomCodigo}' não encontrado."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Verifica se está ativo
    if (!$cupomRow['ativo']) {
        http_response_code(400);
        echo json_encode(["erro" => "O cupom '{$cupomCodigo}' está desativado no momento."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Verifica expiração automática
    $now = time();
    $expiraTs = strtotime((string)$cupomRow['expira_em']);
    if ($expiraTs && $expiraTs < $now) {
        http_response_code(400);
        echo json_encode([
            "erro" => "O cupom '{$cupomCodigo}' expirou em " . date('d/m/Y H:i', $expiraTs) . "."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. Validação de restrição de servidor
    $cupomServidorId = (int)($cupomRow['servidor_id'] ?? 0);
    if ($cupomServidorId > 0) {
        $itemServidorId = (int)($itemRow['servidor_id'] ?? 0);
        if ($itemServidorId > 0 && $itemServidorId !== $cupomServidorId) {
            $stmtSrvNome = $pdo->prepare("SELECT servername FROM servidores WHERE id = :id LIMIT 1");
            $stmtSrvNome->execute([':id' => $cupomServidorId]);
            $srvNome = $stmtSrvNome->fetchColumn() ?: 'outro servidor';

            http_response_code(400);
            echo json_encode([
                "erro" => "O cupom '{$cupomCodigo}' é válido exclusivamente para compras no servidor {$srvNome}."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // 4. Calcula o desconto
    $porcentagem = (float)$cupomRow['porcentagem_desconto'];
    $valorDesconto = round($precoOriginal * ($porcentagem / 100), 2);
    $precoFinal = max(0.01, round($precoOriginal - $valorDesconto, 2));

    echo json_encode([
        "success"              => true,
        "cupom"                => $cupomRow['codigo'],
        "porcentagem"          => $porcentagem,
        "desconto"             => $valorDesconto,
        "porcentagem_desconto" => $porcentagem,
        "valor_desconto"       => $valorDesconto,
        "preco_unitario"       => $precoUnitario,
        "quantidade"           => $quantidade,
        "preco_original"       => $precoOriginal,
        "preco_final"          => $precoFinal,
        "mensagem"             => "Cupom '{$cupomRow['codigo']}' aplicado com sucesso! ({$porcentagem}% de desconto)"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Erro ao validar cupom: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["erro" => "Erro ao validar cupom no servidor."], JSON_UNESCAPED_UNICODE);
}
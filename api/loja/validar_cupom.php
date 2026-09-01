<?php
/**
 * validar_cupom.php
 * Endpoint para validação e cálculo em tempo real de cupons de desconto.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

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

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$cupomCodigo = strtoupper(trim($data['cupom'] ?? ($_GET['cupom'] ?? '')));
$vipId = intval($data['vip_id'] ?? ($_GET['vip_id'] ?? 0));

if (empty($cupomCodigo)) {
    http_response_code(400);
    echo json_encode(["erro" => "Informe o código do cupom."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($vipId <= 0) {
    http_response_code(400);
    echo json_encode(["erro" => "Identificador do VIP inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["erro" => "Banco de dados indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. Busca o preço oficial do VIP
    $stmtVip = $pdo->prepare("SELECT id, nome, preco FROM vips WHERE id = :id AND (ativo = 1 OR ativo IS NULL) LIMIT 1");
    $stmtVip->execute([':id' => $vipId]);
    $vipRow = $stmtVip->fetch(PDO::FETCH_ASSOC);

    if (!$vipRow) {
        http_response_code(404);
        echo json_encode(["erro" => "Pacote VIP não encontrado ou inativo."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $precoOriginal = (float)$vipRow['preco'];

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
    $expiraTs = strtotime($cupomRow['expira_em']);
    if ($expiraTs && $expiraTs < $now) {
        http_response_code(400);
        echo json_encode([
            "erro" => "O cupom '{$cupomCodigo}' expirou em " . date('d/m/Y H:i', $expiraTs) . "."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. Calcula desconto
    $porcentagem = (float)$cupomRow['porcentagem_desconto'];
    $valorDesconto = round($precoOriginal * ($porcentagem / 100), 2);
    $precoFinal = max(0.01, round($precoOriginal - $valorDesconto, 2));

    echo json_encode([
        "success" => true,
        "cupom" => $cupomRow['codigo'],
        "porcentagem" => $porcentagem,
        "desconto" => $valorDesconto,
        "preco_original" => $precoOriginal,
        "preco_final" => $precoFinal,
        "mensagem" => "Cupom de " . number_format($porcentagem, 1, ',', '.') . "% aplicado com sucesso!"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro ao validar cupom: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
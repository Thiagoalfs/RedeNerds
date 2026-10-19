<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../chaves/chave_upload.php";

exigirCSRF();

$servidor_id = (int)($_POST['servidor_id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$packageId = trim($_POST['packageId'] ?? '');
$precoRaw = str_replace(',', '.', trim($_POST['preco'] ?? '0'));
$preco = (float)$precoRaw;
$destaque = !empty($_POST['destaque']) ? 1 : 0;
$ativo = !empty($_POST['ativo']) ? 1 : 0;
$cor_tipo = trim($_POST['cor_tipo'] ?? 'colorwheel');
if (!in_array($cor_tipo, ['colorwheel', 'string'], true)) {
    $cor_tipo = 'colorwheel';
}
$cor_string = trim($_POST['cor_string'] ?? '');
$cor1 = trim($_POST['cor1'] ?? '#FFD700');
$cor2 = trim($_POST['cor2'] ?? '#FFA500');
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor1)) $cor1 = '#FFD700';
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor2)) $cor2 = '#FFA500';

if ($cor_tipo === 'string' && $cor_string !== '') {
    if (preg_match_all('/(?:&#|#)([0-9A-Fa-f]{6})/', $cor_string, $matchesHex)) {
        if (!empty($matchesHex[1])) {
            $cor1 = '#' . strtoupper($matchesHex[1][0]);
            $cor2 = '#' . strtoupper(end($matchesHex[1]));
        }
    }
}

$stmtSrv = $pdo->prepare("SELECT id, servername FROM servidores WHERE id = :id LIMIT 1");
$stmtSrv->execute([':id' => $servidor_id]);
$srv = $stmtSrv->fetch(PDO::FETCH_ASSOC);

if (!$srv) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Selecione um servidor válido para este pacote."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($nome === '' || mb_strlen($nome) < 2) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Informe um nome válido para o pacote (mínimo 2 caracteres)."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($packageId === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Informe o Package ID correspondente no plugin."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($preco <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "O preço deve ser maior que zero."], JSON_UNESCAPED_UNICODE);
    exit;
}

[$imagem, $erro_imagem] = processarImagemChave(null);
if ($erro_imagem) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => $erro_imagem], JSON_UNESCAPED_UNICODE);
    exit;
}

$vantagensPost = $_POST['vantagens'] ?? [];
if (is_string($vantagensPost)) {
    $vantagensPost = explode("\n", $vantagensPost);
}
$vantagens = array_values(array_filter(array_map('trim', (array)$vantagensPost), fn($v) => $v !== ''));
$vantagensJson = !empty($vantagens) ? json_encode($vantagens, JSON_UNESCAPED_UNICODE) : null;

try {
    $stmt = $pdo->prepare("
        INSERT INTO chaves (servidor_id, servidor, nome, imagem, packageId, preco, destaque, vantagens, ativo, cor1, cor2, cor_tipo, cor_string)
        VALUES (:servidor_id, :servidor, :nome, :imagem, :packageId, :preco, :destaque, :vantagens, :ativo, :cor1, :cor2, :cor_tipo, :cor_string)
    ");
    $stmt->execute([
        ':servidor_id' => $servidor_id,
        ':servidor'    => $srv['servername'],
        ':nome'        => $nome,
        ':imagem'      => !empty($imagem) ? $imagem : null,
        ':packageId'   => $packageId,
        ':preco'       => $preco,
        ':destaque'    => $destaque,
        ':vantagens'   => $vantagensJson,
        ':ativo'       => $ativo,
        ':cor1'        => $cor1,
        ':cor2'        => $cor2,
        ':cor_tipo'    => $cor_tipo,
        ':cor_string'  => $cor_string,
    ]);

    $novoId = (int)$pdo->lastInsertId();

    require_once __DIR__ . "/../../../api/cache_helper.php";
    invalidarCache('chaves');

    $busca = $pdo->prepare("SELECT * FROM chaves WHERE id = :id LIMIT 1");
    $busca->execute([':id' => $novoId]);
    $chave = $busca->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => true,
        "mensagem" => "Pacote '{$nome}' cadastrado com sucesso!",
        "chave" => $chave
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("Erro ao criar pacote de chaves: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Erro ao salvar no banco de dados."], JSON_UNESCAPED_UNICODE);
    exit;
}

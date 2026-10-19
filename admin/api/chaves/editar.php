<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../chaves/chave_upload.php";

exigirCSRF();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "ID de pacote inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM chaves WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$chaveAtual = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$chaveAtual) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Pacote não encontrado."], JSON_UNESCAPED_UNICODE);
    exit;
}

$servidor_id = (int)($_POST['servidor_id'] ?? $chaveAtual['servidor_id']);
$nome = trim($_POST['nome'] ?? $chaveAtual['nome']);
$packageId = trim($_POST['packageId'] ?? $chaveAtual['packageId']);
$precoRaw = str_replace(',', '.', trim($_POST['preco'] ?? (string)$chaveAtual['preco']));
$preco = (float)$precoRaw;
$destaque = !empty($_POST['destaque']) ? 1 : 0;
$ativo = !empty($_POST['ativo']) ? 1 : 0;
$cor_tipo = trim($_POST['cor_tipo'] ?? ($chaveAtual['cor_tipo'] ?? 'colorwheel'));
if (!in_array($cor_tipo, ['colorwheel', 'string'], true)) {
    $cor_tipo = 'colorwheel';
}
$cor_string = trim($_POST['cor_string'] ?? ($chaveAtual['cor_string'] ?? ''));
$cor1 = trim($_POST['cor1'] ?? $chaveAtual['cor1'] ?? '#FFD700');
$cor2 = trim($_POST['cor2'] ?? $chaveAtual['cor2'] ?? '#FFA500');
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

[$imagem, $erro_imagem] = processarImagemChave($chaveAtual['imagem'] ?? null);
if ($erro_imagem) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => $erro_imagem], JSON_UNESCAPED_UNICODE);
    exit;
}

$vantagensPost = $_POST['vantagens'] ?? null;
if ($vantagensPost !== null) {
    if (is_string($vantagensPost)) {
        $vantagensPost = explode("\n", $vantagensPost);
    }
    $vantagens = array_values(array_filter(array_map('trim', (array)$vantagensPost), fn($v) => $v !== ''));
    $vantagensJson = !empty($vantagens) ? json_encode($vantagens, JSON_UNESCAPED_UNICODE) : null;
} else {
    $vantagensJson = $chaveAtual['vantagens'];
}

try {
    $stmtUp = $pdo->prepare("
        UPDATE chaves 
        SET servidor_id = :servidor_id, servidor = :servidor, nome = :nome, imagem = :imagem,
            packageId = :packageId, preco = :preco, destaque = :destaque,
            vantagens = :vantagens, ativo = :ativo, cor1 = :cor1, cor2 = :cor2,
            cor_tipo = :cor_tipo, cor_string = :cor_string
        WHERE id = :id
    ");
    $stmtUp->execute([
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
        ':id'          => $id,
    ]);

    require_once __DIR__ . "/../../../api/cache_helper.php";
    invalidarCache('chaves');

    $busca = $pdo->prepare("SELECT * FROM chaves WHERE id = :id LIMIT 1");
    $busca->execute([':id' => $id]);
    $chaveAtualizada = $busca->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => true,
        "mensagem" => "Pacote '{$nome}' atualizado com sucesso!",
        "chave" => $chaveAtualizada
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("Erro ao atualizar pacote de chaves: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Erro ao salvar alterações no banco de dados."], JSON_UNESCAPED_UNICODE);
    exit;
}

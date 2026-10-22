<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "ID de VIP inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM vips WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$vipAtual = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vipAtual) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Pacote VIP não encontrado."], JSON_UNESCAPED_UNICODE);
    exit;
}

$servidor_id = (int)($_POST['servidor_id'] ?? $vipAtual['servidor_id']);
$nome = trim($_POST['nome'] ?? $vipAtual['nome']);
$packageId = trim($_POST['packageId'] ?? ($vipAtual['packageId'] ?? ''));
$precoRaw = str_replace(',', '.', trim($_POST['preco'] ?? (string)$vipAtual['preco']));
$preco = (float)$precoRaw;
$duracao_dias = isset($_POST['duracao_dias']) ? (int)$_POST['duracao_dias'] : (int)($vipAtual['duracao_dias'] ?? 30);
if ($duracao_dias < 0) $duracao_dias = 0;
$destaque = !empty($_POST['destaque']) ? 1 : 0;
$ativo = !empty($_POST['ativo']) ? 1 : 0;
$cor_tipo = trim($_POST['cor_tipo'] ?? ($vipAtual['cor_tipo'] ?? 'colorwheel'));
if (!in_array($cor_tipo, ['colorwheel', 'string'], true)) {
    $cor_tipo = 'colorwheel';
}
$cor_string = trim($_POST['cor_string'] ?? ($vipAtual['cor_string'] ?? ''));
$cor1 = trim($_POST['cor1'] ?? ($vipAtual['cor1'] ?? '#ffffff'));
$cor2 = trim($_POST['cor2'] ?? ($vipAtual['cor2'] ?? '#ffffff'));
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor1)) $cor1 = '#ffffff';
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor2)) $cor2 = '#ffffff';

if ($cor_tipo === 'string' && $cor_string !== '') {
    if (preg_match_all('/(?:&#|#)([0-9A-Fa-f]{6})/', $cor_string, $matchesHex)) {
        if (!empty($matchesHex[1])) {
            $cor1 = '#' . strtoupper($matchesHex[1][0]);
            $cor2 = '#' . strtoupper(end($matchesHex[1]));
        }
    }
}

$srv = null;
if ($servidor_id > 0) {
    $stmtSrv = $pdo->prepare("SELECT id, servername FROM servidores WHERE id = :id LIMIT 1");
    $stmtSrv->execute([':id' => $servidor_id]);
    $srv = $stmtSrv->fetch(PDO::FETCH_ASSOC);

    if (!$srv) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "Selecione um servidor válido para este pacote VIP."], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($nome === '' || mb_strlen($nome) < 2) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Informe um nome válido para o pacote VIP (mínimo 2 caracteres)."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($preco <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "O preço deve ser maior que zero."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($packageId === '') {
    $packageId = 'vip_' . strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace([' ', '-'], '_', $nome)));
}

$vantagensPost = $_POST['vantagens'] ?? null;
if ($vantagensPost !== null) {
    if (is_string($vantagensPost)) {
        $vantagensPost = explode("\n", $vantagensPost);
    }
    $vantagens = array_values(array_filter(array_map('trim', (array)$vantagensPost), fn($v) => $v !== ''));
    if (empty($vantagens)) {
        $vantagens = ["TAG [tag] na tablist"];
    }
    $vantagensJson = json_encode($vantagens, JSON_UNESCAPED_UNICODE);
} else {
    $vantagensJson = $vipAtual['vantagens'];
}

try {
    $stmtUp = $pdo->prepare("
        UPDATE vips 
        SET servidor_id = :servidor_id, nome = :nome, packageId = :packageId, preco = :preco, 
            duracao_dias = :duracao_dias, destaque = :destaque, 
            vantagens = :vantagens, ativo = :ativo, cor1 = :cor1, cor2 = :cor2,
            cor_tipo = :cor_tipo, cor_string = :cor_string
        WHERE id = :id
    ");
    $stmtUp->execute([
        ':servidor_id'  => ($servidor_id > 0 ? $servidor_id : null),
        ':nome'         => $nome,
        ':packageId'    => $packageId,
        ':preco'        => $preco,
        ':duracao_dias' => $duracao_dias,
        ':destaque'     => $destaque,
        ':vantagens'    => $vantagensJson,
        ':ativo'        => $ativo,
        ':cor1'         => $cor1,
        ':cor2'         => $cor2,
        ':cor_tipo'     => $cor_tipo,
        ':cor_string'   => $cor_string,
        ':id'           => $id,
    ]);

    require_once __DIR__ . "/../../../api/cache_helper.php";
    invalidarCache('vips');

    $busca = $pdo->prepare("SELECT * FROM vips WHERE id = :id LIMIT 1");
    $busca->execute([':id' => $id]);
    $vipAtualizado = $busca->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => true,
        "mensagem" => "Pacote VIP '{$nome}' atualizado com sucesso!",
        "vip" => $vipAtualizado
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("Erro ao atualizar pacote VIP: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Erro ao salvar VIP no banco de dados."], JSON_UNESCAPED_UNICODE);
    exit;
}

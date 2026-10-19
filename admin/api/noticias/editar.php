<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../noticias/capa_upload.php";
require_once __DIR__ . "/../../noticias/webhook_helper.php";

exigirCSRF();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "ID de novidade inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM novidades WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$noticiaAtual = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$noticiaAtual) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Novidade não encontrada."], JSON_UNESCAPED_UNICODE);
    exit;
}

$titulo          = trim($_POST['titulo'] ?? $noticiaAtual['titulo']);
$conteudo        = trim($_POST['conteudo'] ?? $noticiaAtual['conteudo']);
$autor           = trim($_POST['autor'] ?? $noticiaAtual['autor']);
$category        = trim($_POST['category'] ?? $noticiaAtual['category']);
$categoria_envio = trim($_POST['categoria_envio'] ?? $noticiaAtual['categoria_envio']);

if ($titulo === '' || mb_strlen($titulo) < 3) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Informe um título válido para a novidade (mínimo 3 caracteres)."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($conteudo === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Informe o conteúdo da novidade."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($autor === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Selecione o autor da postagem."], JSON_UNESCAPED_UNICODE);
    exit;
}

$capaAtual = $noticiaAtual['capa'] ?? null;
if (!empty($_POST['remover_capa'])) {
    apagarCapaAntigaSeForUpload($capaAtual, '');
    $capa = null;
    $erro_upload = null;
} else {
    [$capa, $erro_upload] = processarCapa($capaAtual);
}

if ($erro_upload) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => $erro_upload], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $upd = $pdo->prepare("
        UPDATE novidades 
        SET titulo = :titulo, conteudo = :conteudo, autor = :autor, capa = :capa, category = :category, categoria_envio = :categoria_envio
        WHERE id = :id
    ");
    $upd->execute([
        ':titulo'          => $titulo,
        ':conteudo'        => $conteudo,
        ':autor'           => $autor,
        ':capa'            => $capa,
        ':category'        => $category,
        ':categoria_envio' => $categoria_envio,
        ':id'              => $id
    ]);

    require_once __DIR__ . "/../../../api/cache_helper.php";
    invalidarCache('novidades');

    // Sincroniza a mensagem do Discord com o conteúdo editado
    $discordMsgId = $noticiaAtual['discord_msg_id'] ?? null;
    if (!empty($discordMsgId)) {
        editarWebhookDiscord($categoria_envio, $titulo, $conteudo, $autor, $capa, $category, $discordMsgId);
    }

    $busca = $pdo->prepare("SELECT * FROM novidades WHERE id = :id LIMIT 1");
    $busca->execute([':id' => $id]);
    $noticiaAtualizada = $busca->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => true,
        "mensagem" => "Novidade '{$titulo}' atualizada com sucesso!",
        "noticia" => $noticiaAtualizada
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("Erro ao atualizar novidade via API: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Erro ao salvar alterações no banco de dados."], JSON_UNESCAPED_UNICODE);
    exit;
}

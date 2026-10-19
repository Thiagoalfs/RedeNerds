<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../noticias/capa_upload.php";
require_once __DIR__ . "/../../noticias/webhook_helper.php";

exigirCSRF();

$titulo          = trim($_POST['titulo'] ?? '');
$conteudo        = trim($_POST['conteudo'] ?? '');
$autor           = trim($_POST['autor'] ?? '');
$category        = trim($_POST['category'] ?? 'NerdSky');
$categoria_envio = trim($_POST['categoria_envio'] ?? 'Anúncios');

$enviar_webhook  = !empty($_POST['enviar_webhook']);
$marcar_everyone = !empty($_POST['marcar_everyone']);

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

[$capa, $erro_upload] = processarCapa(null);
if ($erro_upload) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => $erro_upload], JSON_UNESCAPED_UNICODE);
    exit;
}

$discordMsgId = null;
if ($enviar_webhook) {
    $discordMsgId = enviarWebhookDiscord($categoria_envio, $titulo, $conteudo, $autor, $capa, $category, $marcar_everyone);
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO novidades (titulo, conteudo, autor, capa, category, categoria_envio, discord_msg_id) 
        VALUES (:titulo, :conteudo, :autor, :capa, :category, :categoria_envio, :discord_msg_id)
    ");
    $stmt->execute([
        ':titulo'          => $titulo,
        ':conteudo'        => $conteudo,
        ':autor'           => $autor,
        ':capa'            => $capa,
        ':category'        => $category,
        ':categoria_envio' => $categoria_envio,
        ':discord_msg_id'  => $discordMsgId
    ]);

    $novoId = (int)$pdo->lastInsertId();

    require_once __DIR__ . "/../../../api/cache_helper.php";
    invalidarCache('novidades');

    $busca = $pdo->prepare("SELECT * FROM novidades WHERE id = :id LIMIT 1");
    $busca->execute([':id' => $novoId]);
    $noticia = $busca->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => true,
        "mensagem" => "Novidade '{$titulo}' publicada com sucesso!",
        "noticia" => $noticia
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("Erro ao salvar novidade via API: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Erro ao salvar novidade no banco de dados."], JSON_UNESCAPED_UNICODE);
    exit;
}

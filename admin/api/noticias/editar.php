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
$autor           = trim((string)($noticiaAtual['autor'] ?? '')) ?: obterAutorLogadoEquipe($pdo ?? null);
$category        = trim($_POST['category'] ?? $noticiaAtual['category']);
$categoria_envio = trim($_POST['categoria_envio'] ?? $noticiaAtual['categoria_envio']);
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

$capaAtual = $noticiaAtual['capa'] ?? null;
[$capa, $erro_upload] = processarCapa($capaAtual);

if ($erro_upload) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => $erro_upload], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($capa)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "A imagem de capa é obrigatória."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($enviar_webhook && ($categoria_envio === '' || !obterWebhookDiscordPorServidor($pdo ?? null, $category, $categoria_envio))) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "O servidor '{$category}' não possui webhook cadastrado para o canal selecionado."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $versaoHash = trim($_POST['_versao_hash'] ?? '');
    $pdo->beginTransaction();
    exigirConcorrenciaEdicao($pdo, 'novidades', $id, $versaoHash, [], true);

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
    $pdo->commit();

    require_once __DIR__ . "/../../../api/cache_helper.php";
    invalidarCache('novidades');

    // Sincroniza a mensagem do Discord: remove do canal/servidor antigo se mudou ou foi desmarcado, e cria/edita no destino
    $discordMsgId = $noticiaAtual['discord_msg_id'] ?? null;
    $servidorOriginal = trim((string)($noticiaAtual['category'] ?? ''));
    $canalOriginal = trim((string)($noticiaAtual['categoria_envio'] ?? ''));
    $mudouDestino = (strcasecmp($servidorOriginal, $category) !== 0) || (strcasecmp($canalOriginal, $categoria_envio) !== 0);

    if (!$enviar_webhook) {
        if (!empty($discordMsgId)) {
            deletarWebhookDiscord($canalOriginal ?: $categoria_envio, $discordMsgId, $servidorOriginal ?: $category, $pdo);
            $pdo->prepare("UPDATE novidades SET discord_msg_id = NULL WHERE id = :id")->execute([':id' => $id]);
        }
    } elseif ($mudouDestino || empty($discordMsgId)) {
        if (!empty($discordMsgId)) {
            deletarWebhookDiscord($canalOriginal ?: $categoria_envio, $discordMsgId, $servidorOriginal ?: $category, $pdo);
        }
        $novoMsgId = enviarWebhookDiscord($categoria_envio, $titulo, $conteudo, $autor, $capa, $category, $marcar_everyone, $pdo);
        $pdo->prepare("UPDATE novidades SET discord_msg_id = :msg_id WHERE id = :id")->execute([':msg_id' => $novoMsgId, ':id' => $id]);
    } else {
        editarWebhookDiscord($categoria_envio, $discordMsgId, $titulo, $conteudo, $autor, $capa, $category, $pdo);
    }

    $busca = $pdo->prepare("SELECT * FROM novidades WHERE id = :id LIMIT 1");
    $busca->execute([':id' => $id]);
    $noticiaAtualizada = $busca->fetch(PDO::FETCH_ASSOC);
    if ($noticiaAtualizada) {
        $noticiaAtualizada['_versao_hash'] = gerarHashRegistro($noticiaAtualizada, [], 'novidades');
    }
    registrarLogAdmin($pdo, 'editar', 'noticias', "Editou notícia '{$titulo}' (#{$id})", $id);

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

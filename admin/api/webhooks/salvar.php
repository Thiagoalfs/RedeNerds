<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../noticias/webhook_helper.php";

exigirCSRF();

header('Content-Type: application/json; charset=utf-8');

$servidorId = (int)($_POST['servidor_id'] ?? 0);
$urlAnuncios = trim((string)($_POST['webhook_anuncios'] ?? ''));
$urlAtualizacoes = trim((string)($_POST['webhook_atualizacoes'] ?? ''));

if ($servidorId <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "Servidor inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Conexão com o banco indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

$urls = [
    'Anúncios' => $urlAnuncios,
    'Atualizações' => $urlAtualizacoes,
];

foreach ($urls as $cat => $url) {
    if ($url !== '' && !discordWebhookUrlValida($url)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "erro" => "URL de webhook inválida para '{$cat}'. Informe um link oficial do Discord (https://discord.com/api/webhooks/...)."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $stmtSrv = $pdo->prepare("SELECT id, servername FROM servidores WHERE id = :id LIMIT 1");
    $stmtSrv->execute([':id' => $servidorId]);
    $srv = $stmtSrv->fetch(PDO::FETCH_ASSOC);
    if (!$srv) {
        http_response_code(404);
        echo json_encode(["success" => false, "erro" => "Servidor não encontrado."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo->beginTransaction();
    $stmtUpsert = $pdo->prepare("
        INSERT INTO servidores_webhooks (servidor_id, categoria, webhook_url)
        VALUES (:sid, :cat, :url)
        ON DUPLICATE KEY UPDATE webhook_url = VALUES(webhook_url)
    ");
    $stmtDel = $pdo->prepare("DELETE FROM servidores_webhooks WHERE servidor_id = :sid AND categoria = :cat");

    foreach ($urls as $cat => $url) {
        if ($url === '') {
            $stmtDel->execute([':sid' => $servidorId, ':cat' => $cat]);
        } else {
            $stmtUpsert->execute([':sid' => $servidorId, ':cat' => $cat, ':url' => $url]);
        }
    }
    $pdo->commit();

    registrarLogAdmin($pdo, 'editar', 'webhooks', "Atualizou webhooks do servidor '{$srv['servername']}' (#{$servidorId})", $servidorId);

    echo json_encode([
        "success" => true,
        "mensagem" => "Webhooks de '{$srv['servername']}' atualizados com sucesso!",
        "servidor_id" => $servidorId,
        "anuncios" => $urlAnuncios !== '',
        "atualizacoes" => $urlAtualizacoes !== '',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao salvar webhooks: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Erro interno ao salvar webhooks."], JSON_UNESCAPED_UNICODE);
}

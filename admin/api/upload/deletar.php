<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../upload/upload_helper.php";

exigirCSRF();
header('Content-Type: application/json; charset=utf-8');

$input = $_POST;
if (empty($input['id'])) {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        }
    }
}

$id = (int)($input['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "ID de upload inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Conexão com o banco indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nome, caminho_publico FROM site_uploads WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(["success" => false, "erro" => "Upload não encontrado."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $assetsRoot = realpath(uploadAssetsRootFisica());
    $caminhoPub = (string)$row['caminho_publico'];
    if ($assetsRoot && str_starts_with($caminhoPub, '/assets/')) {
        $rel = substr($caminhoPub, strlen('/assets/'));
        $fisico = realpath($assetsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
        if ($fisico && str_starts_with($fisico, $assetsRoot . DIRECTORY_SEPARATOR) && is_file($fisico)) {
            @unlink($fisico);
        }
    }

    $pdo->prepare("DELETE FROM site_uploads WHERE id = :id")->execute([':id' => $id]);
    registrarLogAdmin($pdo, 'deletar', 'upload', "Deletou upload '{$row['nome']}' ({$caminhoPub})", $id);

    echo json_encode(["success" => true, "mensagem" => "Imagem excluída com sucesso."], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Erro ao deletar site_uploads: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Erro interno ao excluir imagem."], JSON_UNESCAPED_UNICODE);
}

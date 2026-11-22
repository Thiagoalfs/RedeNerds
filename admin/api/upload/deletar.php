<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../upload/upload_helper.php";

exigirCSRF();
header('Content-Type: application/json; charset=utf-8');

$input = $_POST;
if (empty($input['id']) && empty($input['caminho_publico'])) {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        }
    }
}

$id = (int)($input['id'] ?? 0);
$caminhoInput = trim((string)($input['caminho_publico'] ?? ''));

if ($id <= 0 && $caminhoInput === '') {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "Informe o ID ou o caminho da imagem."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Conexão com o banco indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $row = null;
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT id, nome, caminho_publico FROM site_uploads WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(["success" => false, "erro" => "Upload não encontrado."], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $caminhoPub = (string)$row['caminho_publico'];
        $nomeExibicao = (string)$row['nome'];
    } else {
        $caminhoPub = '/' . ltrim(str_replace('\\', '/', $caminhoInput), '/');
        if (!str_starts_with($caminhoPub, '/assets/') || str_contains($caminhoPub, '..') || str_contains($caminhoPub, "\0")) {
            http_response_code(400);
            echo json_encode(["success" => false, "erro" => "Caminho de imagem inválido."], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $relCheck = substr($caminhoPub, strlen('/assets/'));
        $posSlash = strrpos($relCheck, '/');
        $pastaCheck = $posSlash !== false ? substr($relCheck, 0, $posSlash) : '';
        if (uploadNormalizarPasta($pastaCheck) === null) {
            http_response_code(400);
            echo json_encode(["success" => false, "erro" => "Pasta de imagem não permitida."], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $nomeExibicao = basename($caminhoPub);
    }

    $assetsRoot = realpath(uploadAssetsRootFisica());
    $rel = substr($caminhoPub, strlen('/assets/'));
    $fisico = $assetsRoot ? realpath($assetsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel)) : false;
    $extPermitidas = ['webp', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'avif'];

    if (!$assetsRoot || !$fisico || !str_starts_with($fisico, $assetsRoot . DIRECTORY_SEPARATOR) || !is_file($fisico)) {
        if ($id <= 0) {
            http_response_code(404);
            echo json_encode(["success" => false, "erro" => "Arquivo não encontrado."], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        $ext = strtolower(pathinfo($fisico, PATHINFO_EXTENSION));
        if (!in_array($ext, $extPermitidas, true)) {
            http_response_code(400);
            echo json_encode(["success" => false, "erro" => "Tipo de arquivo não permitido para exclusão."], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!@unlink($fisico)) {
            http_response_code(500);
            echo json_encode(["success" => false, "erro" => "Falha ao remover o arquivo físico no servidor."], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if ($id > 0) {
        $pdo->prepare("DELETE FROM site_uploads WHERE id = :id")->execute([':id' => $id]);
    } else {
        $pdo->prepare("DELETE FROM site_uploads WHERE caminho_publico = :c")->execute([':c' => $caminhoPub]);
    }

    registrarLogAdmin($pdo, 'deletar', 'upload', "Deletou imagem '{$nomeExibicao}' ({$caminhoPub})", $id > 0 ? $id : null);

    echo json_encode(["success" => true, "mensagem" => "Imagem excluída com sucesso."], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Erro ao deletar site_uploads: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Erro interno ao excluir imagem."], JSON_UNESCAPED_UNICODE);
}

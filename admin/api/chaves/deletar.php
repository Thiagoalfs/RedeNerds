<?php
require_once __DIR__ . "/../../sessao.php";

$configPaths = [
    __DIR__ . "/config.php",
    __DIR__ . "/../config.php",
    __DIR__ . "/../../config.php",
    __DIR__ . "/../../../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}
if ($configPath) {
    require_once $configPath;
}

exigirCSRF();

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

$id = isset($input['id']) ? (int)$input['id'] : 0;
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($input['format']) && $input['format'] === 'json');

if ($id > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT nome FROM chaves WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $chave = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($chave) {
            $del = $pdo->prepare("DELETE FROM chaves WHERE id = :id");
            $del->execute([':id' => $id]);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["success" => true, "mensagem" => "Pacote '{$chave['nome']}' excluído com sucesso!"]);
                exit;
            }

            header("Location: /admin/chaves/?msg=" . urlencode("Pacote '{$chave['nome']}' excluído com sucesso!"));
            exit;
        } else {
            if ($isAjax) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["success" => false, "erro" => "Chave não encontrada."]);
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao deletar chave: " . $e->getMessage());
        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Erro ao deletar pacote de chaves."]);
            exit;
        }
    }
}

header("Location: /admin/chaves/");
exit;

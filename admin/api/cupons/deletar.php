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

if ($id <= 0) {
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "ID inválido."]);
        exit;
    }
    header("Location: /admin/cupons/?erro=" . urlencode("ID inválido."));
    exit;
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("DELETE FROM cupons WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => true]);
            exit;
        }

        header("Location: /admin/cupons/?msg=" . urlencode("Cupom deletado com sucesso!"));
        exit;
    } catch (Exception $e) {
        error_log("Erro ao deletar cupom: " . $e->getMessage());
        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Erro ao deletar cupom."]);
            exit;
        }
        header("Location: /admin/cupons/?erro=" . urlencode("Erro ao deletar cupom."));
        exit;
    }
}

header("Location: /admin/cupons/");
exit;
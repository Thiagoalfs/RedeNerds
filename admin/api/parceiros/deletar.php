<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../parceiros/parceiro_upload.php";

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
    header("Location: /admin/parceiros/?erro=" . urlencode("ID inválido."));
    exit;
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT nome, foto FROM parceiros WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $parceiro = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($parceiro) {
            $del = $pdo->prepare("DELETE FROM parceiros WHERE id = :id");
            $del->execute([':id' => $id]);

            require_once __DIR__ . "/../../../api/cache_helper.php";
            invalidarCache('parceiros');

            // Se possuía foto local em /assets/parceiros/, apaga o arquivo físico
            if (!empty($parceiro['foto'])) {
                apagarFotoParceiroAntigaSeForUpload($parceiro['foto'], '');
            }

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["success" => true]);
                exit;
            }

            header("Location: /admin/parceiros/?msg=" . urlencode("Parceiro '{$parceiro['nome']}' excluído com sucesso!"));
            exit;
        } else {
            if ($isAjax) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["success" => false, "erro" => "Parceiro não encontrado."]);
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao deletar parceiro: " . $e->getMessage());
        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Erro interno no banco de dados."]);
            exit;
        }
    }
}

header("Location: /admin/parceiros/");
exit;
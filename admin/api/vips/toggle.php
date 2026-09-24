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
    header("Location: /admin/vips/?erro=" . urlencode("ID de VIP inválido."));
    exit;
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT ativo, nome FROM vips WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $vip = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($vip) {
            $novoStatus = $vip['ativo'] ? 0 : 1;
            $upd = $pdo->prepare("UPDATE vips SET ativo = :novo WHERE id = :id");
            $upd->execute([':novo' => $novoStatus, ':id' => $id]);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["success" => true, "ativo" => $novoStatus]);
                exit;
            }

            $msg = $novoStatus ? "VIP '{$vip['nome']}' ativado com sucesso!" : "VIP '{$vip['nome']}' desativado com sucesso!";
            header("Location: /admin/vips/?msg=" . urlencode($msg));
            exit;
        } else {
            if ($isAjax) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["success" => false, "erro" => "VIP não encontrado."]);
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao alternar status do VIP: " . $e->getMessage());
        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Erro interno no banco de dados."]);
            exit;
        }
    }
}

header("Location: /admin/vips/");
exit;
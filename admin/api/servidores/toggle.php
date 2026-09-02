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

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['format']) && $_POST['format'] === 'json');

if ($id <= 0) {
    if ($isAjax) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "erro" => "ID inválido."]);
        exit;
    }
    header("Location: /admin/servidores.php?erro=" . urlencode("ID de servidor inválido."));
    exit;
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT enabled FROM servidores WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $srv = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($srv) {
            $novoStatus = $srv['enabled'] ? 0 : 1;
            $upd = $pdo->prepare("UPDATE servidores SET enabled = :novo WHERE id = :id");
            $upd->execute([':novo' => $novoStatus, ':id' => $id]);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["success" => true, "enabled" => $novoStatus]);
                exit;
            }
        } else {
            if ($isAjax) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["success" => false, "erro" => "Servidor não encontrado."]);
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao alternar status do servidor: " . $e->getMessage());
        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Erro no banco de dados."]);
            exit;
        }
    }
}

header("Location: /admin/servidores.php");
exit;
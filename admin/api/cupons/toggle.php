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
    header("Location: /admin/cupons.php?erro=" . urlencode("ID inválido."));
    exit;
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT codigo, ativo FROM cupons WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $cupom = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cupom) {
            if ($isAjax) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["success" => false, "erro" => "Cupom não encontrado."]);
                exit;
            }
            header("Location: /admin/cupons.php?erro=" . urlencode("Cupom não encontrado."));
            exit;
        }

        $novoStatus = $cupom['ativo'] ? 0 : 1;
        $upd = $pdo->prepare("UPDATE cupons SET ativo = :ativo WHERE id = :id");
        $upd->execute([':ativo' => $novoStatus, ':id' => $id]);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => true, "ativo" => $novoStatus]);
            exit;
        }

        $msg = $novoStatus ? "Cupom '{$cupom['codigo']}' ativado com sucesso!" : "Cupom '{$cupom['codigo']}' desativado com sucesso!";
        header("Location: /admin/cupons.php?msg=" . urlencode($msg));
        exit;
    } catch (Exception $e) {
        error_log("Erro ao alternar cupom: " . $e->getMessage());
        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Erro ao atualizar status."]);
            exit;
        }
        header("Location: /admin/cupons.php?erro=" . urlencode("Erro ao atualizar status."));
        exit;
    }
}

header("Location: /admin/cupons.php");
exit;
<?php
require_once __DIR__ . "/../../sessao.php";

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
        $stmt = $pdo->prepare("SELECT nome FROM vips WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $vip = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($vip) {
            $del = $pdo->prepare("DELETE FROM vips WHERE id = :id");
            $del->execute([':id' => $id]);

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["success" => true]);
                exit;
            }

            header("Location: /admin/vips/?msg=" . urlencode("VIP '{$vip['nome']}' deletado com sucesso."));
            exit;
        } else {
            if ($isAjax) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["success" => false, "erro" => "VIP não encontrado."]);
                exit;
            }
            header("Location: /admin/vips/?erro=" . urlencode("VIP não encontrado."));
            exit;
        }
    } catch (Exception $e) {
        error_log("Erro ao deletar VIP: " . $e->getMessage());
        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["success" => false, "erro" => "Erro ao deletar VIP."]);
            exit;
        }
        header("Location: /admin/vips/?erro=" . urlencode("Erro ao deletar VIP do banco de dados."));
        exit;
    }
}

header("Location: /admin/vips/");
exit;

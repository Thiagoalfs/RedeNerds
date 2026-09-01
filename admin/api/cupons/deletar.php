<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../../config.php";

exigirCSRF();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['format']) && $_POST['format'] === 'json');

if ($id <= 0) {
    if ($isAjax) {
        http_response_code(400);
        echo json_encode(["erro" => "ID inválido"]);
        exit;
    }
    header("Location: ../../cupons.php?erro=ID inválido.");
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM cupons WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($isAjax) {
        echo json_encode(["success" => true]);
        exit;
    }

    header("Location: ../../cupons.php?msg=" . urlencode("Cupom deletado com sucesso!"));
    exit;
} catch (PDOException $e) {
    if ($isAjax) {
        http_response_code(500);
        echo json_encode(["erro" => "Erro ao deletar cupom"]);
        exit;
    }
    header("Location: ../../cupons.php?erro=Erro ao deletar cupom.");
    exit;
}
<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../../config.php";

exigirCSRF();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM servidores WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($isAjax) {
            echo json_encode(["success" => true]);
            exit;
        }
    } catch (PDOException $e) {
        if ($isAjax) {
            http_response_code(500);
            echo json_encode(["erro" => "Erro ao deletar servidor."]);
            exit;
        }
    }
}

header("Location: ../../servidores.php");
exit;
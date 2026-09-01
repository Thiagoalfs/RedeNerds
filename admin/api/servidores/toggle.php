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
    header("Location: ../../servidores.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT enabled FROM servidores WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $srv = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($srv) {
        $novoStatus = $srv['enabled'] ? 0 : 1;
        $upd = $pdo->prepare("UPDATE servidores SET enabled = :novo WHERE id = :id");
        $upd->execute([':novo' => $novoStatus, ':id' => $id]);

        if ($isAjax) {
            echo json_encode(["success" => true, "enabled" => $novoStatus]);
            exit;
        }
    }
} catch (PDOException $e) {}

header("Location: ../../servidores.php");
exit;
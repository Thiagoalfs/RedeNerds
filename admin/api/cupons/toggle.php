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
    $stmt = $pdo->prepare("SELECT codigo, ativo FROM cupons WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $cupom = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cupom) {
        if ($isAjax) {
            http_response_code(404);
            echo json_encode(["erro" => "Cupom não encontrado"]);
            exit;
        }
        header("Location: ../../cupons.php?erro=Cupom não encontrado.");
        exit;
    }

    $novoStatus = $cupom['ativo'] ? 0 : 1;
    $upd = $pdo->prepare("UPDATE cupons SET ativo = :ativo WHERE id = :id");
    $upd->execute([':ativo' => $novoStatus, ':id' => $id]);

    if ($isAjax) {
        echo json_encode(["success" => true, "ativo" => $novoStatus]);
        exit;
    }

    $msg = $novoStatus ? "Cupom '{$cupom['codigo']}' ativado com sucesso!" : "Cupom '{$cupom['codigo']}' desativado com sucesso!";
    header("Location: ../../cupons.php?msg=" . urlencode($msg));
    exit;
} catch (PDOException $e) {
    if ($isAjax) {
        http_response_code(500);
        echo json_encode(["erro" => "Erro ao atualizar status"]);
        exit;
    }
    header("Location: ../../cupons.php?erro=Erro ao atualizar status.");
    exit;
}
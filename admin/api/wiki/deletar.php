<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($id > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("DELETE FROM wiki_artigos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($isAjax) {
            echo json_encode(["success" => true]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Erro ao deletar artigo wiki: " . $e->getMessage());
        if ($isAjax) {
            http_response_code(500);
            echo json_encode(["erro" => "Erro ao deletar artigo"]);
            exit;
        }
    }
}
header("Location: /admin/wiki/");
exit;
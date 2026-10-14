<?php
require_once __DIR__ . "/../../sessao.php";

exigirCSRF();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['format']) && $_POST['format'] === 'json');

if ($id > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT publicado FROM wiki_artigos WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $art = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($art) {
            $novo = $art['publicado'] ? 0 : 1;
            $upd = $pdo->prepare("UPDATE wiki_artigos SET publicado = :novo WHERE id = :id");
            $upd->execute([':novo' => $novo, ':id' => $id]);

            if ($isAjax) {
                echo json_encode(["success" => true, "publicado" => $novo]);
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao alternar status artigo wiki: " . $e->getMessage());
    }
}
header("Location: /admin/wiki/");
exit;
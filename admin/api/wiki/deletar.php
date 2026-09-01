<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../../config.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM wiki_artigos WHERE id = :id");
        $stmt->execute([':id' => $id]);
    } catch (Exception $e) {}
}
header("Location: ../../wiki.php");
exit;
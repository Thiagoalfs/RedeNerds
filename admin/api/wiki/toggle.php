<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../../config.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT publicado FROM wiki_artigos WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $art = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($art) {
            $novo = $art['publicado'] ? 0 : 1;
            $upd = $pdo->prepare("UPDATE wiki_artigos SET publicado = :novo WHERE id = :id");
            $upd->execute([':novo' => $novo, ':id' => $id]);
        }
    } catch (Exception $e) {}
}
header("Location: ../../wiki.php");
exit;
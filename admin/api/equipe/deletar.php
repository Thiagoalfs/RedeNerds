<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../../config.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM equipe WHERE id = :id");
        $stmt->execute([':id' => $id]);
    } catch (PDOException $e) {}
}

header("Location: ../../equipe.php");
exit;
<?php
require_once "sessao.php";
require_once "../../config.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: equipe.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM equipe WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $membro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$membro) {
        header("Location: equipe.php");
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM equipe WHERE id = :id");
    $stmt->execute([':id' => $id]);
} catch (PDOException $e) {
    // Silencia o erro e redireciona
}

header("Location: equipe.php");
exit;

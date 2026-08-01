<?php
require_once "sessao.php";
require_once "../../config.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: dashboard.php");
    exit;
}

try {
    // Confirma que a notícia existe antes de deletar
    $stmt = $pdo->prepare("SELECT id, titulo FROM novidades WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $noticia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$noticia) {
        header("Location: dashboard.php");
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM novidades WHERE id = :id");
    $stmt->execute([':id' => $id]);
} catch (PDOException $e) {
    // Silencia o erro e redireciona
}

header("Location: dashboard.php");
exit;

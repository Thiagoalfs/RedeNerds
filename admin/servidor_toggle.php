<?php
require_once "sessao.php";
require_once "../../config.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: servidores.php");
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE servidores SET enabled = NOT enabled WHERE id = :id");
    $stmt->execute([':id' => $id]);
} catch (PDOException $e) {
    // Evita tela branca de erro PDO
}

header("Location: servidores.php");
exit;

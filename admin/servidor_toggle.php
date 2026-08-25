<?php
require_once "sessao.php";
$configPaths = [
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}
if (!$configPath) {
    die("Erro: Arquivo config.php não encontrado.");
}
require_once $configPath;

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

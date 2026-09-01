<?php
require_once __DIR__ . "/../../sessao.php";
$configPaths = [
    __DIR__ . "/config.php",
    __DIR__ . "/../config.php",
    __DIR__ . "/../../config.php",
    __DIR__ . "/../../../config.php",
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
];
$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}
if ($configPath) {
    require_once $configPath;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("DELETE FROM wiki_artigos WHERE id = :id");
        $stmt->execute([':id' => $id]);
    } catch (Exception $e) {
        error_log("Erro ao deletar artigo wiki: " . $e->getMessage());
    }
}
header("Location: ../../wiki.php");
exit;
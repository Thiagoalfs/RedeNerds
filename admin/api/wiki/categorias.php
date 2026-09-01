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
require_once __DIR__ . "/../../../wiki/wiki_helper.php";

header('Content-Type: application/json; charset=utf-8');

$servidorId = isset($_GET['servidor_id']) ? (int)$_GET['servidor_id'] : 0;
if ($servidorId <= 0 || !isset($pdo) || !$pdo instanceof PDO) {
    echo json_encode(["categorias" => []]);
    exit;
}

$categorias = getCategoriasServidorWiki($pdo, $servidorId);
echo json_encode(["categorias" => $categorias], JSON_UNESCAPED_UNICODE);
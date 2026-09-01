<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../../config.php";
require_once __DIR__ . "/../../../wiki/wiki_helper.php";

header('Content-Type: application/json; charset=utf-8');

$servidorId = isset($_GET['servidor_id']) ? (int)$_GET['servidor_id'] : 0;
if ($servidorId <= 0) {
    echo json_encode(["categorias" => []]);
    exit;
}

$categorias = getCategoriasServidorWiki($pdo, $servidorId);
echo json_encode(["categorias" => $categorias], JSON_UNESCAPED_UNICODE);
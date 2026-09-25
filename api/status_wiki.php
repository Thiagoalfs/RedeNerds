<?php
/**
 * status_wiki.php
 * Retorna o status de visualização pública da Wiki para o Navbar do site.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-cache, no-store, must-revalidate");

require_once __DIR__ . "/rate_limiter.php";
exigirRateLimit('api_status_wiki', 60, 60);

$configPaths = [
    __DIR__ . "/../config.php",
    __DIR__ . "/../../config.php",
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

require_once __DIR__ . "/../wiki/wiki_helper.php";

$habilitada = true;
if (isset($pdo) && $pdo instanceof PDO) {
    $habilitada = isWikiHabilitada($pdo);
}

echo json_encode([
    "success" => true,
    "wiki_habilitada" => (bool)$habilitada
], JSON_UNESCAPED_UNICODE);

<?php
/**
 * localidade.php
 * Endpoint leve para sugerir ao frontend a modalidade de pagamento adequada (nacional vs internacional).
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config_loja.php";
aplicarCorsLoja();

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/geo_helper.php";

$tz = $_GET['tz'] ?? ($_POST['tz'] ?? null);
$lang = $_GET['lang'] ?? ($_POST['lang'] ?? null);

// Se enviado via payload JSON
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        if ($tz === null && isset($jsonData['tz'])) {
            $tz = (string)$jsonData['tz'];
        }
        if ($lang === null && isset($jsonData['lang'])) {
            $lang = (string)$jsonData['lang'];
        }
    }
}

$resultado = obterSugestaoLocalidade(
    is_string($tz) ? $tz : null,
    is_string($lang) ? $lang : null
);

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);

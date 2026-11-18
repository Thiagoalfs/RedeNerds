<?php
/**
 * ranking_api.php
 * Endpoint público (protegido por Same-Origin/API Key) que retorna o Top 5 maiores doadores.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=60');

require_once __DIR__ . '/../rate_limiter.php';
exigirRateLimit('loja_ranking_api', 60, 60);

$configPaths = [
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../config.php',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/config.php',
];

$configPath = null;
foreach ($configPaths as $cp) {
    if (!empty($cp) && file_exists($cp)) {
        $configPath = $cp;
        break;
    }
}

if (!$configPath) {
    http_response_code(500);
    echo json_encode(['success' => false, 'erro' => 'Configuração não encontrada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $configPath;
require_once __DIR__ . '/../auth_api.php';
verificarAcessoApi();
require_once __DIR__ . '/ranking_helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo json_encode(['success' => true, 'ranking' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $ranking = calcularRankingDoadores($pdo, 5);
    echo json_encode(['success' => true, 'ranking' => $ranking], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Erro em ranking_api.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'ranking' => [], 'erro' => 'Erro ao carregar ranking.'], JSON_UNESCAPED_UNICODE);
}

<?php
date_default_timezone_set('America/Sao_Paulo');

// 1. Carregador nativo de .env (para ambientes fora do Docker, sem dependências externas)
if (file_exists(__DIR__ . '/.env')) {
    $envLines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (getenv($key) === false) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// 2. Função estrita: exige a variável de ambiente. Se faltar, aborta imediatamente sem expor dados.
function env_require(string $key): string {
    $val = getenv($key);
    if ($val === false || trim($val) === '') {
        http_response_code(500);
        die("FATAL: A variável de ambiente obrigatória '{$key}' não está definida. Verifique seu arquivo .env.");
    }
    return trim($val);
}

// ========================================================
// BANCO DE DADOS (100% via ambiente)
// ========================================================
$host = env_require('DB_HOST');
$user = env_require('DB_USER');
$pass = env_require('DB_PASS');
$db   = env_require('DB_NAME');

$pdo = null;
$conn = null;
$db_error = null;

// Tenta conexão via PDO (se a extensão pdo_mysql estiver ativa)
if (extension_loaded('pdo_mysql')) {
    try {
        $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 4
        ]);
    } catch (Exception $e) {
        $db_error = $e->getMessage();
        $pdo = null;
    }
}

// Tenta conexão via MySQLi (como alternativa / fallback)
if (extension_loaded('mysqli')) {
    try {
        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @new mysqli($host, $user, $pass, $db);
        if ($conn->connect_error) {
            if (!$db_error) $db_error = $conn->connect_error;
            $conn = null;
        } else {
            $conn->set_charset("utf8mb4");
        }
    } catch (Exception $e) {
        $conn = null;
    }
}

// ========================================================
// SEGREDOS, TOKENS E WEBHOOKS (Sem fallback)
// ========================================================
define('API_SECRET_KEY', env_require('API_SECRET_KEY'));

define('DISCORD_WEBHOOKS', [
    'Anúncios'     => env_require('DISCORD_WEBHOOK_ANUNCIOS'),
    'Atualizações' => env_require('DISCORD_WEBHOOK_ATUALIZACOES'),
    'Loja'         => env_require('DISCORD_WEBHOOK_LOJA'),
]);

define('MERCADO_PAGO_ACCESS_TOKEN',    env_require('MERCADO_PAGO_ACCESS_TOKEN'));
define('MERCADO_PAGO_PUBLIC_KEY',     env_require('MERCADO_PAGO_PUBLIC_KEY'));
define('MERCADO_PAGO_WEBHOOK_SECRET', env_require('MERCADO_PAGO_WEBHOOK_SECRET'));

define('DELIVERY_SERVERS', [
    'cobblenerd' => [
        'url'   => env_require('DELIVERY_COBBLENERD_URL'),
        'token' => env_require('DELIVERY_COBBLENERD_TOKEN'),
    ],
    'potatosky' => [
        'url'   => env_require('DELIVERY_POTATOSKY_URL'),
        'token' => env_require('DELIVERY_POTATOSKY_TOKEN'),
    ],
    'aerocity' => [
        'url'   => env_require('DELIVERY_AEROCITY_URL'),
        'token' => env_require('DELIVERY_AEROCITY_TOKEN'),
    ],
    'drefriends' => [
        'url'   => env_require('DELIVERY_DREFRIENDS_URL'),
        'token' => env_require('DELIVERY_DREFRIENDS_TOKEN'),
    ]
]);

define('DELIVERY_API_URL',   env_require('DELIVERY_DEFAULT_URL'));
define('DELIVERY_API_TOKEN', env_require('DELIVERY_DEFAULT_TOKEN'));

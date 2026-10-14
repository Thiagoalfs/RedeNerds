<?php
// Garante buffer de saída ativo para redirecionamentos seguros
if (ob_get_level() === 0) {
    ob_start();
}

// Cookie de sessão mais seguro (httponly + samesite; secure quando em HTTPS)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Carrega o config.php (fonte única de $pdo para o painel e seus endpoints)
if (!isset($pdo) || !$pdo instanceof PDO) {
    $configPaths = [
        __DIR__ . "/../../../config.php",
        __DIR__ . "/../../config.php",
        __DIR__ . "/../config.php",
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . "/config.php"
    ];
    foreach ($configPaths as $cp) {
        if (!empty($cp) && file_exists($cp)) {
            require_once $cp;
            break;
        }
    }
}

// Rate limit preventivo para endpoints de API interna do painel.
// Antes da checagem de login (requisições não autenticadas também contam) e com
 // buckets separados: leitura (GET) tem cota própria para não bloquear escritas.
if (stripos($_SERVER['REQUEST_URI'] ?? '', '/admin/api/') !== false) {
    require_once __DIR__ . "/../api/rate_limiter.php";
    $ehGet = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET');
    exigirRateLimit($ehGet ? 'admin_api_read' : 'admin_api_action', $ehGet ? 120 : 60, 60);
}

// Se não estiver logado ou não for admin
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
    $isApiOrAjax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
        (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false)
    );

    if ($isApiOrAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["erro" => "Sessão expirada. Faça login novamente no painel."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header("Location: /admin/index.php");
    exit;
}

// Inicializa token CSRF seguro por sessão
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/**
 * Valida o token CSRF de requisições POST.
 * Retorna true se válido, ou encerra com 403 Forbidden caso inválido.
 */
if (!function_exists('validarCsrfToken')) {
    function validarCsrfToken(?string $tokenRecebido): bool {
        $tokenSessao = $_SESSION['csrf_token'] ?? '';
        return !empty($tokenRecebido) && !empty($tokenSessao) && hash_equals($tokenSessao, $tokenRecebido);
    }
}

/**
 * Lê o token CSRF de qualquer fonte suportada: campo do formulário,
 * header X-CSRF-Token ou corpo JSON.
 */
if (!function_exists('obterCsrfTokenRequisicao')) {
    function obterCsrfTokenRequisicao(): string {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($token === '' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            if ($raw) {
                $json = json_decode($raw, true);
                if (is_array($json) && !empty($json['csrf_token'])) {
                    $token = (string)$json['csrf_token'];
                }
            }
        }
        return (string)$token;
    }
}

if (!function_exists('exigirCSRF')) {
    function exigirCSRF() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["erro" => "Método não permitido. Requisição deve ser POST."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $tokenRecebido = obterCsrfTokenRequisicao();

        if (!validarCsrfToken($tokenRecebido)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["erro" => "Token CSRF inválido ou expirado."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        return true;
    }
}
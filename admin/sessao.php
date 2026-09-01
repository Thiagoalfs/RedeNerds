<?php
// Arquivo responsável por verificar a sessão em todas as páginas protegidas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
if (!function_exists('exigirCSRF')) {
    function exigirCSRF() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["erro" => "Método não permitido. Requisição deve ser POST."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $tokenRecebido = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $tokenSessao = $_SESSION['csrf_token'] ?? '';

        if (empty($tokenRecebido) || empty($tokenSessao) || !hash_equals($tokenSessao, $tokenRecebido)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(["erro" => "Token CSRF inválido ou expirado."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        return true;
    }
}
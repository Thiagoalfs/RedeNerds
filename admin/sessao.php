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
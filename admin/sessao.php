<?php
// Arquivo responsável por verificar a sessão em todas as páginas protegidas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se não estiver logado ou não for admin, redireciona para o login
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
    header("Location: index.php");
    exit;
}
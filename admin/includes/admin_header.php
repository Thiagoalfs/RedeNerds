<?php
// Garante verificação de sessão
require_once __DIR__ . "/../sessao.php";

$configPaths = [
    __DIR__ . "/../../../config.php",
    __DIR__ . "/../../config.php",
    __DIR__ . "/../config.php",
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

$nome_usuario = $_SESSION['usuario_nome'] ?? 'Administrador';
$paginaAtiva = $paginaAtiva ?? 'dashboard';
$tituloPagina = $tituloPagina ?? 'Painel Administrativo';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8'); ?> - Rede Nerds</title>
    <link rel="icon" type="image/x-icon" href="/assets/images/logo.webp">
    
    <!-- GOOGLE FONTS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FONTAWESOME & BOOTSTRAP -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FOLHA DE ESTILOS CENTRALIZADA -->
    <link rel="stylesheet" href="admin.css?v=2">
</head>
<body>
    <div class="admin-layout">
        <!-- SIDEBAR -->
        <?php require __DIR__ . "/admin_sidebar.php"; ?>

        <!-- CONTEÚDO PRINCIPAL -->
        <div class="admin-main">
            <!-- TOPBAR -->
            <header class="admin-topbar">
                <div class="topbar-left">
                    <button type="button" class="btn-toggle-sidebar d-lg-none" id="btn-toggle-sidebar" title="Menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="topbar-breadcrumbs d-none d-sm-flex">
                        <a href="dashboard.php"><i class="fa-solid fa-house"></i> Painel</a>
                        <i class="fa-solid fa-chevron-right small text-muted"></i>
                        <span class="current"><?php echo htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>

                <div class="topbar-right">
                    <a href="/loja/" target="_blank" class="btn-view-site">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span>Ver Loja</span>
                    </a>
                    <a href="/" target="_blank" class="btn-view-site d-none d-md-inline-flex">
                        <i class="fa-solid fa-globe"></i>
                        <span>Ver Site</span>
                    </a>
                </div>
            </header>

            <main class="admin-content">